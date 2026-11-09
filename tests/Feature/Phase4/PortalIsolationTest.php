<?php

namespace Tests\Feature\Phase4;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorPayout;
use App\Models\Project;
use App\Models\User;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalAccountService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Release gate for the portals: every portal route refuses records owned by someone else, every internal route
 * refuses portal accounts, and no route is left outside the staff or portal groups by accident.
 */
class PortalIsolationTest extends TestCase
{
    private const OPEN_ROUTES = [
        'login', 'login.store', 'two-factor.challenge', 'two-factor.verify', 'password.request', 'password.request.store', 'logout',
        'profile.edit', 'profile.update', 'profile.password', 'profile.two-factor.enable', 'profile.two-factor.confirm',
        'profile.two-factor.recovery', 'profile.two-factor.disable', 'profile.sessions.logout-others',
    ];

    private Investor $investor;

    private Customer $customer;

    private User $investorUser;

    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['keyone.super_admin.password' => 'Admin@12345']);
        $this->seed([SuperAdminSeeder::class, ExpenseCategorySeeder::class, SampleDataSeeder::class, SampleDataSeeder::class]);
        Project::factory()->create(['name' => 'Key One Green Court']);
        $admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
        $checker = User::factory()->create();

        $this->investor = Investor::query()->whereHas('agreements', fn ($q) => $q->where('status', 'active'))->orderBy('id')->firstOrFail();
        $this->customer = FlatSale::query()->orderBy('id')->firstOrFail()->customer;
        $accounts = app(PortalAccountService::class);
        foreach (['investor' => $this->investor, 'customer' => $this->customer] as $type => $owner) {
            $link = $accounts->createAccount($owner, ['name' => $owner->name ?? $owner->full_name, 'email' => $type.'-portal@example.com', 'password' => 'Secret123'], $admin);
            $accounts->verify($link, $checker);
            $link->user->forceFill(['must_change_password' => false])->save();
            $this->{$type.'User'} = $link->user->fresh();
        }
    }

    private function routes(): Collection
    {
        return collect(app('router')->getRoutes()->getRoutes())->filter(fn (Route $r) => $r->getName() && ! str_starts_with($r->getName(), 'dev.'));
    }

    private function url(Route $route, array $params): string
    {
        return '/'.ltrim(preg_replace_callback('/\{([^}?]+)\??\}/', fn ($m) => $params[$m[1]] ?? Str::random(16), $route->uri()), '/');
    }

    public function test_every_route_belongs_to_the_staff_or_a_portal_group(): void
    {
        $stray = $this->routes()
            ->reject(fn (Route $r) => in_array($r->getName(), self::OPEN_ROUTES, true) || $r->uri() === 'up')
            ->reject(fn (Route $r) => in_array('staff', $r->gatherMiddleware(), true))
            ->reject(fn (Route $r) => collect($r->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'portal:')))
            ->map(fn (Route $r) => $r->getName())->values()->all();

        $this->assertSame([], $stray, 'Routes outside the staff and portal groups.');
    }

    public function test_portal_accounts_reach_no_internal_route(): void
    {
        $staff = $this->routes()->filter(fn (Route $r) => in_array('staff', $r->gatherMiddleware(), true));
        $this->assertGreaterThan(250, $staff->count());

        foreach ([$this->investorUser, $this->customerUser] as $user) {
            foreach ($staff as $route) {
                $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
                $response = $this->actingAs($user)->ajax()->json($method, $this->url($route, []));
                if ($response->getStatusCode() !== 404) {
                    $failures[] = "{$user->account_type}: {$route->getName()} {$method} → {$response->getStatusCode()}";
                }
            }
            $this->flushHeaders();
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(PortalAccess::homeRoute($user));
            $this->actingAs($user)->get(route($user->account_type === 'investor' ? 'customer.dashboard' : 'investor.dashboard'))
                ->assertRedirect(PortalAccess::homeRoute($user));
        }

        $this->assertSame([], $failures ?? [], 'Internal routes that answer a portal account.');
    }

    public function test_portal_routes_refuse_records_of_other_owners(): void
    {
        $mineFlats = PortalAccess::investorFlatIds($this->investor);
        $foreign = [
            'agreement' => InvestorFlatAgreement::query()->where('investor_id', '!=', $this->investor->id)->pluck('public_id'),
            'transaction' => InvestorCapitalTransaction::query()->where('investor_id', '!=', $this->investor->id)->pluck('public_id'),
            'payout' => InvestorPayout::query()->where('investor_id', '!=', $this->investor->id)->pluck('public_id'),
            'project' => Project::query()->whereNotIn('id', PortalAccess::investorProjectIds($this->investor))->pluck('public_id'),
            'file' => Attachment::query()->pluck('public_id'),
            'payment' => CustomerPayment::query()->where(fn ($q) => $q->where('customer_id', '!=', $this->customer->id)->orWhereNotIn('status', ['posted', 'reversed']))->pluck('public_id'),
            'unit' => FlatSale::query()->where('customer_id', '!=', $this->customer->id)->pluck('public_id')
                ->merge(FlatBooking::query()->where('customer_id', '!=', $this->customer->id)->pluck('public_id')),
        ];
        $this->assertNotEmpty($foreign['agreement']);
        $this->assertNotEmpty($foreign['project']);
        $this->assertNotEmpty($foreign['unit']);
        $this->assertNotEmpty($foreign['payment']);

        $checked = 0;
        foreach ($this->routes()->filter(fn (Route $r) => preg_match('/^(investor|customer)\./', $r->getName()) && $r->parameterNames() !== []) as $route) {
            $user = str_starts_with($route->getName(), 'investor.') ? $this->investorUser : $this->customerUser;
            foreach ($route->parameterNames() as $param) {
                $this->assertArrayHasKey($param, $foreign, "Portal route [{$route->getName()}] has an unmapped parameter {{$param}}.");
                foreach ([Str::random(16), ...$foreign[$param]->all()] as $id) {
                    $status = $this->actingAs($user)->get($this->url($route, [$param => $id]))->getStatusCode();
                    if ($status !== 404) {
                        $failures[] = "{$route->getName()} {{$param}}={$id} → {$status}";
                    }
                    $checked++;
                }
            }
        }

        // Filters that take a public id answer 404 for another owner's record.
        $otherFlat = Flat::query()->whereNotIn('id', $mineFlats)->value('public_id');
        foreach (['investor.statements.table', 'investor.statements.export'] as $name) {
            $this->assertSame(404, $this->actingAs($this->investorUser)->ajax()->get(route($name, ['flat' => $otherFlat]))->getStatusCode(), $name);
        }
        foreach ($foreign['unit'] as $unit) {
            foreach (['customer.installments.index', 'customer.payments.table'] as $name) {
                $this->assertSame(404, $this->actingAs($this->customerUser)->ajax()->get(route($name, ['unit' => $unit]))->getStatusCode(), $name);
            }
        }
        $this->flushHeaders();
        $rows = $this->actingAs($this->investorUser)->ajax()->get(route('investor.investments.table', ['project' => $foreign['project']->first()]))->assertOk();
        $this->assertSame(0, $rows->json('meta.total') ?? $rows->json('total') ?? 0);

        $this->assertSame([], $failures ?? [], 'Portal routes that answer for another owner.');
        $this->assertGreaterThan(20, $checked);
    }

    public function test_portal_pages_render_for_their_owners_without_internal_ids(): void
    {
        foreach (['investor' => [$this->investorUser, ['investor.dashboard', 'investor.investments.index', 'investor.projects.index', 'investor.documents.index', 'investor.statements.index', 'investor.profile'], ['investor.investments.table', 'investor.statements.table']],
            'customer' => [$this->customerUser, ['customer.dashboard', 'customer.flats.index', 'customer.installments.index', 'customer.payments.index', 'customer.statements.index', 'customer.profile'], ['customer.payments.table', 'customer.statements.table']]] as [$user, $views, $tables]) {
            foreach ($views as $name) {
                $this->flushHeaders();
                $this->actingAs($user)->get(route($name))->assertOk();
            }
            foreach ($tables as $name) {
                $json = $this->actingAs($user)->ajax()->get(route($name))->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/"(id|[a-z_]+_id|[a-zA-Z]+Id)"\s*:/', $json, $name);
            }
        }
    }
}

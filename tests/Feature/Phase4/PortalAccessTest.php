<?php

namespace Tests\Feature\Phase4;

use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorUserLink;
use App\Models\PortalAccessAudit;
use App\Models\PortalAccessGrant;
use App\Models\Project;
use App\Models\User;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalAccountService;
use Database\Factories\FlatFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    private User $admin;

    private User $checker;

    private PortalAccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWith(['investor.view', 'customers.view_all', 'portal.links.manage', 'portal.grants.manage']);
        $this->checker = $this->userWith(['investor.view', 'customers.view_all', 'portal.links.manage', 'portal.grants.manage']);
        $this->accounts = app(PortalAccountService::class);
    }

    private function investorAccount(?Investor $investor = null, bool $verify = true): InvestorUserLink
    {
        $investor ??= app(InvestorService::class)->saveInvestor(null, ['name' => 'Karim', 'status' => 'active'], $this->admin);
        $link = $this->accounts->createAccount($investor, ['name' => $investor->name, 'email' => uniqid().'@example.com', 'password' => 'Secret123'], $this->admin);
        if ($verify) {
            $this->accounts->verify($link, $this->checker);
        }

        return $link->fresh();
    }

    private function holding(Investor $investor): Flat
    {
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create();
        $s = app(InvestorService::class);
        $a = $s->saveAgreement(null, $investor, $flat, ['committed_capital' => '100', 'profit_share_percent' => '10', 'effective_from' => now()->toDateString()], $this->admin);
        $s->submitAgreement($a, $this->admin);
        $s->approveAgreement($a, $this->checker);

        return $flat;
    }

    public function test_portal_entry_needs_a_verified_active_link_of_the_right_type(): void
    {
        $link = $this->investorAccount(verify: false);
        $user = $link->user;
        $this->assertSame('investor', $user->account_type);
        $this->assertTrue($user->hasRole('Investor'));
        $this->assertTrue($user->must_change_password);
        $user->forceFill(['must_change_password' => false])->save();

        $this->actingAs($user)->get(route('investor.dashboard'))->assertForbidden();
        $this->assertSame('denied', PortalAccessAudit::query()->latest('id')->value('result'));
        try {
            $this->accounts->verify($link, $this->admin);
            $this->fail('The creator must not verify.');
        } catch (ValidationException) {
        }
        $this->accounts->verify($link, $this->checker);
        $this->actingAs($user)->get(route('investor.dashboard'))->assertOk()->assertSee('Karim');
        $this->actingAs($user)->get(route('investor.profile'))->assertOk()->assertSee('Investor Portal');
        $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('investor.profile'));

        // Portal accounts never reach internal pages, and the other portal sends them home.
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('investor.dashboard'));
        $this->actingAs($user)->get(route('projects.index'))->assertRedirect(route('investor.dashboard'));
        $this->actingAs($user)->ajax()->get(route('investors.table'))->assertNotFound();
        $this->flushHeaders();
        $this->actingAs($user)->get(route('customer.dashboard'))->assertRedirect(route('investor.dashboard'));

        // Staff never enter a portal.
        $this->actingAs($this->admin)->get(route('investor.dashboard'))->assertNotFound();
        $this->actingAs($this->admin)->ajax()->get(route('customer.dashboard'))->assertNotFound();
        $this->flushHeaders();

        // Revoking ends access on the next request and drops stored sessions.
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->accounts->revoke($link, 'Left the project', $this->admin);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->actingAs($user->fresh())->get(route('investor.dashboard'))->assertForbidden();
    }

    public function test_login_sends_portal_accounts_to_their_portal(): void
    {
        $link = $this->investorAccount();
        $link->user->forceFill(['must_change_password' => false])->save();
        $this->post(route('login.store'), ['login' => $link->user->email, 'password' => 'Secret123'])->assertRedirect(route('investor.dashboard'));

        $customer = Customer::factory()->create();
        $c = $this->accounts->createAccount($customer, ['name' => 'Cust', 'mobile' => '01700000001', 'password' => 'Secret123'], $this->admin);
        $this->accounts->verify($c, $this->checker);
        $c->user->forceFill(['must_change_password' => false])->save();
        auth()->logout();
        $this->post(route('login.store'), ['login' => '01700000001', 'password' => 'Secret123'])->assertRedirect(route('customer.dashboard'));
        $this->get(route('customer.dashboard'))->assertOk()->assertSee($customer->full_name);
    }

    public function test_grants_open_capabilities_only_on_held_resources_and_expire(): void
    {
        $link = $this->investorAccount();
        $investor = $link->investor;
        $flat = $this->holding($investor);
        $other = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create();
        $user = $link->user;
        $cap = 'investor.portal.cost.material.view';

        $this->assertTrue(PortalAccess::allows($user, 'investor.portal.flat.financials.view'));
        $this->assertFalse(PortalAccess::allows($user, $cap, $flat));
        try {
            $this->accounts->grant($link, $other->project, $cap, null, $this->admin);
            $this->fail('Grant on a project the investor holds nothing in must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('resource', $e->errors());
        }

        $grant = $this->accounts->grant($link, $flat->project, $cap, null, $this->admin);
        $this->assertTrue(PortalAccess::allows($user, $cap, $flat));
        $this->assertTrue(PortalAccess::allows($user, $cap, $flat->project));
        $this->assertFalse(PortalAccess::allows($user, $cap, $other));
        $this->assertFalse(PortalAccess::allows($user, 'investor.portal.cost.labour.view', $flat));

        $before = PortalAccess::cacheKey($user, 'x');
        $this->accounts->revokeGrant($grant, $this->admin);
        $this->assertNotSame($before, PortalAccess::cacheKey($user, 'x'));
        $this->assertFalse(PortalAccess::allows($user, $cap, $flat));

        $expiring = $this->accounts->grant($link, $flat, $cap, now()->addDay()->toDateString(), $this->admin);
        $this->assertTrue(PortalAccess::allows($user, $cap, $flat));
        $this->travel(2)->days();
        $this->assertFalse(PortalAccess::allows($user, $cap, $flat));
        $this->assertFalse($expiring->fresh()->isCurrent());
    }

    public function test_admin_screens_manage_accounts_and_grants(): void
    {
        $investor = app(InvestorService::class)->saveInvestor(null, ['name' => 'Rahim', 'status' => 'active'], $this->admin);
        $flat = $this->holding($investor);

        $this->actingAs($this->admin)->ajax()->post(route('portal-access.store', ['investor', $investor->public_id]), ['name' => 'Rahim', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.store', ['investor', $investor->public_id]), ['name' => 'Rahim', 'email' => 'rahim@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertOk();
        $link = InvestorUserLink::query()->firstOrFail();
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.links.verify', ['investor', $link->public_id]))->assertStatus(422);
        $this->actingAs($this->checker)->ajax()->post(route('portal-access.links.verify', ['investor', $link->public_id]))->assertOk();

        $html = $this->actingAs($this->admin)->ajax()->get(route('portal-access.panel', ['investor', $investor->public_id]))->assertOk()->json('data.html');
        $this->assertStringContainsString('rahim@example.com', $html);
        $this->assertStringContainsString('Active', $html);

        $this->actingAs($this->admin)->ajax()->get(route('portal-access.grants.create', ['investor', $link->public_id]))->assertOk()->assertSee('project:'.$flat->project->public_id);
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.grants.store', ['investor', $link->public_id]), ['resource' => 'project:'.$flat->project->public_id, 'capability' => 'investor.portal.project.financials.view'])->assertOk();
        $grant = PortalAccessGrant::query()->firstOrFail();
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.grants.store', ['investor', $link->public_id]), ['resource' => 'project:'.$flat->project->public_id, 'capability' => 'admin.everything'])->assertStatus(422);
        $this->actingAs($this->admin)->ajax()->delete(route('portal-access.grants.revoke', $grant))->assertOk();
        $this->assertNotNull($grant->fresh()->revoked_at);

        $this->actingAs($this->admin)->ajax()->put(route('portal-access.links.password', ['investor', $link->public_id]), ['password' => 'NewSecret9', 'password_confirmation' => 'NewSecret9'])->assertOk();
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.links.revoke', ['investor', $link->public_id]), ['reason' => 'Exit'])->assertOk();
        $this->assertFalse($link->fresh()->isUsable());

        $customer = Customer::factory()->create();
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.store', ['customer', $customer->public_id]), ['name' => 'C', 'mobile' => '01811223344', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertOk();
        $this->assertSame(1, CustomerUserLink::query()->count());
        $this->actingAs($this->admin)->get(route('investors.show', $investor))->assertOk()->assertSee('Portal');

        $clerk = $this->userWith(['investor.view']);
        $this->actingAs($clerk)->ajax()->get(route('portal-access.panel', ['investor', $investor->public_id]))->assertForbidden();
        $this->actingAs($clerk)->ajax()->post(route('portal-access.store', ['investor', $investor->public_id]), [])->assertForbidden();
        $this->actingAs($this->admin)->ajax()->get(route('portal-access.panel', ['investor', 'xxxxxxxxxxxxxxxx']))->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Phase4;

use App\Models\ConstructionProgressUpdate;
use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\PortalAccessGrant;
use App\Models\Project;
use App\Models\User;
use App\Modules\Portal\Services\PortalAccountService;
use App\Modules\Sales\Services\SalesService;
use Database\Factories\FlatFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerProgressTest extends TestCase
{
    private User $admin;

    private User $aliceUser;

    private CustomerUserLink $link;

    private FlatSale $sale;

    private Project $other;

    protected function setUp(): void
    {
        parent::setUp();
        [$maker, $checker] = [User::factory()->create(), User::factory()->create()];
        $this->admin = $this->superAdmin();
        $project = Project::factory()->create();
        $this->other = Project::factory()->create();
        $alice = Customer::factory()->create();
        $sales = app(SalesService::class);
        $sell = function (Customer $c, Project $p) use ($sales, $maker, $checker) {
            $sale = $sales->saveSale(null, Flat::factory()->forFloor(FlatFactory::floorFor($p))->create(), $c, null, ['agreement_date' => now()->toDateString(), 'sale_price' => '1000000'], $maker);
            $sales->submitSale($sale, $maker);
            $sales->approveSale($sale, $checker);

            return $sale->fresh();
        };
        $this->sale = $sell($alice, $project);
        $sell(Customer::factory()->create(), $this->other);

        foreach ([[$project, 'Roof slab cast', 'investor'], [$project, 'Internal contractor note', 'internal'], [$this->other, 'Other project update', 'investor']] as [$p, $note, $visibility]) {
            ConstructionProgressUpdate::query()->forceCreate([
                'public_id' => Str::lower(Str::random(16)), 'project_id' => $p->id, 'progress_percent' => 40, 'report_date' => now()->toDateString(), 'notes' => $note,
                'visibility' => $visibility, 'status' => 'approved', 'submitted_by' => $maker->id, 'published_at' => $visibility === 'investor' ? now() : null,
            ]);
        }

        $accounts = app(PortalAccountService::class);
        $this->link = $accounts->createAccount($alice, ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'Secret123'], $maker);
        $accounts->verify($this->link, $checker);
        $this->link->user->forceFill(['must_change_password' => false])->save();
        $this->aliceUser = $this->link->user->fresh();
    }

    public function test_progress_is_hidden_until_an_admin_opens_it_for_a_unit_or_project(): void
    {
        $page = route('customer.projects.show', $this->sale->project->public_id);
        $unit = route('customer.flats.show', $this->sale->public_id);
        $this->actingAs($this->aliceUser)->get($page)->assertNotFound();
        $this->actingAs($this->aliceUser)->get($unit)->assertOk()->assertDontSee('View Progress');

        $this->actingAs($this->admin)->ajax()->get(route('portal-access.grants.create', ['customer', $this->link->public_id]))->assertOk()
            ->assertSee('Project progress')->assertDontSee('Project financials');
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.grants.store', ['customer', $this->link->public_id]), [
            'resource' => 'project:'.$this->other->public_id, 'capability' => 'customer.portal.project.progress.view',
        ])->assertStatus(422);
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.grants.store', ['customer', $this->link->public_id]), [
            'resource' => 'project:'.$this->sale->project->public_id, 'capability' => 'investor.portal.project.financials.view',
        ])->assertStatus(422);
        $this->actingAs($this->admin)->ajax()->post(route('portal-access.grants.store', ['customer', $this->link->public_id]), [
            'resource' => 'flat:'.$this->sale->flat->public_id, 'capability' => 'customer.portal.project.progress.view',
        ])->assertOk();

        $this->flushHeaders();
        $this->actingAs($this->aliceUser)->get($unit)->assertOk()->assertSee('View Progress');
        $this->actingAs($this->aliceUser)->get($page)->assertOk()->assertSee('Roof slab cast')
            ->assertDontSee('Internal contractor note')->assertDontSee('Other project update');
        $this->actingAs($this->aliceUser)->get(route('customer.projects.show', $this->other->public_id))->assertNotFound();

        $this->travel(2)->days();
        $grant = PortalAccessGrant::query()->firstOrFail();
        $grant->forceFill(['expires_at' => now()->subDay()])->save();
        $this->actingAs($this->aliceUser)->get($page)->assertNotFound();
    }

    public function test_role_permission_opens_progress_for_every_customer_project(): void
    {
        Role::findByName('Customer')->givePermissionTo('customer.portal.project.progress.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->aliceUser->fresh())->get(route('customer.projects.show', $this->sale->project->public_id))->assertOk()->assertSee('Roof slab cast');
        $this->actingAs($this->aliceUser->fresh())->get(route('customer.projects.show', $this->other->public_id))->assertNotFound();
    }
}

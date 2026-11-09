<?php

namespace Tests\Feature\Phase4;

use App\Models\Attachment;
use App\Models\ConstructionProgressUpdate;
use App\Models\Customer;
use App\Models\Flat;
use App\Models\FlatProfitCalculation;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\PortalAccessAudit;
use App\Models\Project;
use App\Models\User;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Investors\Services\DistributionService;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Investors\Services\ProfitService;
use App\Modules\Portal\Services\PortalAccountService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Costing\CostAttribution;
use Database\Factories\FlatFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvestorPortalTest extends TestCase
{
    private User $maker;

    private User $checker;

    private Flat $flat;

    private Flat $other;

    private Investor $karim;

    private Investor $rahim;

    private User $karimUser;

    private User $rahimUser;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->maker, $this->checker] = [User::factory()->create(), User::factory()->create()];
        $project = Project::factory()->create();
        $floor = FlatFactory::floorFor($project);
        $this->flat = Flat::factory()->forFloor($floor)->create(['saleable_area' => 1000]);
        $this->other = Flat::factory()->forFloor($floor)->create(['saleable_area' => 1000]);

        $inv = app(InvestorService::class);
        $this->karim = $inv->saveInvestor(null, ['name' => 'Karim Portal', 'status' => 'active'], $this->maker);
        $this->rahim = $inv->saveInvestor(null, ['name' => 'Rahim Portal', 'status' => 'active'], $this->maker);
        foreach ([[$this->karim, $this->flat, '60'], [$this->rahim, $this->flat, '30'], [$this->rahim, $this->other, '50']] as [$i, $f, $share]) {
            $a = $inv->saveAgreement(null, $i, $f, ['committed_capital' => '1000000', 'profit_share_percent' => $share, 'effective_from' => now()->subYear()->toDateString()], $this->maker);
            $inv->submitAgreement($a, $this->maker);
            $inv->approveAgreement($a, $this->checker);
            $t = $inv->recordCapital($a->fresh(), 'contribution', ['amount' => $share.'0000', 'transaction_date' => now()->toDateString()], $this->maker, (string) Str::uuid());
            $inv->postCapital($t, $this->checker);
        }

        $accounts = app(PortalAccountService::class);
        foreach (['karim' => $this->karim, 'rahim' => $this->rahim] as $key => $investor) {
            $link = $accounts->createAccount($investor, ['name' => $investor->name, 'email' => $key.'@example.com', 'password' => 'Secret123'], $this->maker);
            $accounts->verify($link, $this->checker);
            $link->user->forceFill(['must_change_password' => false])->save();
            $this->{$key.'User'} = $link->user->fresh();
        }

        $sales = app(SalesService::class);
        $sale = $sales->saveSale(null, $this->flat, Customer::factory()->create(), null, ['agreement_date' => now()->toDateString(), 'sale_price' => '3000000'], $this->maker);
        $sales->submitSale($sale, $this->maker);
        $sales->approveSale($sale, $this->checker);
        app(CostAttribution::class)->post($project, User::factory()->create(), 'material', '1000000', now()->subDay());
        app(CostAttribution::class)->post($project, User::factory()->create(), 'contractor', '600000', now()->subDay());
        $runs = app(CostAllocationService::class);
        $runs->approve($runs->run($project, now()->toDateString(), $this->maker), $this->checker);
    }

    private function approvedProfit(): FlatProfitCalculation
    {
        $profit = app(ProfitService::class);
        $c = $profit->calculate($this->flat, ['as_of' => now()->toDateString()], $this->maker);
        $profit->submit($c, $this->maker);
        $profit->approve($c, $this->checker);

        return $c->fresh();
    }

    public function test_overview_and_investments_show_only_the_signed_in_investor(): void
    {
        $this->approvedProfit();
        $dist = app(DistributionService::class);
        $d = $dist->request($this->karim, $this->flat, '100000', $this->maker, (string) Str::uuid());
        $dist->approve($d, $this->checker);
        $dist->pay($d, ['paid_at' => now()->toDateString(), 'payment_method' => 'bank'], $this->maker, (string) Str::uuid());

        // 3,000,000 − 800,000 shared (half of 1.6M) = 2,200,000; Karim 60% = 1,320,000
        $this->actingAs($this->karimUser)->get(route('investor.dashboard'))->assertOk()
            ->assertSee('6,00,000')->assertSee('1,00,000')->assertSee('12,20,000')->assertDontSee('Rahim Portal');

        $html = $this->actingAs($this->karimUser)->ajax()->get(route('investor.investments.table'))->assertOk()->json('data.html');
        $karimAgreement = InvestorFlatAgreement::query()->where('investor_id', $this->karim->id)->firstOrFail();
        $rahimAgreements = InvestorFlatAgreement::query()->where('investor_id', $this->rahim->id)->get();
        $this->assertStringContainsString($karimAgreement->agreement_no, $html);
        foreach ($rahimAgreements as $a) {
            $this->assertStringNotContainsString($a->agreement_no, $html);
            $this->flushHeaders();
            $this->actingAs($this->karimUser)->get(route('investor.investments.show', $a->public_id))->assertNotFound();
        }
        $this->flushHeaders();
        $page = $this->actingAs($this->karimUser)->get(route('investor.investments.show', $karimAgreement->public_id))->assertOk();
        $page->assertSee('13,20,000')->assertSee('Approved')->assertDontSee('Rahim Portal');
        // Category breakdown needs a grant; totals are shown without one.
        $page->assertSee('Shared cost')->assertDontSee('Contractor</dt>', false);
    }

    public function test_documents_statements_and_files_never_cross_investors(): void
    {
        $this->approvedProfit();
        $rahimCapital = InvestorCapitalTransaction::query()->where('investor_id', $this->rahim->id)->firstOrFail();
        $karimCapital = InvestorCapitalTransaction::query()->where('investor_id', $this->karim->id)->firstOrFail();
        $dist = app(DistributionService::class);
        $d = $dist->request($this->rahim, $this->flat, '1000', $this->maker, (string) Str::uuid());
        $dist->approve($d, $this->checker);
        $rahimPayout = $dist->pay($d, ['paid_at' => now()->toDateString(), 'payment_method' => 'cash'], $this->maker, (string) Str::uuid());

        $this->actingAs($this->karimUser)->get(route('investor.documents.capital', $karimCapital->public_id).'?format=print')->assertOk()->assertSee($karimCapital->transaction_no);
        $this->actingAs($this->karimUser)->get(route('investor.documents.capital', $rahimCapital->public_id))->assertNotFound();
        $this->actingAs($this->karimUser)->get(route('investor.documents.payout', $rahimPayout->public_id))->assertNotFound();
        $this->actingAs($this->rahimUser)->get(route('investor.documents.payout', $rahimPayout->public_id).'?format=print')->assertOk();
        $this->assertSame(2, PortalAccessAudit::query()->where('action', 'download')->count());

        $rows = $this->actingAs($this->karimUser)->ajax()->get(route('investor.statements.table'))->assertOk()->json('data.html');
        $this->assertStringContainsString($karimCapital->transaction_no, $rows);
        $this->assertStringNotContainsString($rahimCapital->transaction_no, $rows);
        $this->flushHeaders();
        $csv = $this->actingAs($this->karimUser)->get(route('investor.statements.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString($karimCapital->transaction_no, $csv);
        $this->assertStringNotContainsString($rahimCapital->transaction_no, $csv);
        $this->actingAs($this->karimUser)->ajax()->get(route('investor.statements.table', ['flat' => $this->other->public_id]))->assertNotFound();

        $agreement = InvestorFlatAgreement::query()->where('investor_id', $this->karim->id)->firstOrFail();
        Storage::fake('private');
        $shared = $this->file($agreement, true);
        $hidden = $this->file($agreement, false);
        $foreign = $this->file(InvestorFlatAgreement::query()->where('investor_id', $this->rahim->id)->firstOrFail(), true);
        $this->flushHeaders();
        $this->actingAs($this->karimUser)->get(route('investor.documents.file', $shared->public_id))->assertOk();
        $this->actingAs($this->karimUser)->get(route('investor.documents.file', $hidden->public_id))->assertNotFound();
        $this->actingAs($this->karimUser)->get(route('investor.documents.file', $foreign->public_id))->assertNotFound();
        $this->actingAs($this->karimUser)->get(route('investor.documents.index'))->assertOk()->assertSee($shared->original_name)->assertDontSee($foreign->original_name);
    }

    public function test_profit_states_are_labelled_and_grants_reveal_categories(): void
    {
        $profit = app(ProfitService::class);
        $c = $profit->calculate($this->flat, ['as_of' => now()->toDateString()], $this->maker);
        $agreement = InvestorFlatAgreement::query()->where('investor_id', $this->karim->id)->firstOrFail();
        $this->actingAs($this->karimUser)->get(route('investor.investments.show', $agreement->public_id))->assertOk()->assertSee('Estimated Profit')->assertSee('13,20,000');
        $profit->submit($c, $this->maker);
        $this->actingAs($this->karimUser)->get(route('investor.investments.show', $agreement->public_id))->assertSee('Waiting for Approval')->assertSee('Pending Approval');

        $link = $this->karimUser->investorLinks()->firstOrFail();
        app(PortalAccountService::class)->grant($link, $this->flat, 'investor.portal.cost.contractor.view', null, $this->maker);
        $this->actingAs($this->karimUser)->get(route('investor.investments.show', $agreement->public_id))->assertSee('Contractor</dt>', false)->assertDontSee('Material</dt>', false);

        $this->karimUser->roles->first()->revokePermissionTo('investor.portal.profit.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->karimUser->fresh())->get(route('investor.investments.show', $agreement->public_id))->assertOk()->assertDontSee('Waiting for Approval');
        $this->actingAs($this->karimUser->fresh())->get(route('investor.dashboard'))->assertOk()->assertDontSee('Profit Paid');
    }

    private function file(InvestorFlatAgreement $agreement, bool $visible): Attachment
    {
        Storage::disk('private')->put($path = 'x/'.Str::random(8).'.pdf', 'pdf');
        $a = new Attachment(['original_name' => 'doc-'.Str::random(5).'.pdf', 'disk' => 'private', 'path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 3, 'uploaded_by' => $this->maker->id]);
        $a->attachable()->associate($agreement);
        $a->portal_visible = $visible;
        $a->save();

        return $a;
    }

    public function test_project_progress_and_financials_follow_publication_and_grants(): void
    {
        $project = $this->flat->project;
        $update = fn (string $note, string $visibility, ?string $status = 'approved') => ConstructionProgressUpdate::query()->forceCreate([
            'public_id' => Str::lower(Str::random(16)), 'project_id' => $project->id, 'progress_percent' => 40, 'report_date' => now()->toDateString(), 'notes' => $note,
            'visibility' => $visibility, 'status' => $status, 'submitted_by' => $this->maker->id, 'published_at' => $visibility === 'investor' && $status === 'approved' ? now() : null,
        ]);
        $published = $update('Roof slab cast', 'investor');
        $update('Internal note about the contractor', 'internal');
        $update('Draft for investors', 'investor', 'submitted');

        $this->actingAs($this->karimUser)->get(route('investor.projects.index'))->assertOk()->assertSee($project->name);
        $this->actingAs($this->karimUser)->get(route('investor.projects.show', $project->public_id))->assertOk()
            ->assertSee('Roof slab cast')->assertDontSee('Internal note')->assertDontSee('Draft for investors')->assertDontSee('Financials</a>', false);
        $foreign = Project::factory()->create();
        $this->actingAs($this->karimUser)->get(route('investor.projects.show', $foreign->public_id))->assertNotFound();

        Storage::fake('private');
        Storage::disk('private')->put('p/roof.jpg', 'img');
        $photo = new Attachment(['original_name' => 'roof.jpg', 'disk' => 'private', 'path' => 'p/roof.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 3, 'uploaded_by' => $this->maker->id]);
        $photo->attachable()->associate($published);
        $photo->save();
        $this->actingAs($this->karimUser)->get(route('investor.projects.photo', $photo->public_id))->assertOk();
        $hidden = $photo->replicate(['public_id']);
        $hidden->attachable()->associate(ConstructionProgressUpdate::query()->where('visibility', 'internal')->firstOrFail());
        $hidden->save();
        $this->actingAs($this->karimUser)->get(route('investor.projects.photo', $hidden->public_id))->assertNotFound();

        // Project-wide cost needs a grant on the project, then per category.
        $this->actingAs($this->karimUser)->get(route('investor.projects.financials', $project->public_id))->assertForbidden();
        $link = $this->karimUser->investorLinks()->firstOrFail();
        $accounts = app(PortalAccountService::class);
        $grant = $accounts->grant($link, $project, 'investor.portal.project.financials.view', null, $this->maker);
        $this->actingAs($this->karimUser)->get(route('investor.projects.financials', $project->public_id))->assertOk()->assertSee('No cost categories shared');
        $accounts->grant($link, $project, 'investor.portal.cost.material.view', null, $this->maker);
        $this->actingAs($this->karimUser)->get(route('investor.projects.financials', $project->public_id))->assertOk()
            ->assertSee('Material')->assertSee('10,00,000')->assertDontSee('Contractor')->assertDontSee('6,00,000');
        $this->actingAs($this->rahimUser)->get(route('investor.projects.financials', $project->public_id))->assertForbidden();

        $accounts->revokeGrant($grant, $this->maker);
        $this->actingAs($this->karimUser)->get(route('investor.projects.financials', $project->public_id))->assertForbidden();
    }
}

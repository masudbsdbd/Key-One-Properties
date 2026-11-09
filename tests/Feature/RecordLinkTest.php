<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Flat;
use App\Models\Project;
use App\Support\RecordLink;
use Database\Factories\FlatFactory;
use Tests\TestCase;

class RecordLinkTest extends TestCase
{
    public function test_links_follow_permissions_and_project_visibility(): void
    {
        [$mine, $other] = [Project::factory()->create(), Project::factory()->create()];
        $flatMine = Flat::factory()->forFloor(FlatFactory::floorFor($mine))->create();
        $flatOther = Flat::factory()->forFloor(FlatFactory::floorFor($other))->create();

        $admin = $this->superAdmin();
        $this->assertSame(route('projects.show', $other), RecordLink::url($other, $admin));
        $this->assertSame(route('flats.show', $flatOther), RecordLink::url($flatOther, $admin));

        $member = $this->userWith(['projects.view', 'flats.view']);
        $mine->members()->attach($member->id);
        $this->assertSame(route('flats.show', $flatMine), RecordLink::url($flatMine, $member));
        $this->assertNull(RecordLink::url($flatOther, $member));
        $this->assertNull(RecordLink::url($other, $member));

        $noFlats = $this->userWith(['projects.view_all']);
        $this->assertNull(RecordLink::url($flatMine, $noFlats));
        $this->assertNotNull(RecordLink::url($mine, $noFlats));
    }

    public function test_customer_links_respect_ownership_and_partial_loads_stay_plain(): void
    {
        $owner = $this->userWith(['customers.view']);
        $own = Customer::factory()->create(['created_by' => $owner->id]);
        $foreign = Customer::factory()->create();
        $this->assertSame(route('customers.show', $own), RecordLink::url($own, $owner));
        $this->assertNull(RecordLink::url($foreign, $owner));

        $partial = Project::query()->select('id', 'code')->findOrFail(Project::factory()->create()->id);
        $this->assertNull(RecordLink::url($partial, $this->superAdmin()));
        $this->assertNull(RecordLink::url(null, $owner));
    }

    public function test_component_renders_a_link_or_plain_text(): void
    {
        $project = Project::factory()->create();
        $this->actingAs($this->superAdmin());
        $this->assertStringContainsString('href="'.route('projects.show', $project).'"', $this->blade('<x-ref :to="$p">{{ $p->code }}</x-ref>', ['p' => $project])->__toString());

        $this->actingAs($this->userWith([]));
        $html = $this->blade('<x-ref :to="$p" class="font-medium">{{ $p->code }}</x-ref>', ['p' => $project])->__toString();
        $this->assertStringNotContainsString('href', $html);
        $this->assertStringContainsString('<span class="font-medium">'.$project->code.'</span>', $html);
    }
}

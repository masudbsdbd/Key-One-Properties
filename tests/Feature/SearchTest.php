<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use Tests\TestCase;

class SearchTest extends TestCase
{
    public function test_search_returns_only_records_the_user_may_see(): void
    {
        $user = $this->userWith(['projects.view', 'customers.view']);
        $visible = Project::factory()->create(['name' => 'Lake Garden']);
        $visible->members()->attach($user->id);
        Project::factory()->create(['name' => 'Lake Secret']);
        Customer::factory()->create(['full_name' => 'Lake Customer', 'created_by' => $user->id]);
        Customer::factory()->create(['full_name' => 'Lake Hidden']);

        $this->actingAs($user)->get(route('search', ['q' => 'Lake']))
            ->assertOk()
            ->assertSee('Lake Garden')->assertSee('Lake Customer')
            ->assertDontSee('Lake Secret')->assertDontSee('Lake Hidden');
    }

    public function test_customer_found_by_mobile_in_any_format(): void
    {
        $user = $this->userWith(['customers.view_all']);
        Customer::factory()->create(['full_name' => 'Phone Match', 'primary_mobile' => '01712345678']);

        $this->actingAs($user)->get(route('search', ['q' => '+880 1712-345678']))->assertSee('Phone Match');
    }
}

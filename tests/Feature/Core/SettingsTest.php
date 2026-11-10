<?php

namespace Tests\Feature\Core;

use App\Models\Customer;
use App\Support\Branding;
use App\Support\PageSize;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge(['app_name' => 'Key One', 'primary_color' => '#0F6B52', 'sidebar_color' => '#13254b'], $overrides);
    }

    public function test_requires_settings_permission(): void
    {
        $actor = $this->userWith(['dashboard.view']);

        $this->actingAs($actor)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($actor)->ajax()->put(route('settings.branding'), $this->payload())->assertForbidden();
    }

    public function test_primary_color_change_applies_to_layout(): void
    {
        $actor = $this->userWith(['settings.manage']);

        $this->actingAs($actor)->ajax()->put(route('settings.branding'), $this->payload())->assertOk();

        $this->assertSame('#0f6b52', app(Branding::class)->primaryColor());
        $this->actingAs($actor)->get(route('settings.edit'))->assertSee('--brand-primary: #0f6b52', false);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'event' => 'branding_updated']);
    }

    public function test_invalid_color_cannot_inject_css(): void
    {
        $this->actingAs($this->userWith(['settings.manage']))->ajax()
            ->put(route('settings.branding'), $this->payload(['primary_color' => 'red;}body{display:none']))
            ->assertStatus(422)->assertJsonValidationErrors('primary_color');
    }

    public function test_logo_upload_stored_with_random_name_and_svg_rejected(): void
    {
        Storage::fake('public');
        $actor = $this->userWith(['settings.manage']);

        $this->actingAs($actor)->ajax()->put(route('settings.branding'), $this->payload([
            'logo' => UploadedFile::fake()->image('company-logo.png', 200, 200),
        ]))->assertOk();

        $files = Storage::disk('public')->files('branding');
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('company-logo', $files[0]);

        $this->actingAs($actor)->ajax()->put(route('settings.branding'), $this->payload([
            'logo' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"></svg>'),
        ]))->assertStatus(422);
    }

    public function test_rows_per_page_follow_settings(): void
    {
        $admin = $this->userWith(['settings.manage', 'customers.view', 'customers.view_all']);
        Customer::factory()->count(12)->create();
        $rows = fn (array $query = []) => $this->actingAs($admin)->ajax()->get(route('customers.table', $query))->assertOk();

        // Out of the box: 10 a page, any of the default sizes on request, anything else falls back.
        $rows()->assertJsonPath('meta.to', 10)->assertJsonPath('meta.last_page', 2);
        $rows(['per_page' => 25])->assertJsonPath('meta.to', 12);
        $rows(['per_page' => 7])->assertJsonPath('meta.to', 10);
        $rows(['per_page' => 999999])->assertJsonPath('meta.to', 10);
        $this->actingAs($admin)->get(route('customers.index'))->assertOk()->assertSee('name="per_page"', false)->assertSee('<option value="3000"', false);

        $save = fn (array $data) => $this->actingAs($admin)->ajax()->put(route('settings.tables'), $data);
        $save(['page_sizes' => '5, 20', 'default_page_size' => '10'])->assertStatus(422)->assertJsonValidationErrors('default_page_size');
        $save(['page_sizes' => '5, abc', 'default_page_size' => '5'])->assertStatus(422)->assertJsonValidationErrors('page_sizes');
        $save(['page_sizes' => '5, 9000', 'default_page_size' => '5'])->assertStatus(422)->assertJsonValidationErrors('page_sizes');
        $save(['page_sizes' => '', 'default_page_size' => '5'])->assertStatus(422)->assertJsonValidationErrors('page_sizes');
        $this->actingAs($this->userWith(['customers.view']))->ajax()->put(route('settings.tables'), ['page_sizes' => '5', 'default_page_size' => '5'])->assertForbidden();

        $save(['page_sizes' => '20, 5', 'default_page_size' => '5'])->assertOk();
        $this->assertSame([5, 20], PageSize::options());
        $rows()->assertJsonPath('meta.to', 5);
        $rows(['per_page' => 20])->assertJsonPath('meta.to', 12);
        $rows(['per_page' => 25])->assertJsonPath('meta.to', 5);
        $this->actingAs($admin)->get(route('customers.index'))->assertOk()->assertSee('<option value="20"', false)->assertDontSee('<option value="3000"', false);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'event' => 'tables_updated']);
    }
}

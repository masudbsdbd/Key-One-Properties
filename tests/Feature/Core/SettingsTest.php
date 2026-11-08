<?php

namespace Tests\Feature\Core;

use App\Support\Branding;
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
}

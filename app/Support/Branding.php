<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Branding
{
    public const DEFAULT_COLOR = '#b8862b';

    public const DEFAULT_SIDEBAR_COLOR = '#13254b';

    public function __construct(private Settings $settings) {}

    public function name(): string
    {
        return $this->settings->get('branding.app_name') ?: config('app.name');
    }

    public function tagline(): ?string
    {
        return $this->settings->get('branding.tagline');
    }

    public function primaryColor(): string
    {
        $color = (string) $this->settings->get('branding.primary_color');

        return self::isValidColor($color) ? strtolower($color) : self::DEFAULT_COLOR;
    }

    public function sidebarColor(): string
    {
        $color = (string) $this->settings->get('branding.sidebar_color');

        return self::isValidColor($color) ? strtolower($color) : self::DEFAULT_SIDEBAR_COLOR;
    }

    public function logoUrl(): ?string
    {
        return $this->publicUrl('branding.logo_path');
    }

    /** Local file path of the logo, for PDF rendering. */
    public function logoPath(): ?string
    {
        $path = $this->settings->get('branding.logo_path');

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->publicUrl('branding.favicon_path');
    }

    public static function isValidColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
    }

    private function publicUrl(string $key): ?string
    {
        $path = $this->settings->get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}

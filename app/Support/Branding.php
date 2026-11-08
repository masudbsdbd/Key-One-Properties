<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Branding
{
    public const DEFAULT_COLOR = '#0f6b52';

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

    public function logoUrl(): ?string
    {
        return $this->publicUrl('branding.logo_path');
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

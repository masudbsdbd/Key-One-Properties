<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Settings
{
    private const CACHE_KEY = 'app.settings';

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            return $this->values = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Schema::hasTable('settings')
                    ? Setting::query()->pluck('value', 'key')->all()
                    : [],
            );
        } catch (Throwable) {
            return $this->values = [];
        }
    }
}

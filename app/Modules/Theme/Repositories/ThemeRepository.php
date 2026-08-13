<?php

namespace App\Modules\Theme\Repositories;

use App\Modules\Theme\Models\ThemeSetting;
use Illuminate\Support\Collection;

class ThemeRepository
{
    public function get(string $key, mixed $default = null): mixed
    {
        return ThemeSetting::where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, mixed $value): ThemeSetting
    {
        return ThemeSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public function all(): Collection
    {
        return ThemeSetting::query()
            ->orderBy('key')
            ->pluck('value', 'key');
    }
}

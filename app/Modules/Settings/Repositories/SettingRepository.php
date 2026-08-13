<?php

namespace App\Modules\Settings\Repositories;

use App\Modules\Settings\Models\Setting;

class SettingRepository
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('setting_group', $group)
            ->where('setting_key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    public function set(string $group, string $key, mixed $value, string $dataType = 'string', bool $isPublic = false): Setting
    {
        return Setting::updateOrCreate(
            ['setting_group' => $group, 'setting_key' => $key],
            [
                'value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
                'data_type' => $dataType,
                'is_public' => $isPublic,
            ]
        );
    }

    public function has(string $group, string $key): bool
    {
        return Setting::where('setting_group', $group)
            ->where('setting_key', $key)
            ->exists();
    }

    public function remove(string $group, string $key): bool
    {
        return Setting::where('setting_group', $group)
            ->where('setting_key', $key)
            ->delete() > 0;
    }

    public function getGroup(string $group): array
    {
        return Setting::where('setting_group', $group)
            ->get()
            ->pluck('value', 'setting_key')
            ->toArray();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = self::query()->where('key', $key)->first();

        if ($record === null || $record->value === null) {
            return $default;
        }

        return $record->value;
    }

    public static function setValue(string $key, mixed $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}

<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = ['key', 'value', 'group', 'type'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $value = static::where('key', $key)->value('value');

        return $value === null ? $default : $value;
    }

    public static function isEnabled(string $key, bool $default = true): bool
    {
        $value = static::getValue($key, $default ? '1' : '0');

        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}

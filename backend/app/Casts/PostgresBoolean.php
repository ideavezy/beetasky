<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;

/**
 * Custom cast for PostgreSQL boolean columns.
 * Ensures proper boolean handling for PostgreSQL which requires
 * explicit boolean values rather than 0/1 integers.
 */
class PostgresBoolean implements CastsAttributes
{
    /**
     * Cast the given value.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return (bool) $value;
    }

    /**
     * Prepare the given value for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): Expression
    {
        $boolValue = $value ? 'true' : 'false';
        return new Expression($boolValue);
    }
}


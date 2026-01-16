<?php

declare(strict_types=1);

namespace Database\Traits;

use Helpers\String\Str;

trait HasRefid
{
    /**
     * Boot the trait to add automatic refid generation.
     */
    public static function bootHasRefid(): void
    {
        static::creating(function ($model) {
            if (empty($model->refid)) {
                $prefix = property_exists($model, 'refidPrefix') ? $model->refidPrefix : '';
                $model->refid = $prefix . Str::refid();
            }
        });
    }

    public static function findByRefid(string $refid): ?self
    {
        return static::where('refid', $refid)->first();
    }
}

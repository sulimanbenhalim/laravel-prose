<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string describe(Builder $builder)
 *
 * @see \SulimanBenhalim\Prose\Prose
 */
class Prose extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'prose';
    }

    public static function describe(Builder $builder): string
    {
        return static::getFacadeRoot()->describe($builder);
    }
}

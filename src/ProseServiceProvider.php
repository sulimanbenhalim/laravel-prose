<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\ServiceProvider;

class ProseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/prose.php',
            'prose'
        );

        $this->app->singleton('prose', function ($app) {
            return new Prose($app['config']['prose']);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/prose.php' => config_path('prose.php'),
        ], 'prose-config');

        $this->registerMacro();
    }

    private function registerMacro(): void
    {
        EloquentBuilder::macro('describe', function (): string {
            return app('prose')->describe($this);
        });

        EloquentBuilder::macro('describeCount', function (): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'count', 'columns' => ['*']];

            return app('prose')->describe($cloned);
        });

        EloquentBuilder::macro('describeSum', function (string $column): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'sum', 'columns' => [$column]];

            return app('prose')->describe($cloned);
        });

        EloquentBuilder::macro('describeAvg', function (string $column): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'avg', 'columns' => [$column]];

            return app('prose')->describe($cloned);
        });

        EloquentBuilder::macro('describeAverage', function (string $column): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'avg', 'columns' => [$column]];

            return app('prose')->describe($cloned);
        });

        EloquentBuilder::macro('describeMax', function (string $column): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'max', 'columns' => [$column]];

            return app('prose')->describe($cloned);
        });

        EloquentBuilder::macro('describeMin', function (string $column): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'min', 'columns' => [$column]];

            return app('prose')->describe($cloned);
        });

        EloquentBuilder::macro('describeUpdate', function (array $values = []): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'update', 'columns' => ['*'], 'values' => $values];

            return app('prose')->describe($cloned);
        });

        EloquentBuilder::macro('describeDelete', function (): string {
            $cloned = clone $this;
            $cloned->getQuery()->aggregate = ['function' => 'delete', 'columns' => ['*']];

            return app('prose')->describe($cloned);
        });
    }

    public function provides(): array
    {
        return ['prose'];
    }
}

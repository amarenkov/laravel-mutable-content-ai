<?php

namespace Amarenkov\MutableContentAi;

use Illuminate\Support\ServiceProvider;

use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContentAi\Lovs\MemoryKind;
use Amarenkov\MutableContentAi\Lovs\RunStatus;
use Amarenkov\MutableContentAi\Models\Memory;
use Amarenkov\MutableContentAi\Models\Run;

class MutableContentAiServiceProvider extends ServiceProvider
{
    // public
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mutable-content-ai.php', 'mutable-content-ai');

        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->callAfterResolving(MutableClassRegistry::class, function (MutableClassRegistry $registry) {
            $registry
                ->add(Memory::class)
                ->add(Run::class);
        });

        $this->callAfterResolving(LovRegistry::class, function (LovRegistry $registry) {
            $registry
                ->addClass(MemoryKind::class)
                ->addClass(RunStatus::class);
        });
    }

    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'mutable-content-ai-migrations');

        $this->publishes([
            __DIR__.'/../config/mutable-content-ai.php' => config_path('mutable-content-ai.php'),
        ], 'mutable-content-ai-config');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mutable-content-ai');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/mutable-content-ai'),
        ], 'mutable-content-ai-lang');
    }
}

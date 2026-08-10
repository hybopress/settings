<?php

namespace HBP\Settings\Providers;

use HBP\Settings\Contracts\ObjectMeta;
use HBP\Settings\Meta\WordPressMeta;
use HBP\Settings\SettingsFactory;
use Hybrid\Contracts\Core\DeferrableProvider;
use Hybrid\Core\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider implements DeferrableProvider {
    public function register(): void {
        $this->app->singleton( ObjectMeta::class, WordPressMeta::class );

        $this->app->singleton(
            SettingsFactory::class,
            static fn( $app ) => new SettingsFactory(
                $app->make( 'config' ),
                $app->make( ObjectMeta::class )
            )
        );
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array {
        return [ SettingsFactory::class, ObjectMeta::class ];
    }
}

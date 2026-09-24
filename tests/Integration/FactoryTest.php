<?php

declare(strict_types = 1);

use HBP\Settings\Meta\WordPressMeta;
use HBP\Settings\Settings;
use HBP\Settings\SettingsFactory;
use Hybrid\Tools\Config\Repository as Config;

function factory( array $config = [] ): SettingsFactory {
    return new SettingsFactory( new Config( $config ), new WordPressMeta );
}

it( 'memoises one instance per namespace', function (): void {
    $factory = factory();

    expect( $factory->make( 'child' ) )->toBe( $factory->make( 'child' ) );
} );

it( 'keeps namespaces apart', function (): void {
    $factory = factory();

    expect( $factory->make( 'child' ) )->not->toBe( $factory->make( 'myplugin' ) );
} );

it( 'defaults the option name to the namespace', function (): void {
    factory()->make( 'child' )->set( 'site.title', 'x' );

    expect( get_option( 'child_settings' ) )->toBeArray();
} );

it( 'accepts an explicit option name', function (): void {
    factory()->make( 'myplugin', 'my_option' )->set( 'feature.on', true );

    expect( get_option( 'my_option' ) )->toBeArray();
} );

it( 'builds Settings instances', function (): void {
    expect( factory()->make( 'child' ) )->toBeInstanceOf( Settings::class );
} );

it( 'memoises features per namespace', function (): void {
    $factory = factory();

    expect( $factory->features( 'child' ) )->toBe( $factory->features( 'child' ) );
} );

it( 'rejects an empty namespace', function (): void {
    expect( fn() => factory()->make( '  ' ) )->toThrow( InvalidArgumentException::class );
} );

it( 'reads presets and features from the paths a namespace sets', function (): void {
    $factory = factory( [
        'child' => [
            'site' => [
                'features' => [ 'gallery' => [ 'slider' => true ] ],
                'presets'  => [ 'active' => 'one', 'one' => [ 'title' => 'from preset' ] ],
            ],
        ],
    ] );

    $before = $factory->make( 'child' );
    $factory->paths( 'child', [ 'presets' => 'site.presets', 'features' => 'site.features' ] );

    expect( $factory->make( 'child' ) )->not->toBe( $before )
        ->and( $factory->make( 'child' )->get( 'title' ) )->toBe( 'from preset' )
        ->and( $factory->features( 'child' )->enabled( 'gallery.slider' ) )->toBeTrue();
} );
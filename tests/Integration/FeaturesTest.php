<?php

declare(strict_types = 1);

use HBP\Settings\Features;
use Hybrid\Tools\Config\Repository as Config;

/**
 * @param array<string, mixed> $values
 */
function features( array $values ): Features {
    return new Features( new Config( child( $values ) ), 'child' );
}

it( 'reads a declared default', function (): void {
    expect( features( [ 'features' => [ 'gallery' => [ 'slider' => true ] ] ] )->enabled( 'gallery.slider' ) )
        ->toBeTrue();
} );

/**
 * An undeclared capability is off, so a typo fails loudly rather than
 * silently switching something on.
 */
it( 'treats an undeclared capability as off', function (): void {
    expect( features( [] )->enabled( 'gallery.nope' ) )->toBeFalse();
} );

it( 'lets the active preset override a default', function (): void {
    $features = features( [
        'features' => [ 'gallery' => [ 'slider' => true ] ],
        'presets'  => [
            'active'  => 'minimal',
            'minimal' => [ 'features' => [ 'gallery' => [ 'slider' => false ] ] ],
        ],
    ] );

    expect( $features->enabled( 'gallery.slider' ) )->toBeFalse();
} );

it( 'accepts preset overrides written with flat dotted keys', function (): void {
    $features = features( [
        'features' => [ 'gallery' => [ 'slider' => true ] ],
        'presets'  => [
            'active'  => 'minimal',
            'minimal' => [ 'features' => [ 'gallery.slider' => false ] ],
        ],
    ] );

    expect( $features->enabled( 'gallery.slider' ) )->toBeFalse();
} );

it( 'can turn a capability on that defaults to off', function (): void {
    $features = features( [
        'features' => [ 'social' => false ],
        'presets'  => [
            'active' => 'rich',
            'rich'   => [ 'features' => [ 'social' => true ] ],
        ],
    ] );

    expect( $features->enabled( 'social' ) )->toBeTrue();
} );

it( 'ignores overrides from a preset that is not active', function (): void {
    $features = features( [
        'features' => [ 'social' => true ],
        'presets'  => [
            'active'  => 'rich',
            'minimal' => [ 'features' => [ 'social' => false ] ],
        ],
    ] );

    expect( $features->enabled( 'social' ) )->toBeTrue();
} );

/**
 * A declared default may be a closure, so an expensive or context-dependent
 * default is only paid for when it is reached.
 *
 * A bare cast reads a closure as an object, which is truthy, so every closure
 * default would report enabled whatever it returns. This is that regression.
 */
it( 'resolves a closure declared default', function (): void {
    $features = features( [ 'features' => [ 'social' => static fn(): bool => false ] ] );

    expect( $features->enabled( 'social' ) )->toBeFalse();
} );

it( 'resolves a closure declared default that is on', function (): void {
    $features = features( [ 'features' => [ 'social' => static fn(): bool => true ] ] );

    expect( $features->enabled( 'social' ) )->toBeTrue();
} );

/**
 * The same hazard one tier up: preset overrides are flattened and cast, so a
 * closure override has to resolve before the cast too.
 */
it( 'resolves a closure preset override', function (): void {
    $features = features( [
        'features' => [ 'social' => true ],
        'presets'  => [
            'active' => 'minimal',
            'minimal' => [ 'features' => [ 'social' => static fn(): bool => false ] ],
        ],
    ] );

    expect( $features->enabled( 'social' ) )->toBeFalse();
} );

it( 'resolves closure defaults through all()', function (): void {
    $features = features( [
        'features' => [
            'social'  => static fn(): bool => false,
            'gallery' => true,
        ],
    ] );

    expect( $features->all() )->toBe( [
        'social'  => false,
        'gallery' => true,
    ] );
} );

it( 'reports disabled as the inverse of enabled', function (): void {
    $features = features( [ 'features' => [ 'social' => false ] ] );

    expect( $features->disabled( 'social' ) )->toBeTrue();
} );

it( 'lists every declared capability with its effective state', function (): void {
    $features = features( [
        'features' => [
            'gallery' => [
                'slider'  => true,
                'masonry' => true,
            ],
            'social'  => false,
        ],
        'presets'  => [
            'active'  => 'minimal',
            'minimal' => [ 'features' => [ 'gallery' => [ 'slider' => false ] ] ],
        ],
    ] );

    expect( $features->all() )->toBe( [
        'gallery.slider'  => false,
        'gallery.masonry' => true,
        'social'          => false,
    ] );
} );

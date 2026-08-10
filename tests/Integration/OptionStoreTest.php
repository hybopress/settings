<?php

declare(strict_types = 1);

use HBP\Settings\Store\OptionStore;

it( 'reports success when the value is already correct', function (): void {
    $store = new OptionStore( 'child_settings' );

    expect( $store->put( 'site.title', 'Same' ) )->toBeTrue()
        ->and( $store->put( 'site.title', 'Same' ) )->toBeTrue();
} );

/**
 * update_option() returns false for an unchanged value, which is not a
 * failure. Without this the second save of an identical value reports an
 * error to the user.
 */
it( 'does not mistake an unchanged write for a failure', function (): void {
    $store = new OptionStore( 'child_settings' );
    $store->put( 'site.title', 'Same' );
    $store->flush();

    expect( $store->put( 'site.title', 'Same' ) )->toBeTrue();
} );

it( 'drops the read cache when a write fails', function (): void {
    $store = new OptionStore( 'child_settings' );
    $store->put( 'site.title', 'First' );

    forceWriteFailure();

    expect( $store->put( 'site.title', 'Second' ) )->toBeFalse()
        ->and( $store->get( 'site.title' ) )->toBe( 'First' );
} );

it( 'stores dotted keys as a nested array', function (): void {
    $store = new OptionStore( 'child_settings' );
    $store->put( 'site.identity.logo', 'x.png' );

    expect( get_option( 'child_settings' ) )
        ->toBe( [ 'site' => [ 'identity' => [ 'logo' => 'x.png' ] ] ] );
} );

it( 'forgets a key', function (): void {
    $store = new OptionStore( 'child_settings' );
    $store->put( 'site.title', 'x' );
    $store->forget( 'site.title' );

    expect( $store->has( 'site.title' ) )->toBeFalse();
} );

it( 'treats forgetting an absent key as success', function (): void {
    expect( ( new OptionStore( 'child_settings' ) )->forget( 'nope' ) )->toBeTrue();
} );

it( 'survives an option holding a non-array', function (): void {
    update_option( 'child_settings', 'corrupt' );

    expect( ( new OptionStore( 'child_settings' ) )->all() )->toBe( [] );
} );

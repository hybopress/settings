<?php

declare(strict_types = 1);

use HBP\Settings\Meta\Target;
use function PestWP\createPost;
use function PestWP\createTerm;

// ---------------------------------------------------------------
// Precedence
// ---------------------------------------------------------------

it( 'falls back to Hybrid config', function (): void {
    $settings = settings( child( [ 'site' => [ 'title' => 'From config' ] ] ) );

    expect( $settings->get( 'site.title' ) )->toBe( 'From config' );
} );

it( 'prefers a preset over config', function (): void {
    $settings = settings( child( [
        'site'    => [ 'title' => 'From config' ],
        'presets' => [
            'active'  => 'classic',
            'classic' => [ 'site.title' => 'From preset' ],
        ],
    ] ) );

    expect( $settings->get( 'site.title' ) )->toBe( 'From preset' );
} );

it( 'reads presets written as nested arrays', function (): void {
    $settings = settings( child( [
        'presets' => [
            'active'  => 'classic',
            'classic' => [ 'site' => [ 'title' => 'Nested' ] ],
        ],
    ] ) );

    expect( $settings->get( 'site.title' ) )->toBe( 'Nested' );
} );

it( 'ignores presets that are not active', function (): void {
    $settings = settings( child( [
        'site'    => [ 'title' => 'From config' ],
        'presets' => [
            'active' => 'classic',
            'modern' => [ 'site.title' => 'Wrong preset' ],
        ],
    ] ) );

    expect( $settings->get( 'site.title' ) )->toBe( 'From config' );
} );

it( 'prefers the stored option over preset and config', function (): void {
    $settings = settings( child( [
        'site'    => [ 'title' => 'From config' ],
        'presets' => [
            'active'  => 'classic',
            'classic' => [ 'site.title' => 'From preset' ],
        ],
    ] ) );

    $settings->set( 'site.title', 'From option' );

    expect( $settings->get( 'site.title' ) )->toBe( 'From option' );
} );

it( 'uses the caller default only as a last resort', function (): void {
    expect( settings()->get( 'site.title', 'fallback' ) )->toBe( 'fallback' );
} );

/**
 * A stored falsy value is a decision the user made. It must stop the
 * fall-through rather than leaking the tier below.
 */
it( 'does not fall through a stored falsy value', function ( mixed $stored ): void {
    $settings = settings( child( [ 'site' => [ 'title' => 'From config' ] ] ) );

    $settings->set( 'site.title', $stored );

    expect( $settings->get( 'site.title' ) )->toBe( $stored );
} )->with( [
    'false'        => false,
    'zero'         => 0,
    'empty string' => '',
    'null'         => null,
] );

it( 'does not fall through a stored empty array', function (): void {
    $settings = settings( child( [ 'site' => [ 'title' => 'From config' ] ] ) );

    $settings->set( 'site.title', [] );

    expect( $settings->get( 'site.title' ) )->toBe( [] );
} );

// ---------------------------------------------------------------
// Defaults: the regression that motivated the redesign
// ---------------------------------------------------------------

/**
 * The previous implementation inspected the second argument and treated any
 * positive integer as a post ID, so an integer default was impossible to
 * express and silently triggered a meta read instead.
 */
it( 'treats an integer second argument as a default, not a post ID', function (): void {
    expect( settings()->get( 'posts.per_page', 10 ) )->toBe( 10 );
} );

it( 'treats an object second argument as a default', function (): void {
    $default = createPost();

    expect( settings()->get( 'site.title', $default ) )->toBe( $default );
} );

it( 'invokes closure defaults from config', function (): void {
    $settings = settings( child( [ 'site' => [ 'title' => static fn() => 'Lazy' ] ] ) );

    expect( $settings->get( 'site.title' ) )->toBe( 'Lazy' );
} );

// ---------------------------------------------------------------
// Keys
// ---------------------------------------------------------------

it( 'rejects an empty key', function ( string $key ): void {
    expect( fn() => settings()->get( $key ) )->toThrow( InvalidArgumentException::class );
} )->with( [
    'empty'     => '',
    'dot'       => '.',
    'dots only' => '...',
] );

it( 'rejects reserved config roots as setting keys', function ( string $key ): void {
    expect( fn() => settings()->get( $key ) )->toThrow( InvalidArgumentException::class );
} )->with( [
    'presets.active'  => 'presets.active',
    'meta.site.title' => 'meta.site.title',
    'features.x'      => 'features.x',
    'controls.x'      => 'controls.x',
] );

// ---------------------------------------------------------------
// Object meta: opt-in only
// ---------------------------------------------------------------

it( 'never reads meta on an unscoped instance', function (): void {
    $post     = createPost();
    $settings = settings( child( [ 'site' => [ 'title' => 'Global' ] ] ) );

    $settings->for( $post )->set( 'site.title', 'Per post' );

    expect( $settings->get( 'site.title' ) )->toBe( 'Global' )
        ->and( $settings->for( $post )->get( 'site.title' ) )->toBe( 'Per post' );
} );

it( 'prefers meta over the stored option when scoped', function (): void {
    $post     = createPost();
    $settings = settings();

    $settings->set( 'site.title', 'Global' );
    $settings->for( $post )->set( 'site.title', 'Per post' );

    expect( $settings->for( $post )->get( 'site.title' ) )->toBe( 'Per post' );
} );

it( 'returns a new instance when scoped', function (): void {
    $settings = settings();
    $post     = createPost();
    $scoped   = $settings->for( $post );

    expect( $scoped )->not->toBe( $settings )
        ->and( $settings->target() )->toBeNull()
        ->and( $scoped->target()->id )->toBe( $post->ID );
} );

/**
 * A post and a term can share an ID. The meta type must keep them apart.
 */
it( 'scopes posts and terms of the same ID separately', function (): void {
    $settings = settings();
    $id       = createPost()->ID;

    $settings->for( Target::post( $id ) )->set( 'site.title', 'Post value' );
    $settings->for( Target::term( $id ) )->set( 'site.title', 'Term value' );

    expect( $settings->for( Target::post( $id ) )->get( 'site.title' ) )->toBe( 'Post value' )
        ->and( $settings->for( Target::term( $id ) )->get( 'site.title' ) )->toBe( 'Term value' );
} );

it( 'clears only meta when forgetting on a scoped instance', function (): void {
    $post     = createPost();
    $settings = settings();

    $settings->set( 'site.title', 'Global' );
    $settings->for( $post )->set( 'site.title', 'Per post' );
    $settings->for( $post )->forget( 'site.title' );

    expect( $settings->for( $post )->get( 'site.title' ) )->toBe( 'Global' );
} );

it( 'refuses to scope without a meta driver', function (): void {
    expect( fn() => settings( [], withMeta: false )->for( createPost() ) )
        ->toThrow( RuntimeException::class );
} );

it( 'leaves forQueried unscoped outside the loop', function (): void {
    expect( settings()->forQueried()->target() )->toBeNull();
} );

it( 'scopes forQueried to the current post', function (): void {
    $post = createPost();

    $GLOBALS['wp_query']->queried_object    = $post;
    $GLOBALS['wp_query']->queried_object_id = $post->ID;

    expect( settings()->forQueried()->target()->id )->toBe( $post->ID );
} );

it( 'namespaces the default meta key', function (): void {
    expect( settings()->metaKey( 'site.title' ) )->toBe( '_hbp_settings_child_site_title' );
} );

it( 'remaps a meta key from config', function (): void {
    $settings = settings( child( [ 'meta' => [ 'site.title' => '_legacy_title' ] ] ) );

    expect( $settings->metaKey( 'site.title' ) )->toBe( '_legacy_title' );
} );

// ---------------------------------------------------------------
// Targets
// ---------------------------------------------------------------

it( 'rejects a bare integer target as ambiguous', function (): void {
    expect( fn() => Target::from( 7 ) )->toThrow( InvalidArgumentException::class );
} );

it( 'accepts an explicit post target', function (): void {
    expect( Target::post( 7 )->type )->toBe( 'post' );
} );

it( 'rejects a non-positive target ID', function (): void {
    expect( fn() => Target::post( 0 ) )->toThrow( InvalidArgumentException::class );
} );

it( 'builds a target from a WP_Term', function (): void {
    $target = Target::from( get_term( createTerm( 'News' ) ) );

    expect( $target->type )->toBe( 'term' );
} );

it( 'builds a target from a type and id pair', function (): void {
    $target = Target::from( [
        'type' => 'term',
        'id'   => 3,
    ] );

    expect( $target->type )->toBe( 'term' )->and( $target->id )->toBe( 3 );
} );

// ---------------------------------------------------------------
// has() and stored()
// ---------------------------------------------------------------

it( 'reports has() true for a stored falsy value', function (): void {
    $settings = settings();
    $settings->set( 'site.title', false );

    expect( $settings->has( 'site.title' ) )->toBeTrue();
} );

it( 'reports has() false for an unknown key', function (): void {
    expect( settings()->has( 'site.title' ) )->toBeFalse();
} );

it( 'returns only the option tier from stored()', function (): void {
    $settings = settings( child( [ 'site' => [ 'title' => 'From config' ] ] ) );
    $settings->set( 'site.title', 'From option' );

    expect( $settings->stored() )->toBe( [ 'site' => [ 'title' => 'From option' ] ] );
} );

// ---------------------------------------------------------------
// Filters
// ---------------------------------------------------------------

it( 'lets a filter override a resolved value', function (): void {
    add_filter(
        'hbp-settings/child/setting/site.title',
        static fn( $value ) => strtoupper( (string) $value )
    );

    $settings = settings( child( [ 'site' => [ 'title' => 'shout' ] ] ) );

    expect( $settings->get( 'site.title' ) )->toBe( 'SHOUT' );
} );

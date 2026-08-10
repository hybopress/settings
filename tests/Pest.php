<?php

/**
 * Pest bootstrap.
 *
 * PestWP downloads WordPress, sets up SQLite and loads both for us. So these
 * tests run against real WordPress: real options, real metadata, real hooks.
 * Nothing here fakes a WordPress function.
 *
 * Each test runs inside a transaction that is rolled back afterwards, so the
 * database returns to its prior state and tests cannot leak into each other.
 *
 * Hybrid Tools and Hybrid Contracts load through Composer, so the config
 * repository these tests exercise is the real one too.
 */

declare(strict_types = 1);

use HBP\Settings\Meta\WordPressMeta;
use HBP\Settings\Settings;
use HBP\Settings\Store\OptionStore;
use Hybrid\Tools\Config\Repository as Config;
use PestWP\Database\TransactionManager;

uses()
    ->beforeEach( function (): void {
        TransactionManager::beginTransaction();
        resetTestOptions();
    } )
    ->afterEach( fn() => TransactionManager::rollback() )
    ->in( 'Integration' );

const TEST_OPTION = 'child_settings';

/**
 * Clear the options these tests write to.
 *
 * A rollback restores database rows, but WordPress caches option values in
 * process and that cache outlives the transaction. So an option written by
 * one test can still be readable in the next, and a test asserting on the
 * whole stored array then sees a key it never wrote.
 *
 * Deleting through the API drops the row and its cache entry together, so
 * every test starts from a genuinely empty option whichever layer held the
 * stale value.
 */
function resetTestOptions(): void {
    foreach ( [ TEST_OPTION, 'my_option' ] as $option ) {
        delete_option( $option );
        wp_cache_delete( $option, 'options' );
    }

    // Option reads can be served from the alloptions bundle rather than a
    // per-option key, so that has to go too.
    wp_cache_delete( 'alloptions', 'options' );
}

/**
 * A Settings instance for the "child" namespace.
 *
 * @param array<string, mixed> $config
 */
function settings( array $config = [], bool $withMeta = true ): Settings {
    return new Settings(
        new OptionStore( TEST_OPTION ),
        new Config( $config ),
        'child',
        $withMeta ? new WordPressMeta : null
    );
}

/**
 * Config with the given values under the "child" namespace.
 *
 * @param array<string, mixed> $values
 *
 * @return array<string, mixed>
 */
function child( array $values ): array {
    return [ 'child' => $values ];
}

/**
 * Make the NEXT write to the test option fail, and only that one.
 *
 * Returning the stored value from pre_update_option_* makes update_option()
 * report no change, which is the same false a caller sees when a write does
 * not land.
 *
 * The filter removes itself once it has fired. A database rollback restores
 * rows but does not remove hooks, so a filter left in place here would make
 * every later write to this option fail -- in a different test file, with a
 * failure that looks nothing like its cause.
 */
function forceWriteFailure(): void {
    $fail = static function ( $value, $old ) use ( &$fail ) {
        remove_filter( 'pre_update_option_' . TEST_OPTION, $fail, 10 );

        return $old;
    };

    add_filter( 'pre_update_option_' . TEST_OPTION, $fail, 10, 2 );
}

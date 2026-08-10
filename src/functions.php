<?php

/**
 * Helpers.
 *
 * These are namespaced deliberately. The previous global theme_config() and
 * plugin_config() would fatal on redeclare when two HBP-based packages ran
 * on one site, and the function_exists() guard silently handed the name to
 * whichever package loaded first.
 *
 * Consumers who want a short global alias should declare it in their own
 * theme or plugin, where owning the name is their call to make:
 *
 *     function child_setting( string $key, mixed $default = null ): mixed {
 *         return \HBP\Settings\settings( 'child' )->get( $key, $default );
 *     }
 */

namespace HBP\Settings;

use function Hybrid\app;

if ( ! function_exists( __NAMESPACE__ . '\\settings' ) ) {
    /**
     * Resolve the Settings instance for a namespace.
     */
    function settings( string $namespace, ?string $option = null ): Settings {
        return app( SettingsFactory::class )->make( $namespace, $option );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\setting' ) ) {    /**
     * Read a single effective setting.
     *
     * The namespace is explicit. There is no ambient "current" namespace to
     * get wrong.
     */
    function setting( string $namespace, string $key, mixed $default = null ): mixed {
        return settings( $namespace )->get( $key, $default );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\features' ) ) {
    /**
     * The capability set for a namespace.
     */
    function features( string $namespace ): Features {
        return app( SettingsFactory::class )->features( $namespace );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\feature' ) ) {
    /**
     * Whether a capability is enabled.
     *
     * Off means the behaviour does not run, the controls do not render, and
     * the assets are not enqueued. Guard all three with this.
     */
    function feature( string $namespace, string $name ): bool {
        return features( $namespace )->enabled( $name );
    }
}

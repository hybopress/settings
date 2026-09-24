<?php

namespace HBP\Settings;

/**
 * Where a namespace keeps the config this library reads beside its settings.
 *
 * The library looks for presets, features, tabs and sections directly under
 * the namespace by default. A consumer that files them elsewhere, a theme
 * with a `site/` group for instance, says so once through
 * SettingsFactory::paths(), and every reader resolves through this.
 */
final class Paths {
    private const DEFAULTS = [
        'presets'  => 'presets',
        'features' => 'features',
        'tabs'     => 'tabs',
        'sections' => 'sections',
    ];

    /**
     * @param array<string, string> $map Name to path under the namespace.
     */
    public function __construct(
        private readonly string $namespace,
        private readonly array $map = []
    ) {}

    /**
     * The full config key for one of the library's own config groups.
     */
    public function get( string $name ): string {
        return $this->namespace . '.' . trim( $this->map[ $name ] ?? self::DEFAULTS[ $name ] ?? $name, '.' );
    }
}

<?php

namespace HBP\Settings;

use Hybrid\Contracts\Config\Repository as ConfigRepository;
use function Hybrid\Tools\value;

/**
 * Which capabilities this build ships.
 *
 * Off means the behaviour does not run, the controls do not render, and the
 * assets are not enqueued. Guard all three with enabled().
 *
 * Declared capabilities and their defaults live at `{namespace}.features.*`.
 * The active preset states only where it differs, at
 * `{namespace}.presets.{active}.features.*`. So the config file is the list
 * of capabilities, and a preset is a short diff against it rather than a
 * second copy.
 *
 * This is not a settings tier: a capability is a build-time fact, not
 * something a user stores. It never reads or writes the option.
 */
final class Features {
    /** @var array<string, bool>|null */
    private ?array $overrides = null;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly string $namespace
    ) {}

    /**
     * @param string $name Bare capability name, e.g. `gallery.slider`.
     */
    public function enabled( string $name ): bool {
        $overrides = $this->overrides();

        if ( array_key_exists( $name, $overrides ) ) {
            return $overrides[ $name ];
        }

        // Falls through to the declared default rather than a hardcoded true,
        // so a capability nobody declared is off and a typo fails loudly
        // rather than silently enabling something.
        // value() resolves a declared default written as a closure, which a
        // bare cast would read as an object and so always report enabled.
        return (bool) value( $this->config->get( "{$this->namespace}.features.{$name}", false ) );
    }

    public function disabled( string $name ): bool {
        return ! $this->enabled( $name );
    }

    /**
     * Every declared capability and its effective state.
     *
     * @return array<string, bool>
     */
    public function all(): array {
        $declared = (array) $this->config->get( "{$this->namespace}.features", [] );
        $features = [];

        foreach ( array_keys( $this->flatten( $declared ) ) as $name ) {
            $features[ $name ] = $this->enabled( $name );
        }

        return $features;
    }

    /**
     * The active preset's feature overrides.
     *
     * @return array<string, bool>
     */
    private function overrides(): array {
        if ( null === $this->overrides ) {
            $active = $this->config->get( "{$this->namespace}.presets.active" );

            $overrides = is_string( $active ) && '' !== $active
                ? $this->config->get( "{$this->namespace}.presets.{$active}.features", [] )
                : [];

            // value() for the same reason enabled() needs it: a preset
            // override written as a closure is an object to a bare cast, so
            // every such override would read as true.
            $this->overrides = array_map(
                static fn( $value ): bool => (bool) value( $value ),
                $this->flatten( (array) $overrides )
            );
        }

        return $this->overrides;
    }

    /**
     * Flatten nested arrays to dotted keys, so a preset may write features
     * either way round.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function flatten( array $values, string $prefix = '' ): array {
        $flat = [];

        foreach ( $values as $key => $value ) {
            $name = '' === $prefix ? (string) $key : "{$prefix}.{$key}";

            if ( is_array( $value ) ) {
                $flat += $this->flatten( $value, $name );

                continue;
            }

            $flat[ $name ] = $value;
        }

        return $flat;
    }
}

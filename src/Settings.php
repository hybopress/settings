<?php

namespace HBP\Settings;

use HBP\Settings\Contracts\ObjectMeta;
use HBP\Settings\Contracts\Store;
use HBP\Settings\Meta\Target;
use Hybrid\Contracts\Config\Repository as ConfigRepository;
use Hybrid\Tools\Arr;
use InvalidArgumentException;
use RuntimeException;
use function Hybrid\Tools\value;

/**
 * Effective settings for one namespace.
 *
 * Reads fall through a fixed precedence:
 *
 *     [object meta]  <- only when scoped via for()
 *          |
 *     stored option
 *          |
 *     active preset
 *          |
 *     Hybrid config
 *          |
 *     caller default
 *
 * A stored false, 0, '' or null is a stored value and stops the fall-through.
 *
 * Writes never guess a target. An unscoped instance writes to the option;
 * an instance scoped with for() writes to that object's meta.
 */
final class Settings {
    /**
     * Config subkeys this class reserves under the namespace.
     */
    private const RESERVED = [ 'presets', 'meta', 'features', 'controls', 'tabs', 'sections' ];

    private ?Target $target = null;

    public function __construct(
        private readonly Store $store,
        private readonly ConfigRepository $config,
        private readonly string $namespace,
        private readonly ?ObjectMeta $meta = null,
        private readonly string $hookPrefix = 'hbp-settings',
        private readonly string $metaPrefix = '_hbp_settings_'
    ) {
        if ( '' === trim( $this->namespace, " .\t\n\r" ) ) {
            throw new InvalidArgumentException( 'The settings namespace cannot be empty.' );
        }
    }

    /**
     * Get a new instance scoped to a post or term.
     *
     * This is the opt-in switch for the object-meta tier. Until it is
     * called, no meta lookup happens.
     *
     * @param \HBP\Settings\Meta\Target|\WP_Post|\WP_Term|array{type: string, id: int} $target
     */
    public function for( mixed $target ): self {
        if ( ! $this->meta instanceof ObjectMeta ) {
            throw new RuntimeException(
                'Per-object settings require an ObjectMeta driver. Bind '
                . ObjectMeta::class . ' in the container, or drop the for() call.'
            );
        }

        $clone         = clone $this;
        $clone->target = Target::from( $target );

        return $clone;
    }

    /**
     * Scope to the object currently being queried, if there is one.
     *
     * Returns $this unchanged outside the loop, so callers do not have to
     * branch. Reads on an unscoped instance simply skip the meta tier.
     */
    public function forQueried(): self {
        $target = Target::queried();

        return $target instanceof Target ? $this->for( $target ) : $this;
    }

    public function get( string $key, mixed $default = null ): mixed {
        $key   = $this->key( $key );
        $value = $this->resolve( $key );

        if ( Missing::Value === $value ) {
            $value = $default;
        }

        // Config defaults may be closures so an expensive default is only
        // paid for when it is actually reached.
        return $this->filter( $key, value( $value ) );
    }

    public function has( string $key ): bool {
        return Missing::Value !== $this->resolve( $this->key( $key ) );
    }

    /**
     * Walk the tiers. Returns Missing::Value when no tier holds the key.
     *
     * The precedence is four plain statements on purpose: changing the
     * hierarchy stays a local edit rather than a resolver graph.
     */
    private function resolve( string $key ): mixed {
        $value = $this->fromMeta( $key );

        if ( Missing::Value === $value ) {
            $value = $this->fromStore( $key );
        }

        if ( Missing::Value === $value ) {
            $value = $this->fromPreset( $key );
        }

        if ( Missing::Value === $value ) {
            $value = $this->config->get( "{$this->namespace}.{$key}", Missing::Value );
        }

        return $value;
    }

    public function set( string $key, mixed $value ): bool {
        $key = $this->key( $key );

        if ( $this->target instanceof Target ) {
            return $this->meta->set(
                $this->target->type,
                $this->target->id,
                $this->metaKey( $key ),
                $value
            );
        }

        return $this->store->put( $key, $value );
    }

    public function forget( string $key ): bool {
        $key = $this->key( $key );

        if ( $this->target instanceof Target ) {
            return $this->meta->delete(
                $this->target->type,
                $this->target->id,
                $this->metaKey( $key )
            );
        }

        return $this->store->forget( $key );
    }

    /**
     * Everything stored in the option, without the config or preset tiers.
     *
     * @return array<string, mixed>
     */
    public function stored(): array {
        return $this->store->all();
    }

    /**
     * The meta key a given setting is stored under.
     *
     * Consumers can remap this per key via config: `{namespace}.meta.{key}`.
     * Useful when adopting settings that already live under a legacy key.
     */
    public function metaKey( string $key ): string {
        $key    = $this->key( $key );
        $mapped = $this->fromMap( "{$this->namespace}.meta", $key );

        if ( is_string( $mapped ) && '' !== $mapped ) {
            return $mapped;
        }

        return $this->metaPrefix . $this->namespace . '_' . str_replace( '.', '_', $key );
    }

    public function namespace(): string {
        return $this->namespace;
    }

    public function target(): ?Target {
        return $this->target;
    }

    public function flush(): void {
        $this->store->flush();
    }

    private function fromMeta( string $key ): mixed {
        if ( ! $this->target instanceof Target ) {
            return Missing::Value;
        }

        $metaKey = $this->metaKey( $key );

        if ( ! $this->meta->exists( $this->target->type, $this->target->id, $metaKey ) ) {
            return Missing::Value;
        }

        return $this->meta->get( $this->target->type, $this->target->id, $metaKey );
    }

    private function fromStore( string $key ): mixed {
        return $this->store->has( $key ) ? $this->store->get( $key ) : Missing::Value;
    }

    private function fromPreset( string $key ): mixed {
        $active = $this->config->get( "{$this->namespace}.presets.active" );

        if ( ! is_string( $active ) || '' === $active ) {
            return Missing::Value;
        }

        return $this->fromMap( "{$this->namespace}.presets.{$active}", $key );
    }

    /**
     * Read a dotted key from a config array that may be written either with
     * flat dotted keys or as nested arrays.
     *
     * Arr::get() alone only handles the nested form, so a config entry
     * literally keyed 'site.title' would be invisible to it.
     */
    private function fromMap( string $path, string $key ): mixed {
        $values = $this->config->get( $path );

        if ( ! is_array( $values ) ) {
            return Missing::Value;
        }

        return array_key_exists( $key, $values )
            ? $values[ $key ]
            : Arr::get( $values, $key, Missing::Value );
    }

    private function filter( string $key, mixed $value ): mixed {
        if ( ! function_exists( 'apply_filters' ) ) {
            return $value;
        }

        return apply_filters(
            "{$this->hookPrefix}/{$this->namespace}/setting/{$key}",
            $value,
            $key,
            $this->target
        );
    }

    private function key( string $key ): string {
        $key = trim( $key, '.' );

        if ( '' === $key ) {
            throw new InvalidArgumentException( 'A settings key cannot be empty.' );
        }

        $root = strtok( $key, '.' );

        if ( in_array( $root, self::RESERVED, true ) ) {
            throw new InvalidArgumentException(
                "The settings key [{$key}] uses the reserved root [{$root}]. "
                . 'Reserved roots: ' . implode( ', ', self::RESERVED ) . '.'
            );
        }

        return $key;
    }
}

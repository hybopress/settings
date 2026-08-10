<?php

namespace HBP\Settings\Store;

use HBP\Settings\Contracts\Store;
use Hybrid\Tools\Arr;

/**
 * Stores a namespace's settings in a single WordPress option as a nested
 * array, addressed by dotted key.
 *
 * One option per namespace keeps reads to a single autoloaded row rather
 * than one row per setting.
 */
final class OptionStore implements Store {
    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    public function __construct( private readonly string $option ) {}

    public function has( string $key ): bool {
        return Arr::has( $this->all(), $key );
    }

    public function get( string $key ): mixed {
        return Arr::get( $this->all(), $key );
    }

    /** @return array<string, mixed> */
    public function all(): array {
        if ( null === $this->settings ) {
            $value          = get_option( $this->option, [] );
            $this->settings = is_array( $value ) ? $value : [];
        }

        return $this->settings;
    }

    public function put( string $key, mixed $value ): bool {
        $settings = $this->all();

        if ( Arr::has( $settings, $key ) && Arr::get( $settings, $key ) === $value ) {
            return true;
        }

        Arr::set( $settings, $key, $value );

        return $this->write( $settings );
    }

    public function forget( string $key ): bool {
        $settings = $this->all();

        if ( ! Arr::has( $settings, $key ) ) {
            return true;
        }

        Arr::forget( $settings, $key );

        return $this->write( $settings );
    }

    public function flush(): void {
        $this->settings = null;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function write( array $settings ): bool {
        if ( update_option( $this->option, $settings ) ) {
            $this->settings = $settings;

            return true;
        }

        // The write failed, or another process changed the row underneath
        // us. Either way the cache can no longer be trusted.
        $this->settings = null;

        return false;
    }
}

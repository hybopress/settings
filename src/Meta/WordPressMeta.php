<?php

namespace HBP\Settings\Meta;

use HBP\Settings\Contracts\ObjectMeta;

final class WordPressMeta implements ObjectMeta {
    public function exists( string $type, int $id, string $key ): bool {
        return metadata_exists( $type, $id, $key );
    }

    public function get( string $type, int $id, string $key ): mixed {
        return get_metadata( $type, $id, $key, true );
    }

    public function set( string $type, int $id, string $key, mixed $value ): bool {
        // update_metadata() returns false for an unchanged value, which is
        // not a failure. Treat "already correct" as success.
        if ( $this->exists( $type, $id, $key ) && $this->get( $type, $id, $key ) === $value ) {
            return true;
        }

        return (bool) update_metadata( $type, $id, $key, $value );
    }

    public function delete( string $type, int $id, string $key ): bool {
        if ( ! $this->exists( $type, $id, $key ) ) {
            return true;
        }

        return (bool) delete_metadata( $type, $id, $key );
    }
}

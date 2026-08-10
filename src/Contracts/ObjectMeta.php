<?php

namespace HBP\Settings\Contracts;

/**
 * Per-object (post/term) metadata driver.
 *
 * Only consulted by Settings instances scoped through Settings::for().
 * An unscoped Settings never touches this tier.
 */
interface ObjectMeta {
    public function exists( string $type, int $id, string $key ): bool;

    public function get( string $type, int $id, string $key ): mixed;

    public function set( string $type, int $id, string $key, mixed $value ): bool;

    public function delete( string $type, int $id, string $key ): bool;
}

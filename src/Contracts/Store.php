<?php

namespace HBP\Settings\Contracts;

/**
 * Persistence for one namespace's stored settings.
 *
 * Keys are dotted paths. Implementations own their read cache; callers
 * drop it with flush().
 */
interface Store {
    public function has( string $key ): bool;

    public function get( string $key ): mixed;

    /** @return array<string, mixed> */
    public function all(): array;

    public function put( string $key, mixed $value ): bool;

    public function forget( string $key ): bool;

    public function flush(): void;
}

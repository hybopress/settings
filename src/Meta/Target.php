<?php

namespace HBP\Settings\Meta;

use InvalidArgumentException;
use WP_Post;
use WP_Term;

/**
 * The object a scoped Settings instance reads and writes meta against.
 *
 * Construction is explicit on purpose. The previous implementation guessed
 * a bare integer meant "post ID", which made it impossible to pass an
 * integer default and produced silent wrong reads.
 */
final class Target {
    private function __construct(
        public readonly string $type,
        public readonly int $id
    ) {}

    public static function post( int $id ): self {
        return new self( 'post', self::validId( $id ) );
    }

    public static function term( int $id ): self {
        return new self( 'term', self::validId( $id ) );
    }

    /**
     * Build a target from a WordPress object or an explicit type/id pair.
     *
     * Bare integers are rejected: use Target::post() or Target::term().
     */
    public static function from( mixed $value ): self {
        if ( $value instanceof self ) {
            return $value;
        }

        if ( $value instanceof WP_Post ) {
            return self::post( (int) $value->ID );
        }

        if ( $value instanceof WP_Term ) {
            return self::term( (int) $value->term_id );
        }

        if ( is_array( $value ) && isset( $value['type'], $value['id'] ) ) {
            return new self( (string) $value['type'], self::validId( (int) $value['id'] ) );
        }

        throw new InvalidArgumentException(
            'A settings target must be a WP_Post, WP_Term, Target, or an array with "type" and "id" keys. '
            . 'Bare integers are ambiguous: use Target::post() or Target::term().'
        );
    }

    /**
     * The object currently being queried, or null when there isn't one.
     */
    public static function queried(): ?self {
        if ( ! function_exists( 'get_queried_object' ) ) {
            return null;
        }

        $object = get_queried_object();

        return $object instanceof WP_Post || $object instanceof WP_Term
            ? self::from( $object )
            : null;
    }

    private static function validId( int $id ): int {
        if ( 1 > $id ) {
            throw new InvalidArgumentException( "A settings target ID must be a positive integer, got [{$id}]." );
        }

        return $id;
    }
}

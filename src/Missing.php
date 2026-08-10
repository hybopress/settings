<?php

namespace HBP\Settings;

/**
 * Internal "no value at this tier" sentinel.
 *
 * An enum case can never collide with a value a user actually stored,
 * unlike the magic string this replaces.
 *
 * @internal
 */
enum Missing {
    case Value;
}

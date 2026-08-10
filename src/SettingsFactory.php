<?php

namespace HBP\Settings;

use HBP\Settings\Contracts\ObjectMeta;
use HBP\Settings\Store\OptionStore;
use Hybrid\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;

/**
 * Builds and memoises one Settings instance per namespace.
 *
 * This replaces the old Manager's theme/plugin "kinds". A theme and a
 * plugin are the same thing to this library: a namespace and an option.
 * Whether a namespace belongs to a theme is the consumer's business.
 */
final class SettingsFactory {
    /** @var array<string, \HBP\Settings\Settings> */
    private array $instances = [];

    /** @var array<string, \HBP\Settings\Features> */
    private array $features = [];

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly ?ObjectMeta $meta = null
    ) {}

    /**
     * @param string      $namespace Config namespace, e.g. "child".
     * @param string|null $option Option name. Defaults to "{namespace}_settings".
     */
    public function make( string $namespace, ?string $option = null ): Settings {
        $namespace = trim( $namespace, " .\t\n\r" );

        if ( '' === $namespace ) {
            throw new InvalidArgumentException( 'The settings namespace cannot be empty.' );
        }

        $option = $option ?: "{$namespace}_settings";

        return $this->instances[ "{$namespace}|{$option}" ] ??= new Settings(
            new OptionStore( $option ),
            $this->config,
            $namespace,
            $this->meta
        );
    }

    /**
     * The capability set for a namespace.
     */
    public function features( string $namespace ): Features {
        $namespace = trim( $namespace, " .\t\n\r" );

        return $this->features[ $namespace ] ??= new Features( $this->config, $namespace );
    }

    /**
     * Drop every cached read. Mostly useful in tests and long-running CLI.
     */
    public function flush(): void {
        foreach ( $this->instances as $settings ) {
            $settings->flush();
        }
    }
}

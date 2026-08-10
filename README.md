# HBP Settings

Layered settings resolution for Hybrid Core 7 themes and plugins.

It reads Hybrid Core's existing configuration repository rather than
introducing a second one. This package ships no `config/` directory of its
own: every namespace it reads is owned by the theme or plugin consuming it.

Requires PHP 8.2+, `themehybrid/hybrid-core` ^7.0.

## The consumer loads the config

**This package reads config. It never loads it.** Nothing here populates
`{namespace}.*`, so a consumer that does not load its own config gets `[]`
from every read, with no error.

Load a namespaced directory once, from the theme or plugin bootstrap:

```php
use Hybrid\Tools\Config\Loader;

Loader::load( MYPLUGIN_DIR . '/config', 'myplugin' );
```

Everything under that directory then answers to the namespace:

```text
config/
├── site/
│   └── identity.php     → myplugin.site.identity.*
├── presets.php          → myplugin.presets.*
├── features.php         → myplugin.features.*
└── controls.php         → myplugin.controls.*   (see hbp/settings-ui)
```

A root-level `config/*.php` file loaded without a namespace is framework
config and collides with the framework's own keys by design. Namespace your
package's config.

## Resolution

For a namespace such as `child` or `myplugin`, a read falls through:

```text
object meta        ← only when scoped via for()
    ↓
namespace option
    ↓
active preset
    ↓
Hybrid Core config
    ↓
caller default
```

A stored `false`, `0`, `''` or `null` is a stored value and stops the
fall-through. Only a genuinely absent key falls to the next tier.

The order is four plain statements in `Settings::resolve()`, so changing the
precedence stays a local edit rather than a resolver graph.

## API

Helpers are namespaced and take the namespace explicitly. There is no ambient
"current" namespace to get wrong:

```php
use function HBP\Settings\settings;
use function HBP\Settings\setting;
use function HBP\Settings\features;
use function HBP\Settings\feature;

setting( 'child', 'site.title' );
setting( 'child', 'site.title', 'Fallback' );

$settings = settings( 'child' );
$settings->get( 'site.title' );
$settings->set( 'site.title', 'New' );
$settings->has( 'site.title' );
$settings->forget( 'site.title' );
$settings->stored();            // the option only, no config or preset tier
```

`settings( $namespace, $option )` takes an optional option name, defaulting to
`"{$namespace}_settings"`.

### No global helpers

This package exports no global functions.

Declare a short alias in your own theme or plugin, where owning the name is
your call to make:

```php
function child_setting( string $key, mixed $default = null ): mixed {
    return \HBP\Settings\settings( 'child' )->get( $key, $default );
}
```

## Per-object settings

The object-meta tier is opt-in. An unscoped instance never touches meta:

```php
settings( 'child' )->for( $post )->get( 'layout' );
settings( 'child' )->for( Target::post( 12 ) )->set( 'layout', 'wide' );
settings( 'child' )->forQueried()->get( 'layout' );  // $this outside the loop
```

`Target::from()` accepts a `WP_Post`, a `WP_Term`, a `Target`, or
`[ 'type' => ..., 'id' => ... ]`. Bare integers are rejected: they made an
integer default indistinguishable from a post ID and produced silent wrong
reads. Use `Target::post()` or `Target::term()`.

A scoped instance writes to that object's meta; an unscoped one writes to the
option. Writes never guess a target.

Meta keys default to `_hbp_settings_{namespace}_{key}` and can be remapped per
key at `{namespace}.meta.{key}`, which is what makes adopting settings that
already live under a legacy key possible.

## Presets

A preset is a diff against the config, not a second copy of it:

```php
// config/presets.php
return [
    'active'  => 'minimal',
    'minimal' => [
        'site.title' => 'Minimal',
        'features'   => [ 'gallery.slider' => false ],
        'hidden'     => [ 'site.tagline' ],   // read by hbp/settings-ui
    ],
];
```

Preset bodies may be written with flat dotted keys or as nested arrays; both
resolve.

## Features

A capability is a build-time fact, not something a user stores. `Features`
never reads or writes the option.

```php
feature( 'child', 'gallery.slider' );   // bool
features( 'child' )->all();             // every declared capability
```

Capabilities and their defaults are declared at `{namespace}.features.*`; the
active preset states only where it differs. An undeclared capability is off,
so a typo fails loudly rather than silently enabling something.

Off must mean all three: the behaviour does not run, the controls do not
render, and the assets are not enqueued. Guard all three.

## Reserved roots

These subkeys are reserved under a namespace and cannot be used as setting
keys: `presets`, `meta`, `features`, `controls`, `tabs`, `sections`. Using one
throws.

## Closures in config

A config value may be a closure, so an expensive or translated default is only
paid for when it is reached:

```php
'site.title' => static fn() => esc_html__( 'Untitled', 'child' ),
```

`Settings::get()` and `Features::enabled()` resolve these through
`Hybrid\Tools\value()`. Reading the repository directly does not — a raw
`config()->get()` hands back the closure unresolved.

Closures resolve at the **leaf** only. A closure returning a whole subtree is
not expanded.

## Filters

Every read passes through:

```text
hbp-settings/{namespace}/setting/{key}
```

with `( $value, $key, $target )`.

## Extending

`Store` and `ObjectMeta` are the two seams. `OptionStore` keeps a namespace in
a single autoloaded option addressed by dotted key; `WordPressMeta` is the
metadata boundary. Swapping either is what lets `Settings` be tested without
WordPress.

## Admin UI

Rendering controls, tabs and settings pages lives in `hbp/settings-ui`.

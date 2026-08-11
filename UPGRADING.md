# Upgrading

## Public API surface

While pre-1.0 the supported surface is: the `Chrono` and `Timezones` facades, everything under
`Simtabi\Laranail\Chrono\Core\Contracts`, the published `config/chrono.php` keys, the Eloquent casts,
the validation rules, the Blade components, and the `laranail::chrono.*` commands.

Anything else — in particular the classes under `Core\*\Support` and `Core\*\Resolver` — is internal
and may change without a major bump.

## From `laranail/package-tools`' `Timezone` enum

The generated IANA enum moved here with identical case names and values. Change the import:

```diff
-use Simtabi\Laranail\Package\Tools\Enums\Timezone;
+use Simtabi\Laranail\Chrono\Core\Enums\Timezone;
```

The copy in `package-tools` is deprecated and will be removed after one release cycle.

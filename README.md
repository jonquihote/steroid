# Steroid

A curated Laravel metapackage for essential application packages.

## Installation

Install the metapackage via Composer:

```bash
composer require jonquihote/steroid
```

## What It Does

Steroid is a [Composer metapackage](https://getcomposer.org/doc/04-schema.md#type). It installs **no source files of its own** — requiring it simply pulls in a curated set of essential Laravel packages as dependencies, so a fresh application can be bootstrapped with a single `composer require`.

## Included Packages

Requiring `jonquihote/steroid` installs:

- [robertboes/inertia-breadcrumbs](https://github.com/robertboes/inertia-breadcrumbs) — Breadcrumbs for Inertia.js applications
- [saloonphp/saloon](https://github.com/saloonphp/saloon) — Fluent API integrator / HTTP client abstraction
- [saloonphp/laravel-plugin](https://github.com/saloonphp/laravel-plugin) — Laravel integration for Saloon
- [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) — Log activity in your Laravel app
- [spatie/laravel-data](https://github.com/spatie/laravel-data) — Powerful data objects for Laravel
- [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary) — Associate files with your models
- [spatie/laravel-model-states](https://github.com/spatie/laravel-model-states) — State machine support for Eloquent models
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission) — Roles and permissions for Laravel
- [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) — Build Eloquent queries from API requests
- [spatie/laravel-sluggable](https://github.com/spatie/laravel-sluggable) — Generate slugs for your Eloquent models
- [spatie/laravel-typescript-transformer](https://github.com/spatie/laravel-typescript-transformer) — Transform PHP structures to TypeScript
- [diglactic/laravel-breadcrumbs](https://github.com/diglactic/laravel-breadcrumbs) — Simple breadcrumb navigation

After installation, follow each package's own documentation to publish its config and migrations as needed.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

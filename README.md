# :package_name

A Composer meta-package that bundles a curated set of Laravel dependencies into a single requirement.

This package contains no code of its own. Requiring it pulls in a consistent set of upstream packages so your application can depend on one requirement instead of many.

## Requirements

- PHP `^8.5`
- Laravel `^13.0` (via `illuminate/support`)

## Installation

Install via Composer:

```bash
composer require :vendor_slug/:package_slug
```

All bundled packages support [Laravel's package auto-discovery](https://laravel.com/docs/packages#package-discovery); their service providers will be registered automatically.

### Inertia Breadcrumbs

[`robertboes/inertia-breadcrumbs`](https://github.com/robertboes/inertia-breadcrumbs) uses [`diglactic/laravel-breadcrumbs`](https://github.com/diglactic/laravel-breadcrumbs) as its default collector. After installation, publish its configuration and select the Diglactic collector so Inertia Breadcrumbs can resolve your application's breadcrumbs:

```bash
php artisan vendor:publish --tag=inertia-breadcrumbs-config
```

Refer to the [Inertia Breadcrumbs documentation](https://github.com/robertboes/inertia-breadcrumbs) and the [Diglactic Laravel Breadcrumbs documentation](https://github.com/diglactic/laravel-breadcrumbs) for full setup instructions.

## Bundled Packages

| Package | Constraint | Description |
| ------- | ---------- | ----------- |
| [illuminate/support](https://github.com/illuminate/support) | `^13.0` | Laravel support helpers and collections. |
| [robertboes/inertia-breadcrumbs](https://github.com/robertboes/inertia-breadcrumbs) | `^1.0` | Share Laravel breadcrumbs with Inertia.js. |
| [saloonphp/laravel-plugin](https://github.com/saloonphp/laravel-plugin) | `^5.0` | Laravel integration for Saloon HTTP clients. |
| [saloonphp/saloon](https://github.com/saloonphp/saloon) | `^4.0` | Build beautiful, testable API integrations. |
| [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) | `^5.0` | Log user activity and Eloquent model changes. |
| [spatie/laravel-data](https://github.com/spatie/laravel-data) | `^4.21` | Create rich data objects. |
| [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary) | `^11.0` | Associate files with Eloquent models. |
| [spatie/laravel-model-states](https://github.com/spatie/laravel-model-states) | `^2.13` | State machine support for Eloquent models. |
| [spatie/laravel-permission](https://github.com/spatie/laravel-permission) | `^8.0` | Associate users with roles and permissions. |
| [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) | `^7.2` | Build Eloquent queries from API requests. |
| [spatie/laravel-sluggable](https://github.com/spatie/laravel-sluggable) | `^4.0` | Generate slugs for Eloquent models. |
| [spatie/laravel-typescript-transformer](https://github.com/spatie/laravel-typescript-transformer) | `^3.0` | Transform PHP types to TypeScript. |
| [diglactic/laravel-breadcrumbs](https://github.com/diglactic/laravel-breadcrumbs) | `^10.1` | A simple breadcrumb package for Laravel. |

## Package-Specific Setup

This umbrella package does not ship its own configuration, migrations, routes, views, or assets, so there is nothing to publish from it.

Some bundled packages require their own post-install steps (publishing configuration or migrations, running migrations, registering middleware, and so on). Refer to each package's upstream documentation for setup instructions:

- [Inertia Breadcrumbs](https://github.com/robertboes/inertia-breadcrumbs)
- [Saloon Laravel Plugin](https://github.com/saloonphp/laravel-plugin)
- [Spatie Laravel Activitylog](https://spatie.be/docs/laravel-activitylog)
- [Spatie Laravel Data](https://spatie.be/docs/laravel-data)
- [Spatie Laravel Medialibrary](https://spatie.be/docs/laravel-medialibrary)
- [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Spatie Laravel Query Builder](https://spatie.be/docs/laravel-query-builder)
- [Spatie Laravel Sluggable](https://spatie.be/docs/laravel-sluggable)
- [Spatie Laravel TypeScript Transformer](https://spatie.be/docs/laravel-typescript-transformer)
- [Diglactic Laravel Breadcrumbs](https://github.com/diglactic/laravel-breadcrumbs)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

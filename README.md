# jonquihote/steroid

A Composer library that bundles a curated set of Laravel dependencies into a single requirement.

This package contains no code of its own. Requiring it pulls in a consistent set of upstream packages so your application can depend on one requirement instead of many.

## Requirements

- PHP `^8.5`
- Laravel `^13.0` (via `illuminate/support`)

## Installation

Install via Composer:

```bash
composer require jonquihote/steroid
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

## Laravel Boost

This package mirrors [Laravel Boost](https://github.com/laravel/boost) skills and guidelines from a subset of its bundled packages. When this library is a direct dependency of your Laravel application, Boost's normal package discovery picks them up automatically.

The following six skills are mirrored under `resources/boost/skills/`:

- `inertia-breadcrumbs` (from `robertboes/inertia-breadcrumbs`)
- `saloon-development` (from `saloonphp/laravel-plugin`)
- `medialibrary-development` (from `spatie/laravel-medialibrary`)
- `laravel-permission-development` (from `spatie/laravel-permission`)
- `laravel-query-builder` (from `spatie/laravel-query-builder`)
- `sluggable-development` (from `spatie/laravel-sluggable`)

Four guideline files are mirrored under `resources/boost/guidelines/`:

- `robertboes-inertia-breadcrumbs.core.blade.php`
- `saloonphp-laravel-plugin.core.blade.php`
- `spatie-laravel-activitylog.core.blade.php`
- `spatie-laravel-medialibrary.core.blade.php`

The mirror is version-pinned: each mirrored file is copied from a specific upstream release tag. Mirrored `SKILL.md` and guideline files carry a leading HTML provenance comment recording the upstream package, tag, and source URL; mirrored reference files are exact upstream copies and carry no comment. The [`.mirror-manifest.json`](resources/boost/.mirror-manifest.json) records the exact tags and commit SHAs used, and [`MIRRORED-LICENSES.md`](resources/boost/MIRRORED-LICENSES.md) preserves the upstream MIT license texts for attribution.

Bundled packages that do not ship their own Boost skills or guidelines do not get synthetic ones here; only genuine upstream Boost assets are mirrored.

### Mirror Audit

A CI workflow ([`.github/workflows/boost-mirror-audit.yml`](.github/workflows/boost-mirror-audit.yml)) runs [`.github/scripts/verify-boost-mirror.php`](.github/scripts/verify-boost-mirror.php) to prove the mirror stays in sync with what this package requires. It fails unless:

- Every direct Composer `require` whose installed tree contains `resources/boost/**` files has a corresponding `sources[]` entry in [`.mirror-manifest.json`](resources/boost/.mirror-manifest.json).
- Each manifest source is a direct `require` whose `declared_constraint` exactly matches `composer.json`, whose `resolved_tag` exactly matches the version pinned in `composer.lock`, whose lock entry is an immutable release tag (no `dev-*` / `*-dev` aliases), and whose `resolved_commit` equals the lockfile's `source.reference`.
- The upstream `resources/boost/**` inventory matches the manifest's `skills[].mirrored_files` and `guidelines[].source_file` entries (new or removed upstream files are errors).
- Every mirrored file exists; `SKILL.md` and guideline copies match upstream content byte-for-byte after stripping the leading provenance HTML comment; reference files match upstream exactly and carry no comment.
- Mirrored `SKILL.md` and guideline provenance comments record the manifest's package, resolved tag, and expected raw GitHub source URL.
- No local file under `resources/boost/skills/` or `resources/boost/guidelines/` is orphaned; each is uniquely declared as a manifest destination.
- Upstream `SKILL.md` files have unique YAML `name:` values, and each manifest `skills[].name` matches its upstream `SKILL.md`.
- [`MIRRORED-LICENSES.md`](resources/boost/MIRRORED-LICENSES.md) exists and contains every manifest source's copyright string and license URL.
- `.gitattributes` does not mark `resources/` or any of its children as `export-ignore`, so mirrored assets ship in package archives.

The workflow runs on `pull_request` and on `push` when `composer.json`, `.gitattributes`, `resources/boost/**`, or the workflow/script change, on a daily cron schedule, and on manual `workflow_dispatch`. It builds a scratch `composer.json`/lock/vendor tree under `/tmp/boost-mirror-audit` (so repository metadata placeholders do not need to be resolved) and invokes the verifier against the repository's `resources/boost/` tree.

When a bundled dependency adds, removes, or modifies its upstream Boost assets — or when `composer.json` adds or drops a Boost-capable direct requirement — update the mirrored files, `.mirror-manifest.json`, and [`MIRRORED-LICENSES.md`](resources/boost/MIRRORED-LICENSES.md) in the same PR.

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

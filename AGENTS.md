# Package

This repository is a generic Composer-only Laravel umbrella/meta-package. It contains no runtime code, service providers, autoload mappings, or tests — `composer.json` declares a curated set of upstream Laravel dependencies so consuming applications can require a single package instead of many.

## Package Conventions

- Keep `:author_name`, `:package_name`, `:vendor_slug`, and `:package_slug` placeholders intact until a real package identity is supplied.
- The package `type` is `metapackage`. Do not add `autoload`, `autoload-dev`, `extra.laravel`, or any `src/`, `tests/`, or workbench scaffolding unless the package's purpose changes.
- Add or remove dependencies by editing the `require` block of `composer.json`. Do not commit `composer.lock` — this package is a library, not an application.
- All bundled upstream packages rely on Laravel's package auto-discovery; do not add provider or alias registration here.
- Do not add `vendor:publish` or other Artisan commands to this repository; package-specific setup belongs to upstream packages and is documented in `README.md`.

## Quick Commands

- Validate Composer metadata: `composer validate --no-check-publish --no-check-lock`
- No test, lint, or static-analysis tooling is installed — this package ships no PHP code.

## Documentation

- `README.md` is the package-facing documentation. Keep the bundled-packages table, constraints, and upstream links in sync with `composer.json` whenever dependencies change.
- `CHANGELOG.md` tracks releases; the `update-changelog` GitHub workflow updates it after each published release.

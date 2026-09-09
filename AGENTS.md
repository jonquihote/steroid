# Steroid Metapackage

This repository is a Composer metapackage (`jonquihote/steroid`). It ships no source code — its only purpose is to require a curated set of essential Laravel packages as Composer dependencies.

## Conventions

- All package changes are made exclusively in `composer.json`'s `require` section.
- Keep each requirement pinned to a clear major-version constraint compatible with the supported PHP (`^8.3`) and `illuminate/support` (`^12.0||^13.0`) versions.
- When adding, removing, or bumping a required package, update the "Included Packages" list in `README.md` in the same change.
- Do not add `require-dev`, autoload sections, scripts, source directories, tests, or tooling scaffolding — this package is intentionally a manifest only.
- Keep `.gitattributes` export-ignore entries limited to repository-only files that actually exist.

## Validation

- Run `composer validate --strict --no-check-publish` after editing `composer.json`. Never run `composer install` or `composer update` in this repository, and never commit a `composer.lock`.

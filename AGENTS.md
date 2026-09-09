# Package

This repository is a generic Composer-only Laravel umbrella library. It contains no runtime code, service providers, autoload mappings, or tests — `composer.json` declares a curated set of upstream Laravel dependencies so consuming applications can require a single package instead of many.

## Package Conventions

- Keep `:author_name`, `:package_name`, `:vendor_slug`, and `:package_slug` placeholders intact until a real package identity is supplied.
- The package `type` is `library`. Do not add `autoload`, `autoload-dev`, `extra.laravel`, or any `src/`, `tests/`, or workbench scaffolding unless the package's purpose changes.
- Add or remove dependencies by editing the `require` block of `composer.json`. Do not commit `composer.lock` — this package is a library, not an application.
- All bundled upstream packages rely on Laravel's package auto-discovery; do not add provider or alias registration here.
- Do not add `vendor:publish` or other Artisan commands to this repository; package-specific setup belongs to upstream packages and is documented in `README.md`.

## Quick Commands

- Validate Composer metadata: `composer validate --no-check-publish --no-check-lock`
- No test, lint, or static-analysis tooling is installed — this package ships no PHP code.

## Mirrored Boost Assets

- This package deliberately ships mirrored Laravel Boost assets under `resources/boost/**` so that Boost's package discovery can pick them up when this library is a direct application dependency. Do not remove, relocate, or rewrite these files.
- Every mirrored `SKILL.md` and guideline file must retain (a) its exact upstream content and (b) the leading HTML provenance comment that records the upstream package, tag, and raw source URL. When re-mirroring a newer tag, overwrite content from the upstream release rather than editing it here, and update the provenance comment to match.
- Whenever a bundled Composer constraint changes in a way that alters the mirrored upstream tag, regenerate `resources/boost/.mirror-manifest.json` (schema 1; per-source package, constraint, resolved tag, resolved commit SHA, repository URL, license, license URL, copyright, mirrored skill directories/names/files, guideline sources) and `resources/boost/MIRRORED-LICENSES.md` (full upstream MIT license texts and attribution) to stay in sync.
- `resources/` must be retained in package archives; do not add an `export-ignore` rule for `resources/` or any path beneath it in `.gitattributes`.

## Mirror Audit

- `.github/scripts/verify-boost-mirror.php` is the Boost mirror verifier; `.github/workflows/boost-mirror-audit.yml` runs it in CI. The verifier is plain PHP 8.5, has no external dependencies, and lives under `.github/` so it is excluded from package archives.
- The workflow triggers on `pull_request` and on `push` when `composer.json`, `.gitattributes`, `resources/boost/**`, or the workflow/script change, on a daily cron schedule, and on `workflow_dispatch`. It resolves the `require` block in a scratch directory via `composer update --no-install` + `composer install --no-autoloader`, then runs the verifier against the repository's `resources/boost/` tree.
- The verifier enforces manifest shape (every source has non-empty `composer_package`, `declared_constraint`, `resolved_tag`, 40-hex `resolved_commit`, an HTTPS GitHub `repository_url`, `license: MIT`, `license_url`, `copyright`, and `skills`/`guidelines` arrays with normalized relative paths), upstream-vs-manifest inventory parity, mirrored-file content parity (SKILL/guidelines strip the provenance HTML comment; references are byte-for-byte exact), provenance-comment consistency with the manifest, copyright/license-URL presence in `MIRRORED-LICENSES.md`, no orphaned local mirror files, no duplicate YAML `name:` across upstream SKILL.md files, and no `export-ignore` rule in `.gitattributes` that would exclude `resources/`.
- Adding a new direct `require` that ships `resources/boost/**` assets, bumping a constraint to a tag whose Boost assets moved, or editing mirrored files must update the mirror, `.mirror-manifest.json`, and `MIRRORED-LICENSES.md` in the same PR — CI fails otherwise.

## Documentation

- `README.md` is the package-facing documentation. Keep the bundled-packages table, constraints, and upstream links in sync with `composer.json` whenever dependencies change.
- `CHANGELOG.md` tracks releases; the `update-changelog` GitHub workflow updates it after each published release.

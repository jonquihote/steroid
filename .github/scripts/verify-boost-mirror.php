#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Boost mirror audit.
 *
 * Hardened verifier for the repository's `resources/boost/**` mirror.
 * Plain PHP 8.5 CLI. No external dependencies.
 */

if (PHP_VERSION_ID < 80500) {
    fwrite(STDERR, "boost-mirror: requires PHP 8.5 or newer; running " . PHP_VERSION . PHP_EOL);
    exit(2);
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo <<<TXT
Usage: verify-boost-mirror.php --composer-json=PATH --composer-lock=PATH --vendor-dir=PATH --manifest=PATH --mirror-root=PATH

Options:
  --composer-json=PATH   Path to composer.json declaring the direct dependencies.
  --composer-lock=PATH   Path to the resolved composer.lock for that composer.json.
  --vendor-dir=PATH      Path to the vendor directory containing installed packages.
  --manifest=PATH        Path to resources/boost/.mirror-manifest.json in this repository.
  --mirror-root=PATH     Absolute path to this repository's mirror root (usually the repo root,
                         such that mirror-relative paths like resources/boost/... resolve).
  -h, --help             Show this help.

Exit codes:
  0  audit passed
  1  audit produced one or more errors
  2  argument or shape validation failure
TXT;
    exit(0);
}

// ---------------------------------------------------------------------------
// Argument parsing
// ---------------------------------------------------------------------------

$options = parseArgs($argv);
if (is_string($options)) {
    fwrite(STDERR, "boost-mirror: {$options}" . PHP_EOL);
    exit(2);
}

[$composerJsonPath, $composerLockPath, $vendorDir, $manifestPath, $mirrorRoot] = $options;

foreach ([
    'composer-json' => $composerJsonPath,
    'composer-lock' => $composerLockPath,
    'manifest' => $manifestPath,
] as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "boost-mirror: --{$label} file not found: {$path}" . PHP_EOL);
        exit(2);
    }
}
if (!is_dir($vendorDir)) {
    fwrite(STDERR, "boost-mirror: --vendor-dir directory not found: {$vendorDir}" . PHP_EOL);
    exit(2);
}
if (!is_dir($mirrorRoot)) {
    fwrite(STDERR, "boost-mirror: --mirror-root directory not found: {$mirrorRoot}" . PHP_EOL);
    exit(2);
}

$mirrorRoot = rtrim($mirrorRoot, '/');

// ---------------------------------------------------------------------------
// JSON loading + shape validation (exit 2 on hard failure)
// ---------------------------------------------------------------------------

$composerJson = loadJsonObject($composerJsonPath, 'composer.json');
$composerLock = loadJsonObject($composerLockPath, 'composer.lock');
$manifest = loadJsonObject($manifestPath, 'mirror manifest');

$errors = [];

// ---------------------------------------------------------------------------
// Extract direct non-platform requirements
// ---------------------------------------------------------------------------

$require = $composerJson['require'] ?? null;
if (!is_array($require)) {
    fwrite(STDERR, 'boost-mirror: composer.json is missing a "require" object' . PHP_EOL);
    exit(2);
}

$directRequires = [];
foreach ($require as $package => $constraint) {
    if (!is_string($package) || !is_string($constraint)) {
        continue;
    }
    if (isPlatformRequirement($package)) {
        continue;
    }
    $directRequires[$package] = $constraint;
}

// ---------------------------------------------------------------------------
// Locked packages map: name -> ['version' => string, 'source_reference' => ?string]
// ---------------------------------------------------------------------------

$lockPackages = $composerLock['packages'] ?? null;
if (!is_array($lockPackages)) {
    fwrite(STDERR, 'boost-mirror: composer.lock is missing a "packages" array' . PHP_EOL);
    exit(2);
}
$locked = [];
foreach ($lockPackages as $entry) {
    if (!is_array($entry) || !isset($entry['name']) || !is_string($entry['name'])) {
        continue;
    }
    $version = $entry['version'] ?? null;
    $source = $entry['source'] ?? null;
    $sourceRef = (is_array($source) && isset($source['reference']) && is_string($source['reference']))
        ? $source['reference']
        : null;
    if (!is_string($version)) {
        continue;
    }
    $locked[$entry['name']] = [
        'version' => $version,
        'source_reference' => $sourceRef,
    ];
}

// ---------------------------------------------------------------------------
// Manifest shape + content validation
// ---------------------------------------------------------------------------

$schema = $manifest['schema'] ?? null;
if ($schema !== 1) {
    $errors[] = 'manifest top-level "schema" must equal 1';
}

$sources = $manifest['sources'] ?? null;
if (!is_array($sources)) {
    $errors[] = 'manifest is missing a "sources" array';
    $sources = [];
}

$manifestByPackage = [];
// destination path (mirror-relative, e.g. "resources/boost/skills/foo/SKILL.md") =>
//   ['package' => string, 'upstream_path' => string]
$declaredDestinations = [];
// tracks duplicate upstream source paths (per package, both skills+guidelines)
$declaredSources = [];

foreach ($sources as $i => $source) {
    if (!is_array($source)) {
        $errors[] = "manifest sources[{$i}] must be an object";
        continue;
    }
    $label = "manifest sources[{$i}]";
    $pkg = $source['composer_package'] ?? null;
    $declared = $source['declared_constraint'] ?? null;
    $resolvedTag = $source['resolved_tag'] ?? null;
    $resolvedCommit = $source['resolved_commit'] ?? null;
    $repoUrl = $source['repository_url'] ?? null;
    $license = $source['license'] ?? null;
    $licenseUrl = $source['license_url'] ?? null;
    $copyright = $source['copyright'] ?? null;
    $skills = $source['skills'] ?? null;
    $guidelines = $source['guidelines'] ?? null;

    $sourceErrors = [];

    if (!isNonEmptyString($pkg)) {
        $sourceErrors[] = 'composer_package must be a non-empty string';
        $pkg = null;
    } else {
        $label = "manifest sources[{$i}] ({$pkg})";
    }
    if (!isNonEmptyString($declared)) {
        $sourceErrors[] = 'declared_constraint must be a non-empty string';
        $declared = null;
    }
    if (!isNonEmptyString($resolvedTag)) {
        $sourceErrors[] = 'resolved_tag must be a non-empty string';
        $resolvedTag = null;
    }
    if (!is_string($resolvedCommit) || !preg_match('/\A[0-9a-f]{40}\z/', $resolvedCommit)) {
        $sourceErrors[] = 'resolved_commit must be a 40-character lowercase hex string';
        $resolvedCommit = null;
    }
    if (!is_string($repoUrl) || !preg_match('#\Ahttps://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z#', $repoUrl)) {
        $sourceErrors[] = 'repository_url must be an HTTPS GitHub URL like https://github.com/owner/repo';
        $repoUrl = null;
    }
    if ($license !== 'MIT') {
        $sourceErrors[] = 'license must equal "MIT"';
    }
    if (!isNonEmptyString($licenseUrl)) {
        $sourceErrors[] = 'license_url must be a non-empty string';
        $licenseUrl = null;
    }
    if (!isNonEmptyString($copyright)) {
        $sourceErrors[] = 'copyright must be a non-empty string';
        $copyright = null;
    }
    if (!is_array($skills)) {
        $sourceErrors[] = 'skills must be an array';
        $skills = [];
    }
    if (!is_array($guidelines)) {
        $sourceErrors[] = 'guidelines must be an array';
        $guidelines = [];
    }

    foreach ($sourceErrors as $msg) {
        $errors[] = "{$label}: {$msg}";
    }

    if ($pkg === null) {
        // Cannot safely continue accumulating under a null key.
        continue;
    }

    if (isset($manifestByPackage[$pkg])) {
        $errors[] = "manifest contains duplicate composer_package entry: {$pkg}";
        continue;
    }

    // Validate skills
    $seenSkillDirSkillMd = [];
    foreach ($skills as $j => $skill) {
        $slabel = "{$label}.skills[{$j}]";
        if (!is_array($skill)) {
            $errors[] = "{$slabel} must be an object";
            continue;
        }
        $skillName = $skill['name'] ?? null;
        $skillDir = $skill['directory'] ?? null;
        $mirroredFiles = $skill['mirrored_files'] ?? null;

        if (!isNonEmptyString($skillName)) {
            $errors[] = "{$slabel}.name must be a non-empty string";
            $skillName = null;
        }
        if (!isNonEmptyString($skillDir)) {
            $errors[] = "{$slabel}.directory must be a non-empty string";
            $skillDir = null;
        } else {
            $skillDirErr = validateRelativePath($skillDir);
            if ($skillDirErr !== null) {
                $errors[] = "{$slabel}.directory {$skillDirErr}";
                $skillDir = null;
            } elseif (!str_starts_with($skillDir . '/', 'resources/boost/skills/')) {
                $errors[] = "{$slabel}.directory must be rooted under resources/boost/skills/; got {$skillDir}";
                $skillDir = null;
            }
        }
        if (!is_array($mirroredFiles)) {
            $errors[] = "{$slabel}.mirrored_files must be an array";
            $mirroredFiles = [];
        }

        // Each skill must enumerate at least one SKILL.md inside directory.
        $localSkillMdCount = 0;
        foreach ($mirroredFiles as $k => $path) {
            $flabel = "{$slabel}.mirrored_files[{$k}]";
            if (!is_string($path) || $path === '') {
                $errors[] = "{$flabel} must be a non-empty string";
                continue;
            }
            $err = validateRelativePath($path);
            if ($err !== null) {
                $errors[] = "{$flabel} {$err} (got {$path})";
                continue;
            }
            // Skills may only enumerate upstream resources/boost/skills/** files.
            if (!str_starts_with($path . '/', 'resources/boost/skills/')) {
                $errors[] = "{$flabel} must be rooted under resources/boost/skills/; got {$path}";
                continue;
            }
            if ($skillDir !== null && !pathIsUnder($path, $skillDir)) {
                $errors[] = "{$flabel} must be inside skill directory {$skillDir}; got {$path}";
                continue;
            }
            if (basename($path) === 'SKILL.md') {
                $localSkillMdCount++;
                if ($skillDir !== null && dirname($path) !== $skillDir) {
                    $errors[] = "{$flabel} SKILL.md must live at the top of the skill directory {$skillDir}; got {$path}";
                }
            }
            // Register destination (skills keep the upstream relative path).
            registerDestination($declaredDestinations, $path, $pkg, $path, $errors);
            registerSource($declaredSources, $pkg, $path, $errors);
        }
        if ($skillDir !== null) {
            if ($localSkillMdCount === 0) {
                $errors[] = "{$slabel} must include exactly one SKILL.md in {$skillDir}";
            } elseif ($localSkillMdCount > 1) {
                $errors[] = "{$slabel} must include exactly one SKILL.md in {$skillDir}; got {$localSkillMdCount}";
            }
        }
    }

    // Validate guidelines
    foreach ($guidelines as $j => $g) {
        $glabel = "{$label}.guidelines[{$j}]";
        if (!is_array($g)) {
            $errors[] = "{$glabel} must be an object";
            continue;
        }
        $sourceFile = $g['source_file'] ?? null;
        $mirroredAs = $g['mirrored_as'] ?? null;

        if (!isNonEmptyString($sourceFile)) {
            $errors[] = "{$glabel}.source_file must be a non-empty string";
            $sourceFile = null;
        } else {
            $err = validateRelativePath($sourceFile);
            if ($err !== null) {
                $errors[] = "{$glabel}.source_file {$err} (got {$sourceFile})";
                $sourceFile = null;
            } elseif (!str_starts_with($sourceFile . '/', 'resources/boost/guidelines/')) {
                $errors[] = "{$glabel}.source_file must be rooted under resources/boost/guidelines/; got {$sourceFile}";
                $sourceFile = null;
            }
        }
        if (!isNonEmptyString($mirroredAs)) {
            $errors[] = "{$glabel}.mirrored_as must be a non-empty string";
            $mirroredAs = null;
        } else {
            $err = validateRelativePath($mirroredAs);
            if ($err !== null) {
                $errors[] = "{$glabel}.mirrored_as {$err} (got {$mirroredAs})";
                $mirroredAs = null;
            } elseif (!str_starts_with($mirroredAs . '/', 'resources/boost/guidelines/')) {
                $errors[] = "{$glabel}.mirrored_as must be rooted under resources/boost/guidelines/; got {$mirroredAs}";
                $mirroredAs = null;
            }
        }
        if ($sourceFile !== null && $mirroredAs !== null) {
            registerDestination($declaredDestinations, $mirroredAs, $pkg, $sourceFile, $errors);
            registerSource($declaredSources, $pkg, $sourceFile, $errors);
        }
    }

    $manifestByPackage[$pkg] = $source;
}

// ---------------------------------------------------------------------------
// 1. Direct require with installed resources/boost but no manifest entry
// ---------------------------------------------------------------------------

$boostCapableDirect = [];
foreach ($directRequires as $package => $constraint) {
    $packageRoot = vendorPackageRoot($vendorDir, $package);
    if ($packageRoot === null) {
        continue;
    }
    $boostDir = $packageRoot . '/resources/boost';
    if (!is_dir($boostDir)) {
        continue;
    }
    $files = listRelativeFiles($boostDir, 'resources/boost');
    if ($files === []) {
        continue;
    }
    $boostCapableDirect[$package] = $files;
    if (!isset($manifestByPackage[$package])) {
        $errors[] = "package {$package} ships resources/boost assets but has no entry in the mirror manifest; mirror its files and add it to the manifest, or remove it from require.";
    }
}

// ---------------------------------------------------------------------------
// 2. Manifest source must be a direct require with matching constraint
// ---------------------------------------------------------------------------

foreach ($manifestByPackage as $package => $source) {
    if (!isset($directRequires[$package])) {
        $errors[] = "manifest lists {$package} but it is not a direct Composer require; remove the manifest source or add the package to composer.json require.";
        continue;
    }
    $declared = $source['declared_constraint'] ?? null;
    $actual = $directRequires[$package];
    if (is_string($declared) && $declared !== $actual) {
        $errors[] = "manifest declared_constraint for {$package} ({$declared}) does not match composer.json require constraint ({$actual}); update the manifest to match.";
    }
}

// ---------------------------------------------------------------------------
// 3. Version provenance: locked version = resolved_tag, non-immutable
//    versions rejected, lock source.reference = resolved_commit
// ---------------------------------------------------------------------------

foreach ($manifestByPackage as $package => $source) {
    if (!isset($locked[$package])) {
        $errors[] = "manifest lists {$package} but it is not present in composer.lock; run composer update to produce a lockfile that includes it.";
        continue;
    }
    $entry = $locked[$package];
    $lockedVersion = $entry['version'];
    $resolvedTag = $source['resolved_tag'] ?? null;

    if (is_string($resolvedTag) && $lockedVersion !== $resolvedTag) {
        $errors[] = "manifest resolved_tag for {$package} ({$resolvedTag}) does not match locked version ({$lockedVersion}); refresh the manifest after the next composer update.";
    }
    if (isNonImmutableVersion($lockedVersion)) {
        $errors[] = "locked version for {$package} ({$lockedVersion}) is not an immutable release tag (dev branch, alias, or similar); pin a stable tag.";
    }
    $resolvedCommit = $source['resolved_commit'] ?? null;
    $sourceRef = $entry['source_reference'];
    if ($sourceRef === null) {
        $errors[] = "composer.lock entry for {$package} has no source.reference; ensure it resolved from a dist/source with a commit reference.";
    } elseif (is_string($resolvedCommit) && $sourceRef !== $resolvedCommit) {
        $errors[] = "manifest resolved_commit for {$package} ({$resolvedCommit}) does not match locked source.reference ({$sourceRef}); update the manifest to match the lockfile.";
    }
}

// ---------------------------------------------------------------------------
// 4. Inventory: upstream resources/boost/** vs manifest entries
// ---------------------------------------------------------------------------

$expectedInventory = []; // pkg -> [upstream_path => mirror_relative]
foreach ($manifestByPackage as $package => $source) {
    $expectedInventory[$package] = [];
    foreach (($source['skills'] ?? []) as $skill) {
        if (!is_array($skill)) {
            continue;
        }
        foreach (($skill['mirrored_files'] ?? []) as $path) {
            if (!is_string($path)) {
                continue;
            }
            $expectedInventory[$package][$path] = $path;
        }
    }
    foreach (($source['guidelines'] ?? []) as $g) {
        if (!is_array($g)) {
            continue;
        }
        $sf = $g['source_file'] ?? null;
        $ma = $g['mirrored_as'] ?? null;
        if (is_string($sf) && is_string($ma)) {
            $expectedInventory[$package][$sf] = $ma;
        }
    }
}

foreach ($expectedInventory as $package => $expectedPaths) {
    if (!isset($directRequires[$package])) {
        continue;
    }
    $packageRoot = vendorPackageRoot($vendorDir, $package);
    if ($packageRoot === null) {
        continue;
    }
    $actualFiles = listRelativeFiles($packageRoot . '/resources/boost', 'resources/boost');

    $expectedSet = array_keys($expectedPaths);
    sort($expectedSet);
    sort($actualFiles);

    $missingFromManifest = array_diff($actualFiles, $expectedSet);
    $missingFromInstalled = array_diff($expectedSet, $actualFiles);

    foreach ($missingFromManifest as $path) {
        $errors[] = "{$package}: installed upstream file {$path} is not listed in the manifest; mirror it (new skill/reference/guideline) and update the manifest.";
    }
    foreach ($missingFromInstalled as $path) {
        $errors[] = "{$package}: manifest expects {$path} but the installed package does not contain it; remove the entry and its mirrored copy, or re-install.";
    }
}

// ---------------------------------------------------------------------------
// 4b. Reject stale/orphaned local mirror files
// ---------------------------------------------------------------------------
// Recursively scan all of <mirror-root>/resources/boost/. The only permitted
// non-manifest destinations are the two metadata files listed below. Any
// other local file — including unknown top-level or category files — fails.

$localMirrorRoot = $mirrorRoot . '/resources/boost';

$orphanAllowed = [
    'resources/boost/.mirror-manifest.json' => true,
    'resources/boost/MIRRORED-LICENSES.md' => true,
];

$localFiles = listRelativeFiles($localMirrorRoot, 'resources/boost');

foreach ($localFiles as $localPath) {
    if (isset($orphanAllowed[$localPath])) {
        continue;
    }
    if (!isset($declaredDestinations[$localPath])) {
        $errors[] = "orphaned local mirror file {$localPath} is not declared as a destination in the manifest; mirror it properly or delete it.";
    }
}

// Also flag manifest destinations that appear more than once (shared/duplicated).
foreach ($declaredDestinations as $dest => $info) {
    // registerDestination already emitted per-duplicate errors.
    (void)$info;
}

// ---------------------------------------------------------------------------
// 5. Mirror file existence + content comparison + provenance verification
// ---------------------------------------------------------------------------

foreach ($expectedInventory as $package => $expectedPaths) {
    if (!isset($directRequires[$package])) {
        continue;
    }
    $packageRoot = vendorPackageRoot($vendorDir, $package);
    if ($packageRoot === null) {
        continue;
    }
    $source = $manifestByPackage[$package];
    $expectedTag = $source['resolved_tag'] ?? null;

    foreach ($expectedPaths as $upstreamPath => $mirrorRelative) {
        $upstreamFile = $packageRoot . '/' . $upstreamPath;
        $mirrorFile = $mirrorRoot . '/' . $mirrorRelative;

        if (!is_file($mirrorFile)) {
            $errors[] = "{$package}: mirrored file missing from this repository: {$mirrorRelative}";
            continue;
        }
        if (!is_file($upstreamFile)) {
            continue; // already reported in inventory
        }
        $upstreamContent = file_get_contents($upstreamFile);
        $mirrorContent = file_get_contents($mirrorFile);
        if ($upstreamContent === false || $mirrorContent === false) {
            $errors[] = "{$package}: failed to read {$mirrorRelative} or {$upstreamPath}";
            continue;
        }

        $isSkill = basename($upstreamPath) === 'SKILL.md';
        $isGuideline = str_starts_with($upstreamPath . '/', 'resources/boost/guidelines/');

        if ($isSkill || $isGuideline) {
            $parsed = parseProvenanceComment($mirrorContent);
            if ($parsed === null) {
                $errors[] = "{$package}: mirrored file {$mirrorRelative} is missing a valid leading HTML provenance comment.";
                continue;
            }
            [$prov, $bodyMirror] = $parsed;

            // Assert provenance fields match manifest/package/source path.
            if (!isset($prov['upstream package']) || $prov['upstream package'] !== $package) {
                $errors[] = "{$package}: mirrored file {$mirrorRelative} provenance \"Upstream package\" does not equal {$package}.";
            }
            if (is_string($expectedTag)
                && (!isset($prov['upstream tag']) || $prov['upstream tag'] !== $expectedTag)) {
                $errors[] = "{$package}: mirrored file {$mirrorRelative} provenance \"Upstream tag\" does not equal manifest resolved_tag {$expectedTag}.";
            }
            $expectedUrl = buildRawSourceUrl($source, $upstreamPath);
            if ($expectedUrl !== null
                && (!isset($prov['source url']) || $prov['source url'] !== $expectedUrl)) {
                $errors[] = "{$package}: mirrored file {$mirrorRelative} provenance \"Source URL\" does not equal expected {$expectedUrl}.";
            }

            if ($bodyMirror !== $upstreamContent) {
                $errors[] = "{$package}: mirrored file {$mirrorRelative} content differs from upstream {$upstreamPath} (after stripping provenance comment).";
            }
        } else {
            // References must match exactly and carry no leading comment.
            if (str_starts_with($mirrorContent, '<!--')) {
                $errors[] = "{$package}: mirrored reference {$mirrorRelative} must not include a leading HTML provenance comment; references are exact upstream copies.";
            }
            if ($mirrorContent !== $upstreamContent) {
                $errors[] = "{$package}: mirrored reference {$mirrorRelative} content differs from upstream {$upstreamPath}.";
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 6. Cross-package SKILL.md YAML name collision detection + manifest
//    skill "name" must match installed upstream SKILL.md YAML name.
// ---------------------------------------------------------------------------

$skillUpstreamNames = []; // yaml name -> "pkg:path"
foreach ($manifestByPackage as $package => $source) {
    if (!isset($directRequires[$package])) {
        continue;
    }
    $packageRoot = vendorPackageRoot($vendorDir, $package);
    if ($packageRoot === null) {
        continue;
    }
    foreach (($source['skills'] ?? []) as $j => $skill) {
        if (!is_array($skill)) {
            continue;
        }
        $skillName = $skill['name'] ?? null;
        $skillMdPath = null;
        foreach (($skill['mirrored_files'] ?? []) as $p) {
            if (is_string($p) && basename($p) === 'SKILL.md') {
                $skillMdPath = $p;
                break;
            }
        }
        if ($skillMdPath === null) {
            continue; // shape error already emitted
        }
        $file = $packageRoot . '/' . $skillMdPath;
        if (!is_file($file)) {
            continue;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        $yamlName = extractSkillYamlName($content);
        if ($yamlName === null) {
            $errors[] = "{$package}: upstream {$skillMdPath} has no YAML frontmatter name: field.";
            continue;
        }
        if (is_string($skillName) && $skillName !== $yamlName) {
            $errors[] = "{$package}: manifest skills[{$j}].name ({$skillName}) does not match the upstream SKILL.md YAML name ({$yamlName}) in {$skillMdPath}.";
        }
        if (isset($skillUpstreamNames[$yamlName])) {
            $errors[] = "duplicate upstream SKILL.md YAML name \"{$yamlName}\" found in {$package}:{$skillMdPath} (already used by {$skillUpstreamNames[$yamlName]}); upgrade or drop one of the packages.";
        } else {
            $skillUpstreamNames[$yamlName] = "{$package}:{$skillMdPath}";
        }
    }
}

// ---------------------------------------------------------------------------
// 7. Attribution: MIRRORED-LICENSES.md must contain every source's
//    copyright string and license URL.
// ---------------------------------------------------------------------------

$licensesFile = $mirrorRoot . '/resources/boost/MIRRORED-LICENSES.md';
if (!is_file($licensesFile)) {
    $errors[] = 'missing resources/boost/MIRRORED-LICENSES.md; it must list every mirrored source\'s MIT license text and attribution.';
} else {
    $licensesText = file_get_contents($licensesFile);
    if ($licensesText === false) {
        $errors[] = 'unable to read resources/boost/MIRRORED-LICENSES.md';
    } else {
        foreach ($manifestByPackage as $package => $source) {
            $copyright = $source['copyright'] ?? null;
            $licenseUrl = $source['license_url'] ?? null;
            if (is_string($copyright) && !str_contains($licensesText, $copyright)) {
                $errors[] = "{$package}: MIRRORED-LICENSES.md does not contain the copyright string \"{$copyright}\"";
            }
            if (is_string($licenseUrl) && !str_contains($licensesText, $licenseUrl)) {
                $errors[] = "{$package}: MIRRORED-LICENSES.md does not contain the license URL {$licenseUrl}";
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 8. Archive: every expected mirrored destination (and the two metadata files)
//    must NOT have the export-ignore attribute set. Evaluate via real Git
//    attributes (`git check-attr`) so wildcards, overrides, and last-match
//    semantics are honoured. A missing Git context is a validation failure.
// ---------------------------------------------------------------------------

$pathsToCheck = array_unique(array_merge(
    array_keys($declaredDestinations),
    array_keys($orphanAllowed),
));
sort($pathsToCheck);

foreach ($pathsToCheck as $repoPath) {
    $result = gitCheckAttrExportIgnore($mirrorRoot, $repoPath);
    if (is_string($result)) {
        // Error message from Git.
        $errors[] = "unable to evaluate Git attributes for {$repoPath}: {$result}";
        continue;
    }
    if ($result === true) {
        $errors[] = "git check-attr reports export-ignore=set for mirrored asset {$repoPath}; remove the covering rule in .gitattributes so the file ships in package archives.";
    }
}

// ---------------------------------------------------------------------------
// Result
// ---------------------------------------------------------------------------

if ($errors !== []) {
    fwrite(STDERR, "boost-mirror: audit failed with " . count($errors) . " error(s):" . PHP_EOL);
    foreach ($errors as $e) {
        fwrite(STDERR, "  - {$e}" . PHP_EOL);
    }
    exit(1);
}

echo "boost-mirror: audit passed (" . count($manifestByPackage) . " sources mirrored, " . count($boostCapableDirect) . " Boost-capable direct dependencies)." . PHP_EOL;
exit(0);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * @return array{0:string,1:string,2:string,3:string,4:string}|string
 */
function parseArgs(array $argv): array|string
{
    $required = ['composer-json', 'composer-lock', 'vendor-dir', 'manifest', 'mirror-root'];
    $values = array_fill_keys($required, null);

    foreach (array_slice($argv, 1) as $arg) {
        if (!is_string($arg)) {
            continue;
        }
        if (!str_starts_with($arg, '--')) {
            return "unexpected positional argument: {$arg}";
        }
        $body = substr($arg, 2);
        $eq = strpos($body, '=');
        if ($eq === false) {
            if ($body === 'help' || $body === 'h') {
                continue;
            }
            return "option --{$body} requires a value (use --{$body}=PATH)";
        }
        $key = substr($body, 0, $eq);
        $value = substr($body, $eq + 1);
        if (!in_array($key, $required, true)) {
            return "unknown option: --{$key}";
        }
        if ($value === '') {
            return "option --{$key} must be a non-empty path";
        }
        $values[$key] = $value;
    }

    foreach ($required as $key) {
        if ($values[$key] === null) {
            return "missing required option --{$key}=PATH";
        }
    }

    return [
        $values['composer-json'],
        $values['composer-lock'],
        $values['vendor-dir'],
        $values['manifest'],
        $values['mirror-root'],
    ];
}

/**
 * @return array<array-key,mixed>
 */
function loadJsonObject(string $path, string $label): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "boost-mirror: unable to read {$label} at {$path}" . PHP_EOL);
        exit(2);
    }
    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "boost-mirror: {$label} at {$path} is not a valid JSON object: " . json_last_error_msg() . PHP_EOL);
        exit(2);
    }
    return $decoded;
}

/**
 * Platform requirement names that are not real packages.
 * Only exact matches plus ext-* / lib-* prefixes are ignored.
 * Arbitrary `composer/*` vendor prefixes are NOT ignored.
 */
function isPlatformRequirement(string $package): bool
{
    $lower = strtolower($package);
    if (in_array($lower, ['php', 'hhvm', 'composer', 'composer-plugin-api', 'composer-runtime-api'], true)) {
        return true;
    }
    if (str_starts_with($lower, 'ext-')) {
        return true;
    }
    if (str_starts_with($lower, 'lib-')) {
        return true;
    }
    return false;
}

function isNonEmptyString(mixed $v): bool
{
    return is_string($v) && $v !== '';
}

/**
 * Validate a forward-slash relative POSIX path. Returns null if valid, else
 * an error description.
 */
function validateRelativePath(string $path): ?string
{
    if ($path === '') {
        return 'must be a non-empty path';
    }
    if ($path[0] === '/') {
        return 'must not be an absolute path';
    }
    if (str_contains($path, '\\')) {
        return 'must not contain backslashes';
    }
    if (str_contains($path, '//')) {
        return 'must not contain empty segments';
    }
    foreach (explode('/', $path) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') {
            return 'must not contain "." or ".." segments';
        }
    }
    return null;
}

function pathIsUnder(string $path, string $directory): bool
{
    $directory = rtrim($directory, '/');
    return str_starts_with($path, $directory . '/');
}

function vendorPackageRoot(string $vendorDir, string $package): ?string
{
    $vendorDir = rtrim($vendorDir, '/');
    if (!str_contains($package, '/')) {
        return null;
    }
    $path = $vendorDir . '/' . $package;
    return is_dir($path) ? $path : null;
}

/**
 * @return list<string>
 */
function listRelativeFiles(string $dir, string $prefix): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        if (!$item->isFile()) {
            continue;
        }
        $relative = substr($item->getPathname(), strlen($dir));
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        $relative = ltrim($relative, '/');
        $out[] = $prefix . '/' . $relative;
    }
    sort($out);
    return $out;
}

/**
 * Detect Composer lock "versions" that are not immutable release tags:
 * dev branches, branch aliases, or anything with a `-dev`/`dev-` marker.
 */
function isNonImmutableVersion(string $version): bool
{
    $lower = strtolower($version);
    if (str_starts_with($lower, 'dev-')) {
        return true;
    }
    if (str_ends_with($lower, '-dev')) {
        return true;
    }
    if (str_ends_with($lower, '.x-dev')) {
        return true;
    }
    return false;
}

/**
 * Parse a leading HTML provenance comment from mirror file content.
 *
 * Returns [fields, body] or null when no leading comment exists.
 * Fields keys are lowercased ("upstream package", "upstream tag", "source url").
 *
 * @return array{0: array<string,string>, 1: string}|null
 */
function parseProvenanceComment(string $content): ?array
{
    if (!str_starts_with($content, '<!--')) {
        return null;
    }
    $end = strpos($content, '-->');
    if ($end === false) {
        return null;
    }
    $block = substr($content, 4, $end - 4);
    $fields = [];
    foreach (preg_split('/\r?\n/', $block) as $line) {
        $t = trim($line);
        if ($t === '') {
            continue;
        }
        if (preg_match('/\A([A-Za-z][A-Za-z ]*):\s*(.+)\z/', $t, $m)) {
            $key = strtolower(trim($m[1]));
            $fields[$key] = trim($m[2]);
        }
    }
    $body = substr($content, $end + 3);
    $body = preg_replace('/^(?:\r?\n)+/', '', $body) ?? '';
    return [$fields, $body];
}

/**
 * Build the raw.githubusercontent.com URL for an upstream source file given
 * the manifest source shape (repository_url + resolved_tag) and relative path.
 */
function buildRawSourceUrl(array $source, string $upstreamPath): ?string
{
    $repo = $source['repository_url'] ?? null;
    $tag = $source['resolved_tag'] ?? null;
    if (!is_string($repo) || !is_string($tag) || $repo === '' || $tag === '') {
        return null;
    }
    if (!preg_match('#\Ahttps://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)\z#', $repo, $m)) {
        return null;
    }
    return 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . $tag . '/' . $upstreamPath;
}

/**
 * Extract YAML `name:` from a SKILL.md frontmatter block.
 */
function extractSkillYamlName(string $content): ?string
{
    if (!str_starts_with($content, '---')) {
        return null;
    }
    if (!preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n/s', $content, $m)) {
        return null;
    }
    $frontmatter = $m[1];
    if (preg_match('/^name:\s*([^\r\n]+?)\s*$/m', $frontmatter, $nm)) {
        $value = trim($nm[1]);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2)
            || (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2)) {
            $value = substr($value, 1, -1);
        }
        return $value === '' ? null : $value;
    }
    return null;
}

/**
 * Register a mirror-relative destination path. Emits an error when another
 * source path already uses the same destination.
 */
function registerDestination(array &$map, string $dest, string $pkg, string $upstream, array &$errors): void
{
    if (isset($map[$dest])) {
        $prev = $map[$dest];
        $errors[] = "mirror destination {$dest} for {$pkg}:{$upstream} is already used by {$prev['package']}:{$prev['upstream_path']}; each mirror file must have a unique source.";
        return;
    }
    $map[$dest] = ['package' => $pkg, 'upstream_path' => $upstream];
}

/**
 * Track upstream source paths per package to catch duplicates.
 */
function registerSource(array &$map, string $pkg, string $upstream, array &$errors): void
{
    $key = $pkg . "\0" . $upstream;
    if (isset($map[$key])) {
        $errors[] = "manifest declares {$pkg}:{$upstream} more than once; each upstream source file must map to exactly one mirrored destination.";
        return;
    }
    $map[$key] = true;
}

/**
 * Evaluate `git check-attr export-ignore -- <path>` for the given repository
 * path using safe argv-based process invocation (no shell). Returns:
 *   true  — attribute is set
 *   false — attribute is unset or unspecified
 *   string — an error message describing why evaluation failed
 *
 * Uses `proc_open` with an argv array to avoid shell interpretation of the
 * values passed to git (no metacharacter injection via paths or cwd).
 */
function gitCheckAttrExportIgnore(string $mirrorRoot, string $repoPath): bool|string
{
    $argv = [
        'git',
        '-C',
        $mirrorRoot,
        'check-attr',
        'export-ignore',
        '--',
        $repoPath,
    ];

    $spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($argv, $spec, $pipes);
    if (!is_resource($process)) {
        return 'proc_open(git) failed (is git installed and on PATH?)';
    }

    // Close stdin so the child never blocks on input.
    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }

    $stdout = stream_get_contents($pipes[1]);
    if ($stdout === false) {
        $stdout = '';
    }
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    if ($stderr === false) {
        $stderr = '';
    }
    fclose($pipes[2]);

    $exit = proc_close($process);
    if ($exit !== 0) {
        $msg = trim($stderr) !== '' ? trim($stderr) : "git exited with status {$exit}";
        return $msg;
    }

    // Expected output shape: "<path>: export-ignore: <value>" where value is
    // "set", "unset", or "unspecified". Parse without a shell.
    $line = trim($stdout);
    if ($line === '') {
        return 'git check-attr returned empty output';
    }
    // Strip the path prefix we asked about to avoid quoting surprises.
    $sep = ': export-ignore: ';
    $pos = strpos($line, $sep);
    if ($pos === false) {
        return "unexpected git check-attr output: {$line}";
    }
    $value = substr($line, $pos + strlen($sep));
    return $value === 'set';
}

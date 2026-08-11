<?php

declare(strict_types=1);

/**
 * Reports the tz release this repository is built against, or the newest one upstream publishes.
 *
 * There is exactly one place the pinned release lives: `resources/tzdata-version.txt`, written by
 * the generators so it cannot describe a database they did not run against. The workflows read it at
 * run time rather than restating it, so there is no second copy to drift.
 *
 * That was not always true. The pin used to be written into three workflow files, which made moving
 * it a change to `.github/workflows/*` — and the default `GITHUB_TOKEN` is not allowed to push those,
 * so the automated bump could do everything except the last step. Single-sourcing it fixed the
 * permission problem and removed the invariant at the same time: two things that must agree are worse
 * than one thing.
 *
 * Usage:
 *   php tools/tzdata-release.php           the release the committed data describes
 *   php tools/tzdata-release.php --latest  the newest release PECL publishes
 *   php tools/tzdata-release.php --check   compare the two; exit 1 when a newer one exists
 */
$versionFile = dirname(__DIR__) . '/resources/tzdata-version.txt';
$pinned = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';

if ($pinned === '') {
    fwrite(STDERR, "resources/tzdata-version.txt is missing or empty. Run `laranail::chrono.sync`.\n");

    exit(1);
}

$wantsLatest = in_array('--latest', $argv, true);
$wantsCheck = in_array('--check', $argv, true);

if (! $wantsLatest && ! $wantsCheck) {
    fwrite(STDOUT, $pinned . "\n");

    exit(0);
}

$latest = @file_get_contents('https://pecl.php.net/rest/r/timezonedb/latest.txt');

if (! is_string($latest) || trim($latest) === '') {
    // Never guess. A bump that cannot read upstream should stop, not invent a version and
    // regenerate the whole catalogue against whatever the runner happened to have.
    fwrite(STDERR, "Could not reach PECL to read the newest timezonedb release.\n");

    exit(1);
}

$latest = trim($latest);

if ($wantsLatest) {
    fwrite(STDOUT, $latest . "\n");

    exit(0);
}

if ($latest === $pinned) {
    fwrite(STDOUT, "Pinned to tzdata {$pinned}, which is the newest release.\n");

    exit(0);
}

fwrite(STDOUT, "Pinned to tzdata {$pinned}; PECL publishes {$latest}.\n");

exit(1);

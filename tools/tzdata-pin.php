<?php

declare(strict_types=1);

/**
 * Reads and moves the tz database pin.
 *
 * The pin exists in two places that must agree: `resources/tzdata-version.txt`, written by the
 * generators to record the release the committed data describes, and a `timezonedb-<version>`
 * extension pin in each workflow that needs a real database. If they diverge, `composer sync-check`
 * either compares against the wrong baseline or stops running — and a gate that has stopped running
 * looks exactly like one that is passing.
 *
 * Keeping the rewrite here rather than in a shell step means the invariant is expressed once, is
 * readable, and fails loudly instead of leaving one file behind.
 *
 * Usage:
 *   php tools/tzdata-pin.php               print the current pin and whether the files agree
 *   php tools/tzdata-pin.php --latest      print the newest release PECL publishes
 *   php tools/tzdata-pin.php --set=2026.3  rewrite the workflow pins to that release
 */
$root = dirname(__DIR__);
$versionFile = $root . '/resources/tzdata-version.txt';

/** Every file carrying an extension pin. Kept explicit so a new workflow cannot silently miss one. */
$workflows = [
    $root . '/.github/workflows/tests.yml',
    $root . '/.github/workflows/static-analysis.yml',
    $root . '/.github/workflows/tzdata.yml',
];

$recorded = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';

if ($recorded === '') {
    fwrite(STDERR, "resources/tzdata-version.txt is missing or empty. Run `laranail::chrono.sync`.\n");

    exit(1);
}

// ── --latest: what PECL publishes ───────────────────────────────────────────────────────────

if (in_array('--latest', $argv, true)) {
    $latest = @file_get_contents('https://pecl.php.net/rest/r/timezonedb/latest.txt');

    if (! is_string($latest) || trim($latest) === '') {
        fwrite(STDERR, "Could not reach PECL. Not guessing — a bump run that cannot read upstream\n"
            . "should stop rather than invent a version.\n");

        exit(1);
    }

    fwrite(STDOUT, trim($latest) . "\n");

    exit(0);
}

// ── --set: move the pins ────────────────────────────────────────────────────────────────────

$set = null;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--set=')) {
        $set = substr($argument, 6);
    }
}

if ($set !== null) {
    if (preg_match('/^\d{4}\.\d+$/', $set) !== 1) {
        fwrite(STDERR, "Refusing to pin to '{$set}': a timezonedb release looks like 2026.3.\n");

        exit(1);
    }

    $changed = 0;

    foreach ($workflows as $workflow) {
        $contents = (string) file_get_contents($workflow);
        $rewritten = preg_replace('/timezonedb-\d{4}\.\d+/', 'timezonedb-' . $set, $contents);

        if (! is_string($rewritten)) {
            fwrite(STDERR, "Failed to rewrite {$workflow}.\n");

            exit(1);
        }

        if ($rewritten !== $contents) {
            file_put_contents($workflow, $rewritten);
            $changed++;
        }
    }

    fwrite(STDOUT, sprintf("Pinned %d workflow(s) to timezonedb-%s.\n", $changed, $set));

    exit(0);
}

// ── default: report, and fail when the two sources disagree ─────────────────────────────────

$mismatched = [];

foreach ($workflows as $workflow) {
    preg_match_all('/timezonedb-(\d{4}\.\d+)/', (string) file_get_contents($workflow), $matches);

    foreach (array_unique($matches[1]) as $pinned) {
        if ($pinned !== $recorded) {
            $mismatched[basename($workflow)] = $pinned;
        }
    }
}

fwrite(STDOUT, "Generated data describes tzdata {$recorded}.\n");

if ($mismatched !== []) {
    fwrite(STDERR, "\nThe workflow pins disagree with it:\n");

    foreach ($mismatched as $file => $pinned) {
        fwrite(STDERR, "  {$file} pins {$pinned}\n");
    }

    fwrite(STDERR, "\nCI would then compare the generated files against a database that is not the one\n"
        . "they describe, which sync-check treats as a failure — correctly, but late. Run\n"
        . "`php tools/tzdata-pin.php --set={$recorded}`.\n");

    exit(1);
}

fwrite(STDOUT, sprintf("All %d workflow pins agree.\n", count($workflows)));

exit(0);

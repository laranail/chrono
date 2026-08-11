<?php

declare(strict_types=1);

/**
 * CI gate: fails when any generated file disagrees with the runner's tz database.
 *
 * The IANA database changes several times a year, often by government decree and often without any
 * zone being added or removed. This is what catches that: it re-runs every generator in check mode
 * and compares byte for byte, so a stale enum or alias map cannot ship unnoticed.
 */
// Built `--with-system-tzdata` — the official Docker images, Debian, Ubuntu, and therefore most CI —
// PHP reads /usr/share/zoneinfo and `timezone_version_get()` returns the literal `0.system`. The data
// is fine, often fresher than the bundled copy, but it names no release, so comparing files
// generated on another machine byte for byte is not a check. It is a red build no commit caused.
//
// Drift is still caught: tzdata.yml runs weekly against a versioned host and opens an issue.
if (! str_contains(timezone_version_get(), '.') || str_starts_with(timezone_version_get(), '0.')) {
    fwrite(STDOUT, sprintf(
        "sync-check skipped: this host reads the OS tz database (timezone_version_get() = %s),\n"
        . "which carries no release to compare the generated files against.\n",
        timezone_version_get(),
    ));

    exit(0);
}

$generators = [
    'alias map' => __DIR__ . '/generate-alias-map.php',
    'enums' => __DIR__ . '/generate-enums.php',
];

$failed = [];

foreach ($generators as $label => $script) {
    $output = [];
    $exitCode = 0;

    exec(sprintf('%s %s --check 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script)), $output, $exitCode);

    if ($exitCode !== 0) {
        $failed[$label] = implode("\n", $output);

        continue;
    }

    fwrite(STDOUT, sprintf("  %-12s in sync\n", $label));
}

if ($failed !== []) {
    fwrite(STDERR, "\nGenerated data is stale.\n\n");

    foreach ($failed as $label => $output) {
        fwrite(STDERR, "  {$label}:\n" . preg_replace('/^/m', '    ', $output) . "\n");
    }

    fwrite(STDERR, sprintf(
        "\nThe runner reports tzdata %s (ICU %s, ICU tzdata %s).\nRun `laranail::chrono.sync` and commit the result.\n",
        timezone_version_get(),
        defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : 'n/a',
        class_exists('IntlTimeZone') ? (IntlTimeZone::getTZDataVersion() ?: 'n/a') : 'n/a',
    ));

    exit(1);
}

fwrite(STDOUT, sprintf("\nAll generated data matches tzdata %s.\n", timezone_version_get()));

exit(0);

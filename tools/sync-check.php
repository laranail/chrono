<?php

declare(strict_types=1);

/**
 * CI gate: fails when any generated file disagrees with the database it was generated against.
 *
 * The IANA database changes several times a year, often by government decree and often without any
 * zone being added or removed. This re-runs every generator in check mode and compares byte for
 * byte, so a stale enum or alias map cannot ship unnoticed.
 *
 * The comparison is only meaningful on a host carrying the *same* release the files were generated
 * against — `resources/tzdata-version.txt`, written by the generators themselves. CI pins the PECL
 * `timezonedb` to that release, which is what turns this from a check that is skipped into one that
 * runs.
 *
 * Two different failures, deliberately not conflated:
 *
 *   - the data is stale        the generators produce something else on the right database
 *   - the host cannot tell     the database is not the one the files describe
 *
 * The second is fatal in CI and merely reported elsewhere. In CI it means the pin has broken and the
 * gate is not running; on a contributor's laptop it means their PHP ships a different release, which
 * is normal and not their problem to fix.
 */
$expectedFile = dirname(__DIR__) . '/resources/tzdata-version.txt';
$expected = is_file($expectedFile) ? trim((string) file_get_contents($expectedFile)) : '';
$actual = timezone_version_get();
$inCi = getenv('CI') !== false && getenv('CI') !== '' && getenv('CI') !== 'false';

if ($expected === '') {
    fwrite(STDERR, "resources/tzdata-version.txt is missing. Run `laranail::chrono.sync`.\n");

    exit(1);
}

if ($actual !== $expected) {
    $message = sprintf(
        "This host carries tzdata %s; the generated files were built against %s.\n"
        . "Byte-for-byte comparison across two releases reports a difference that no commit caused,\n"
        . "so it is not run here.\n",
        $actual === '0.system' ? '0.system (the OS database, which names no release)' : $actual,
        $expected,
    );

    if ($inCi) {
        fwrite(STDERR, "sync-check cannot run, and in CI that is a failure.\n\n" . $message
            . "\nCI pins the PECL timezonedb to " . $expected . ". If that pin no longer resolves,\n"
            . "the gate is silently not running — fix the pin, or regenerate against a newer release\n"
            . "and move the pin with it.\n");

        exit(1);
    }

    fwrite(STDOUT, "sync-check skipped.\n\n" . $message
        . "\nThis is normal on a machine whose PHP bundles a different release; CI runs the real\n"
        . 'check against ' . $expected . ".\n");

    exit(0);
}

fwrite(STDOUT, sprintf("Checking against tzdata %s, the release the files were generated on.\n", $expected));

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

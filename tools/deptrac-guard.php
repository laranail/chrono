<?php

declare(strict_types=1);

/**
 * Runs deptrac and fails on anything that would leave the architecture boundary unenforced.
 *
 * deptrac does not exit non-zero when it cannot parse a file: version 3.0.0 printed
 * "Syntax Error on File ... unexpected ','" for a PHP 8.5 `clone ($this, [...])` expression, then
 * reported "Violations 0 / Errors 0" and exited 0. CI would have gone green while `src/Core` was
 * free to import Illuminate. We pin deptrac ^4.7, which parses the syntax correctly — this guard
 * exists so the same class of gap cannot reappear silently with a future language feature.
 *
 * Fails when: deptrac exits non-zero · any violation is reported · any error is reported ·
 * the output mentions a syntax error · the JSON report cannot be read at all.
 */
$root = dirname(__DIR__);
$binary = $root . '/vendor/bin/deptrac';

if (! is_file($binary)) {
    fwrite(STDERR, "deptrac is not installed; run composer install.\n");

    exit(1);
}

$command = sprintf(
    '%s analyse --config-file=%s --no-progress --formatter=json 2>&1',
    escapeshellarg($binary),
    escapeshellarg($root . '/deptrac.yaml'),
);

exec($command, $lines, $exitCode);
$output = implode("\n", $lines);

$fail = static function (string $reason) use ($output): never {
    fwrite(STDERR, "deptrac guard failed: {$reason}\n\n{$output}\n");

    exit(1);
};

if (stripos($output, 'syntax error') !== false) {
    $fail('deptrac could not parse at least one file, so the boundary was not enforced');
}

// The JSON formatter prints the report object; anything else means deptrac aborted.
$decoded = json_decode($output, true);

if (! is_array($decoded) || ! isset($decoded['Report']) || ! is_array($decoded['Report'])) {
    $fail('deptrac produced no readable JSON report');
}

$report = $decoded['Report'];
$violations = (int) ($report['Violations'] ?? 0);
$errors = (int) ($report['Errors'] ?? 0);

foreach (($decoded['files'] ?? []) as $file => $detail) {
    foreach (($detail['messages'] ?? []) as $message) {
        fwrite(STDERR, sprintf("%s:%s  %s\n", $file, $message['line'] ?? '?', $message['message'] ?? ''));
    }
}

if ($violations > 0) {
    $fail("{$violations} architecture violation(s)");
}

if ($errors > 0) {
    $fail("{$errors} error(s)");
}

if ($exitCode !== 0) {
    $fail("deptrac exited with code {$exitCode}");
}

fwrite(STDOUT, "deptrac: boundary clean (0 violations, 0 errors).\n");

exit(0);

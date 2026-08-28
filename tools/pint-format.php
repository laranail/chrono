<?php

declare(strict_types=1);

/**
 * Format generated PHP through the same Pint config the repository lints with.
 *
 * Generated files are compared byte-for-byte against what the generator produces.
 * That check and a formatter are in direct conflict unless the generator emits
 * the formatter's output: Pint rewrites the file, the comparison then fails, and
 * the suite reports the package as out of sync with tzdata when nothing about
 * tzdata changed.
 *
 * Running the rendered text through Pint *before* both writing and comparing
 * removes the conflict at the source. The generator's output is Pint-clean by
 * construction, so the two can never disagree.
 *
 * Falls back to the unformatted text when Pint is unavailable (no vendor/ yet,
 * or a sandbox that blocks the local socket its parallel mode opens). A missing
 * formatter must not stop a regeneration; the worst case is the same behaviour
 * this had before.
 */
function laranail_pint_format(string $contents): string
{
    $root = dirname(__DIR__);
    $pint = $root . '/vendor/bin/pint';
    $config = $root . '/vendor/laranail/package-tools/pint.json';

    if (! is_file($pint) || ! is_file($config)) {
        return $contents;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'laranail-gen-') ?: null;
    if ($tmp === null) {
        return $contents;
    }

    // Pint only inspects files it recognises as PHP.
    $file = $tmp . '.php';
    rename($tmp, $file);

    try {
        file_put_contents($file, $contents);

        $command = sprintf(
            '%s --config %s %s 2>/dev/null',
            escapeshellarg($pint),
            escapeshellarg($config),
            escapeshellarg($file),
        );
        exec($command, $output, $status);

        $formatted = file_get_contents($file);

        return is_string($formatted) && $formatted !== '' ? $formatted : $contents;
    } finally {
        @unlink($file);
    }
}

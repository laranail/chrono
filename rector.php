<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Expression\RemoveDeadStmtRector;
use Rector\Set\ValueObject\SetList;

/**
 * Pinned to the php85 set, unlike the rest of the laranail family which pins php83.
 *
 * This package's floor is ^8.5 and its value objects use `clone ($this, [...])` and
 * `#[\NoDiscard]` throughout; the php83 set would fight that syntax on every file. Recorded as a
 * deliberate divergence in docs/architecture.md.
 *
 * Generated files are skipped: a byte-for-byte sync test asserts they match what the generator
 * emits, so a Rector rewrite would put the two permanently at odds.
 */
return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
    ->withSkip([
        __DIR__.'/vendor',
        __DIR__.'/tests/Fixtures',
        __DIR__.'/src/Core/Enums/Timezone.php',
        __DIR__.'/src/Core/Enums/TimezoneLegacy.php',
        __DIR__.'/src/Core/Enums/TimezoneAbbreviation.php',
        __DIR__.'/src/Core/Enums/Tz.php',
        __DIR__.'/src/Core/Timezone/Support/AliasMap.php',
        __DIR__.'/src/Core/Timezone/Support/CountryZones.php',
        __DIR__.'/src/Enums/TimezoneEnum.php',
        // `(void)` is how PHP 8.5 tells #[\NoDiscard] "I meant to discard this". Rector's
        // dead-code set sees a statement whose value is unused and deletes the whole line,
        // turning a deliberate discard back into a warning — and phpunit.xml fails the build
        // on warnings. Tests are where deliberate discards live, so the rule is skipped there.
        RemoveDeadStmtRector::class => [__DIR__.'/tests'],
    ])
    ->withPhpSets(php85: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ])
    ->withImportNames(removeUnusedImports: true);

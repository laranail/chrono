<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Console;

use Override;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Regenerate the enums and the alias map from this host's tz database.
 *
 * `--check` writes nothing and exits non-zero on drift, which is what CI runs. The generators are
 * plain scripts so they work without a booted framework; this only wraps them.
 */
final class SyncCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    #[Override]
    protected array $commandAliases = ['chrono:sync'];

    #[Override]
    protected $signature = 'laranail::chrono.sync {--check : Report drift without writing}';

    #[Override]
    protected $description = 'Regenerate the generated timezone enums and alias map from live tzdata.';

    public function handle(): int
    {
        $root = dirname(__DIR__, 2);
        $check = (bool) $this->option('check');
        $failed = false;

        foreach (['generate-alias-map.php', 'generate-enums.php'] as $script) {
            $path = $root . '/tools/' . $script;

            if (! is_file($path)) {
                $this->components->error("Missing generator: {$script}");

                return self::FAILURE;
            }

            $output = [];
            $exitCode = 0;

            exec(sprintf(
                '%s %s%s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($path),
                $check ? ' --check' : '',
            ), $output, $exitCode);

            foreach ($output as $line) {
                $exitCode === 0 ? $this->line('  ' . $line) : $this->components->error($line);
            }

            $failed = $failed || $exitCode !== 0;
        }

        if ($failed) {
            $this->components->error('Generated data is out of sync. Run without --check to regenerate.');

            return self::FAILURE;
        }

        $this->components->info($check ? 'Generated data is in sync.' : 'Regenerated.');

        return self::SUCCESS;
    }
}

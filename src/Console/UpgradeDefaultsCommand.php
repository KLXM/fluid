<?php

namespace Klxm\Fluid\Console;

use Klxm\Fluid\DefaultChanges;
use Redaxo\Core\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;

/**
 * Writes changed defaults of block fields into stored blocks (Klxm\Fluid\DefaultChanges). Also runs on
 * `console migrate`; here for checking (--dry-run) or for projects that don't run migrate.
 *
 * @internal
 */
#[AsCommand(name: 'fluid:upgrade:defaults', description: 'Existing blocks keep their look when defaults of block fields change (also runs on migrate)')]
final class UpgradeDefaultsCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only count, write nothing')] bool $dryRun = false,
    ): int {
        $pending = DefaultChanges::pending();
        if (!$pending) {
            $io->success('No pending changes (' . count(DefaultChanges::changes()) . ' done).');

            return Command::SUCCESS;
        }
        foreach (DefaultChanges::run($dryRun) as $line) {
            $io->text($line);
        }
        $io->success($dryRun ? 'Dry run – nothing written.' : 'Done.');

        return Command::SUCCESS;
    }
}

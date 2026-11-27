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
 * Geänderte Standardwerte von Blockfeldern in gespeicherte Blöcke schreiben (Klxm\Fluid\DefaultChanges). Läuft auch bei
 * `console migrate`; hier zum Nachsehen (--dry-run) oder für Projekte, die migrate nicht ausführen.
 */
#[AsCommand(name: 'fluid:upgrade:defaults', description: 'Bestehende Blöcke behalten ihr Aussehen, wenn sich Standardwerte von Blockfeldern ändern (läuft auch bei migrate)')]
final class UpgradeDefaultsCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nur zählen, nichts schreiben')] bool $dryRun = false,
    ): int {
        $pending = DefaultChanges::pending();
        if (!$pending) {
            $io->success('Keine offenen Änderungen (' . count(DefaultChanges::changes()) . ' erledigt).');

            return Command::SUCCESS;
        }
        foreach (DefaultChanges::run($dryRun) as $line) {
            $io->text($line);
        }
        $io->success($dryRun ? 'Probelauf – nichts geschrieben.' : 'Fertig.');

        return Command::SUCCESS;
    }
}

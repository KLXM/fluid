<?php

namespace Klxm\Fluid\Console;

use Klxm\Fluid\BlockSelfTest;
use Klxm\Fluid\EditLock;
use Klxm\Fluid\Live;
use Klxm\Fluid\Presence;
use Redaxo\Core\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fluid:selftest', description: 'Prüft Live-Aktualisierung, Präsenz der Redaktion, Bearbeitungssperre und Blöcke (Views, Standardwerte, Video-Schalter …)')]
final class SelftestCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $fails = 0;
        foreach (['Live' => Live::selftest(...), 'Präsenz' => Presence::selftest(...), 'Sperre' => EditLock::selftest(...), 'Blöcke' => BlockSelfTest::selftest(...)] as $name => $test) {
            $r = $test();
            $fails += \count($r['fails']);
            $io->writeln(sprintf('%s: %d in Ordnung%s', $name, $r['ok'], $r['fails'] ? ', ' . \count($r['fails']) . ' Fehler' : ''));
            foreach ($r['fails'] as $f) {
                $io->writeln('  ✗ ' . $f);
            }
        }
        if ($fails) {
            $io->error('Selbsttest fehlgeschlagen.');

            return Command::FAILURE;
        }
        $io->success('Alles in Ordnung.');

        return Command::SUCCESS;
    }
}

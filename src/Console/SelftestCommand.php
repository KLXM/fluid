<?php

namespace Klxm\Fluid\Console;

use Klxm\Fluid\BlockSelfTest;
use Klxm\Fluid\EditLock;
use Klxm\Fluid\FeatureSelfTest;
use Klxm\Fluid\Live;
use Klxm\Fluid\Presence;
use Redaxo\Core\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

/** @internal */
#[AsCommand(name: 'fluid:selftest', description: 'Checks live updates, editor presence, edit lock, blocks (views, defaults, video toggle …), permissions and list templates')]
final class SelftestCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $fails = 0;
        foreach (['Live' => Live::selftest(...), 'Presence' => Presence::selftest(...), 'Lock' => EditLock::selftest(...), 'Blocks' => BlockSelfTest::selftest(...)] + FeatureSelfTest::groups() as $name => $test) {
            $r = $test();
            $fails += count($r['fails']);
            $io->writeln(sprintf('%s: %d OK%s', $name, $r['ok'], $r['fails'] ? ', ' . count($r['fails']) . ' failed' : ''));
            foreach ($r['fails'] as $f) {
                $io->writeln('  ✗ ' . $f);
            }
        }
        if ($fails) {
            $io->error('Self-test failed.');

            return Command::FAILURE;
        }
        $io->success('All OK.');

        return Command::SUCCESS;
    }
}

<?php

namespace Klxm\Fluid\Console;

use Klxm\Fluid\Search\Search;
use Redaxo\Core\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @internal */
#[AsCommand(name: 'fluid:search-index', description: 'Rebuilds the website search index (Loupe, all languages)')]
final class SearchIndexCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(Search::rebuild() . ' pages in the search index.');

        return Command::SUCCESS;
    }
}

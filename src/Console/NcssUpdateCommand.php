<?php

namespace Klxm\Fluid\Console;

use Klxm\Fluid\Kit;
use Klxm\Fluid\Kit\Native\NcssUpdate;
use Redaxo\Core\Console\AbstractCommand;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;

/**
 * Updates NativeCSS in the kit „NativeCSS“ (kits/native/assets/ncss) from a working copy (--from) or a GitHub
 * release/tag (--tag) – shows beforehand what changes. For working on fluid: in the installed project the kit lives
 * under vendor/ (Composer overwrites it on the next update), so write into fluid's working copy with --target or
 * use `php tools/ncss-update.php` in fluid's repo.
 *
 * @internal
 */
#[AsCommand(name: 'fluid:kit:ncss-update', description: 'Updates NativeCSS in the "NativeCSS" kit from a working copy (--from) or from GitHub (--tag)')]
final class NcssUpdateCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Working copy of NativeCSS (folder with dist/)')] string $from = '',
        #[Option(description: 'with --from: only take this Git revision (e.g. HEAD, v1.2.0) – without uncommitted changes')] string $ref = '',
        #[Option(description: 'Release, tag or branch of github.com/skerbis/nativecss (instead of --from)')] string $tag = '',
        #[Option(description: 'Assets folder of the kit (default: kits/native/assets of this fluid)')] string $target = '',
        #[Option(description: 'only show, write nothing')] bool $dryRun = false,
    ): int {
        $target = '' !== $target ? rtrim($target, '/') : (Kit::get('native')?->path('assets') ?? '');
        $code = self::update($io, $from, $ref, $tag, $target, $dryRun);
        if (Command::SUCCESS === $code && !$dryRun && str_contains($target, '/vendor/')) {
            $io->note('Written below vendor/ – the next composer update removes it again. For fluid itself: --target=/path/to/fluid/kits/native/assets');
        }

        return $code;
    }

    private static function update(SymfonyStyle $io, string $from, string $ref, string $tag, string $target, bool $dryRun): int
    {
        if (('' === $from) === ('' === $tag)) {
            $io->error('Please give exactly one source: --from=/path/to/nativecss or --tag=v1.2.0');

            return Command::INVALID;
        }
        if ('' === $target || !is_dir($target . '/ncss')) {
            $io->error('Target not found (assets folder of the "NativeCSS" kit with ncss/): ' . $target);

            return Command::FAILURE;
        }
        try {
            $up = new NcssUpdate($target);
            '' !== $from ? $up->fromDirectory($from, $ref) : $up->fromGithub($tag);
            $plan = $up->plan();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $info = $up->info();
        $io->title('NativeCSS ' . $info['version'] . ('' !== $info['commit'] && $info['commit'] !== $info['version'] ? ' (' . $info['commit'] . ')' : '') . ' of ' . $info['date']);
        $io->text(['Source: ' . $info['source'], 'Target: ' . $target . '/ncss']);
        if ($info['dirty']) {
            $io->warning('The working copy has uncommitted changes in dist/. Use --ref=HEAD to take only the committed state.');
        }
        $io->table(['', 'Files'], [
            ['new', (string) count($plan['added'])], ['changed', (string) count($plan['changed'])],
            ['removed', (string) count($plan['removed'])], ['unchanged', (string) $plan['unchanged']],
        ]);
        foreach (['added' => 'New', 'changed' => 'Changed', 'removed' => 'Removed'] as $k => $label) {
            if ($plan[$k]) {
                $io->section($label);
                $io->listing($plan[$k]);
            }
        }
        $io->text(count($plan['manifest']) . ' files in the manifest css/ncss-base.css; loaded on demand: ' . implode(', ', array_map('basename', NcssUpdate::ON_DEMAND)) . '.');
        if ($dryRun) {
            $io->note('Dry run – nothing written.');

            return Command::SUCCESS;
        }
        $up->apply();
        $io->success('NativeCSS updated. The kit\'s own files (css/, views/, template) are unchanged – please check the pages in the "NativeCSS" kit.');

        return Command::SUCCESS;
    }
}

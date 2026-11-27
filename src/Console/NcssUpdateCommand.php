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
 */
#[AsCommand(name: 'fluid:kit:ncss-update', description: 'Aktualisiert NativeCSS im Kit „NativeCSS“ aus einer Arbeitskopie (--from) oder von GitHub (--tag)')]
final class NcssUpdateCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Arbeitskopie von NativeCSS (Ordner mit dist/)')] string $from = '',
        #[Option(description: 'mit --from: nur diesen Git-Stand übernehmen (z. B. HEAD, v1.2.0) – ohne nicht committete Änderungen')] string $ref = '',
        #[Option(description: 'Release, Tag oder Branch von github.com/skerbis/nativecss (statt --from)')] string $tag = '',
        #[Option(description: 'Ordner assets des Kits (Standard: kits/native/assets dieses fluid)')] string $target = '',
        #[Option(description: 'nur anzeigen, nichts schreiben')] bool $dryRun = false,
    ): int {
        $target = '' !== $target ? rtrim($target, '/') : (Kit::get('native')?->path('assets') ?? '');
        $code = self::update($io, $from, $ref, $tag, $target, $dryRun);
        if (Command::SUCCESS === $code && !$dryRun && str_contains($target, '/vendor/')) {
            $io->note('Geschrieben unter vendor/ – beim nächsten composer update ist es wieder weg. Für fluid selbst: --target=/pfad/zu/fluid/kits/native/assets');
        }

        return $code;
    }

    private static function update(SymfonyStyle $io, string $from, string $ref, string $tag, string $target, bool $dryRun): int
    {
        if (('' === $from) === ('' === $tag)) {
            $io->error('Bitte genau eine Quelle angeben: --from=/pfad/zu/nativecss oder --tag=v1.2.0');

            return Command::INVALID;
        }
        if ('' === $target || !is_dir($target . '/ncss')) {
            $io->error('Ziel nicht gefunden (Ordner assets des Kits „NativeCSS“ mit ncss/): ' . $target);

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
        $io->title('NativeCSS ' . $info['version'] . ('' !== $info['commit'] && $info['commit'] !== $info['version'] ? ' (' . $info['commit'] . ')' : '') . ' vom ' . $info['date']);
        $io->text(['Quelle: ' . $info['source'], 'Ziel:   ' . $target . '/ncss']);
        if ($info['dirty']) {
            $io->warning('Die Arbeitskopie hat nicht committete Änderungen in dist/. Mit --ref=HEAD nur den committeten Stand übernehmen.');
        }
        $io->table(['', 'Dateien'], [
            ['neu', (string) count($plan['added'])], ['geändert', (string) count($plan['changed'])],
            ['entfernt', (string) count($plan['removed'])], ['unverändert', (string) $plan['unchanged']],
        ]);
        foreach (['added' => 'Neu', 'changed' => 'Geändert', 'removed' => 'Entfernt'] as $k => $label) {
            if ($plan[$k]) {
                $io->section($label);
                $io->listing($plan[$k]);
            }
        }
        $io->text(count($plan['manifest']) . ' Dateien im Manifest css/ncss-base.css; bei Bedarf geladen: ' . implode(', ', array_map('basename', NcssUpdate::ON_DEMAND)) . '.');
        if ($dryRun) {
            $io->note('Probelauf – nichts geschrieben.');

            return Command::SUCCESS;
        }
        $up->apply();
        $io->success('NativeCSS übernommen. Eigene Dateien des Kits (css/, views/, template) sind unverändert – bitte die Seiten im Kit „NativeCSS“ ansehen.');

        return Command::SUCCESS;
    }
}

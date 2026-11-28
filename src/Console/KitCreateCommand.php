<?php

namespace Klxm\Fluid\Console;

use Klxm\Fluid\Kit;
use Redaxo\Core\Cache;
use Redaxo\Core\Console\AbstractCommand;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_array;
use function is_dir;
use function is_file;

/**
 * Creates a custom kit in the project (`src/Kit/{Name}/`) that inherits from an existing kit.
 *
 * The new kit initially brings only its own stylesheet; everything else comes from the parent kit. With
 * --view=hero,cards and --template single files of the parent kit are copied for customising.
 *
 * @internal
 */
#[AsCommand(name: 'fluid:kit:create', description: 'Creates a custom kit in the project that inherits from a kit (default: fluid)')]
final class KitCreateCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Key of the kit, e.g. nature (lowercase letters, digits, hyphen)')] string $key,
        #[Option(description: 'Name in the kit selection, e.g. "Nature"')] string $label = '',
        #[Option(description: 'Parent kit the new kit inherits from')] string $parent = Kit::DEFAULT,
        #[Option(description: 'Copy views of the parent kit for customising, e.g. --view=hero,cards')] string $view = '',
        #[Option(description: 'Copy the page frame (template.view.php) for customising')] bool $template = false,
    ): int {
        if (!preg_match('~^[a-z][a-z0-9-]{1,30}$~', $key)) {
            $io->error('Key: lowercase letters, digits and hyphen, starting with a letter (2–31 characters).');

            return Command::FAILURE;
        }
        if (null !== Kit::get($key)) {
            $io->error('A kit "' . $key . '" already exists.');

            return Command::FAILURE;
        }
        $parentKit = Kit::get($parent);
        if (null === $parentKit) {
            $io->error('Parent kit "' . $parent . '" does not exist (' . implode(', ', array_keys(Kit::all())) . ').');

            return Command::FAILURE;
        }

        [$namespace, $srcDir] = self::projectSource();
        $name = str_replace(' ', '', ucwords(str_replace('-', ' ', $key)));
        $dir = $srcDir . '/Kit/' . $name;
        if (is_dir($dir)) {
            $io->error('The folder ' . Path::relative($dir) . ' already exists.');

            return Command::FAILURE;
        }
        $label = '' !== $label ? $label : $name;
        $class = $name . 'Kit';
        $ns = $namespace . 'Kit\\' . $name;

        File::put($dir . '/' . $class . '.php', <<<PHP
            <?php

            namespace {$ns};

            use Klxm\\Fluid\\AsFluidKit;
            use Klxm\\Fluid\\Kit;

            /**
             * Kit "{$label}" – inherits from "{$parentKit->label()}". Files in this folder replace those of the parent kit:
             * kit.php (definition), views/{type}.php, template.view.php, functions.php, assets/.
             */
            #[AsFluidKit('{$key}', '{$this->quote($label)}', parent: '{$parentKit->key()}')]
            final class {$class} extends Kit {}

            PHP);

        File::put($dir . '/kit.php', <<<PHP
            <?php

            /*
             * Kit "{$label}": definition. \$parent is the definition of the parent kit "{$parentKit->key()}" – anything missing
             * here applies from there. Backgrounds and 'assets' are merged per key, 'css' is appended. The blocks' fields
             * live in their classes; 'blocks' extends them per block type (variants are added, fields are appended).
             *
             * Examples:
             *   'blocks' => ['hero' => ['variants' => ['custom' => 'Custom variant']]],   // view: views/hero.php
             *   'backgrounds' => ['sand' => 'Sand'],
             *   'design' => \$parent['design'],   // style editor; own presets under ['presets']
             */

            return [
                'label' => '{$this->quote($label)}',
                // Stylesheets for every page – after the base and block CSS of the parent kit
                'css' => ['css/kit.css'],
            ];

            PHP);

        File::put($dir . '/assets/css/kit.css', <<<CSS
            /*
             * Kit "{$label}" – own CSS. Loaded after the CSS of "{$parentKit->label()}" (including that of the blocks) and
             * can override it. A file of the same name here replaces a single file of the parent kit (e.g. css/b-cards.css).
             * Tip: editors set colours and fonts in the style editor (Design) – this is the place for what the kit does
             * fundamentally differently. Relative paths (url(…)) point to this kit's assets/.
             */

            CSS);

        $copied = [];
        foreach (array_filter(array_map('trim', explode(',', $view))) as $type) {
            $type = (string) preg_replace('~[^a-z0-9_]~', '', $type);
            $source = $parentKit->file('views/' . $type . '.php');
            if (null === $source) {
                $io->warning('No view "' . $type . '" in the parent kit.');
                continue;
            }
            File::copy($source, $dir . '/views/' . $type . '.php');
            $copied[] = 'views/' . $type . '.php';
        }
        if ($template) {
            $source = $parentKit->file('template.view.php') ?? $parentKit->path('src/FluidTemplate.view.php');
            if (is_file($source)) {
                File::copy($source, $dir . '/template.view.php');
                $copied[] = 'template.view.php';
            }
        }

        // Re-read classes (ClassDiscovery) so the kit appears in the selection right away
        Cache::delete();

        $io->success('Kit "' . $label . '" created: ' . Path::relative($dir));
        $io->listing(array_merge([
            $class . '.php – registration (#[AsFluidKit])',
            'kit.php – definition (inherits from "' . $parentKit->key() . '")',
            'assets/css/kit.css – own CSS',
        ], $copied));
        $io->text([
            'Next steps:',
            '  1. Website settings → tab "Kit" → choose "' . $label . '".',
            '  2. Adjust assets/css/kit.css; copy more views with --view=type,type or create them by hand.',
            '  3. On the server: deploy the folder, then run `bin/console migrate` (publishes the assets).',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Namespace and folder for the project's `src/` (from its composer.json, PSR-4).
     *
     * @return array{string, string}
     */
    private static function projectSource(): array
    {
        $file = Path::base('composer.json');
        $json = is_file($file) ? json_decode((string) File::get($file), true) : null;
        $psr4 = is_array($json) ? ($json['autoload']['psr-4'] ?? []) : [];
        foreach ((array) $psr4 as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                if ('src' === rtrim((string) $path, '/')) {
                    return [(string) $namespace, Path::base('src')];
                }
            }
        }

        return ['Project\\', Path::base('src')];
    }

    private function quote(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}

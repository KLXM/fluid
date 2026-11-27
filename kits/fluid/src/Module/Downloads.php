<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\MediaCategory;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\DownloadsFile;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.downloads', 'Downloads')]
#[AsBlock(
    icon: 'download-simple',
    group: 'Medien',
    help: 'Dateien aus dem Medienpool zum Herunterladen; PDFs auf Wunsch auch zum Ansehen. Größe und Typ erscheinen automatisch.',
    nestable: true,
)]
final class Downloads extends FluidBlock
{
    #[Text(
        'Dachzeile (optional)',
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        'Überschrift',
        maxLength: 110,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        'Einleitung (optional)',
        maxLength: 400,
        rows: 2,
    )]
    public string $intro = '';

    #[Choice(
        'Quelle',
        required: true,
        choices: ['manual' => 'Einzelne Dateien auswählen', 'collection' => 'Alle Dateien einer Medienkategorie'],
        width: 'half',
    )]
    public string $source = 'manual';

    #[MediaCategory(
        'Medienkategorie',
        width: 'half',
    )]
    public string $collection = '';

    #[Collection(
        'Dateien (bei „Einzelne Dateien“)',
        item: DownloadsFile::class,
        itemLabel: 'Datei',
        titleField: 'label',
    )]
    public array $files = [];

    #[Checkbox('PDFs zusätzlich „Ansehen“ anbieten (öffnet im Browser)')]
    public bool $showViewer = true;
}

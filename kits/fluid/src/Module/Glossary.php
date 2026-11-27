<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.glossary', 'Glossar')]
#[AsBlock(
    icon: 'book-open',
    group: 'Daten',
    help: 'Alle Begriffe des Glossars von A bis Z mit Suche. Die Seite mit diesem Block ist die Übersicht; jeder Begriff hat darunter eine eigene Adresse, z. B. /glossar/begriff. Begriffe pflegen: YForm-Tabelle „Glossar“ oder im Bearbeiten-Modus über ⋯ → Glossar.',
    requires: 'yform',
)]
final class Glossary extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Checkbox('Suchfeld anzeigen', width: 'half')]
    public bool $search = true;

    #[Checkbox('Buchstaben A–Z anzeigen', width: 'half')]
    public bool $letters = true;
}

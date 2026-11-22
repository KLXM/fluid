<?php

namespace Klxm\Fluid\Glossary;

use Klxm\Fluid\Data\FluidBlocks;
use Klxm\Fluid\Data\FluidLanguage;
use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

// Nur mit YForm (fluid läuft auch ohne)
if (!class_exists(Table::class)) {
    return;
}

/**
 * Glossar der Website – EINE YForm-Tabelle (`console migrate` legt sie an): Begriff, Schreibweisen, Kurztext (Hinweisfenster)
 * und Text (ausführliche Erklärung aus Blöcken, Detailseite). Begriffe werden auf den Seiten automatisch markiert, wenn es
 * unter Website → Glossar eingeschaltet ist (Klxm\Fluid\Glossary\Glossary).
 *
 * Eigene Felder: Klasse ableiten ist nicht nötig – ein Projekt kann mit Glossary::$store eine ganz andere Quelle setzen.
 */
#[AsTable('rex_fluid_glossary', label: 'Glossar', icon: 'fa-book')]
final class GlossaryTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('term')->label('Begriff')->required()->maxLength(120)->filterable();
        yield Textarea::new('variants')->label('Schreibweisen')->rows(2)
            ->notice('Eine je Zeile oder mit Komma getrennt, z. B. Mehrzahl oder Abkürzung. "In Anführungszeichen" = nur genau diese Schreibweise.');
        yield Textarea::new('short')->label('Kurztext')->required()->rows(3)
            ->notice('Klartext für das Hinweisfenster, höchstens ' . Glossary::SHORT_MAX . ' Zeichen.');
        yield FluidBlocks::new('text')->label('Text (ausführliche Erklärung, Detailseite)')->blocks('richtext', 'media_text', 'quote', 'faq', 'video');
        yield Checkbox::new('online')->label('Online')->default(true)->filterable();
        yield FluidLanguage::new('lang');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'term', 'short', 'online', 'lang')->sortBy('term', 'asc');
    }
}

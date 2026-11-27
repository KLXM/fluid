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

// Only with YForm (fluid also runs without)
if (!class_exists(Table::class)) {
    return;
}

/**
 * The website's glossary – ONE YForm table (`console migrate` creates it): term, spellings, short text (tooltip)
 * and text (detailed explanation from blocks, detail page). Terms are marked automatically on the pages when this is
 * enabled under Website → Glossar (Klxm\Fluid\Glossary\Glossary).
 *
 * Custom fields: no need to extend the class – a project can set an entirely different source with Glossary::$store.
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

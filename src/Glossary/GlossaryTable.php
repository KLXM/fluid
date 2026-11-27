<?php

namespace Klxm\Fluid\Glossary;

use Klxm\Fluid\Data\FluidBlocks;
use Klxm\Fluid\Data\FluidLanguage;
use Redaxo\Core\Translation\I18n;
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
#[AsTable('rex_fluid_glossary', label: 'translate:fluid_glossary_tab', icon: 'fa-book')]
final class GlossaryTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('term')->label('translate:fluid_glossary_field_term')->required()->maxLength(120)->filterable();
        yield Textarea::new('variants')->label('translate:fluid_glossary_field_variants')->rows(2)
            ->notice(I18n::rawMsg('fluid_glossary_variants_notice'));
        yield Textarea::new('short')->label('translate:fluid_glossary_field_short')->required()->rows(3)
            ->notice(I18n::rawMsg('fluid_glossary_short_notice', Glossary::SHORT_MAX));
        yield FluidBlocks::new('text')->label('translate:fluid_glossary_field_text')->blocks('richtext', 'media_text', 'quote', 'faq', 'video');
        yield Checkbox::new('online')->label('translate:fluid_glossary_field_online')->default(true)->filterable();
        yield FluidLanguage::new('lang');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'term', 'short', 'online', 'lang')->sortBy('term', 'asc');
    }
}

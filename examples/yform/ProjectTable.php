<?php

namespace Project\YForm;

use Klxm\Fluid\Data\FluidBlocks;
use Klxm\Fluid\Data\FluidLanguage;
use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Integer;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

/**
 * Projects / references: fixed data (client, year, image, short text) for overview and search – and a case study made of
 * blocks (field „Fluid-Blöcke“) that editors design directly on the website (detail view of the data list).
 */
#[AsTable('rex_yf_project', label: 'Projekte (Beispiel)', icon: 'fa-briefcase')]
final class ProjectTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('title')->label('Projekt')->required()->maxLength(191)->filterable();
        yield Text::new('client')->label('Kunde')->maxLength(191)->filterable();
        yield Integer::new('year')->label('Jahr')->min(1990)->max(2100)->filterable();
        yield Media::new('image')->label('Bild')->types('jpg', 'jpeg', 'png', 'webp', 'avif');
        yield Textarea::new('teaser')->label('Kurztext');
        yield FluidBlocks::new('story')->label('Fallstudie (Blöcke)')
            ->blocks('richtext', 'media_text', 'stats', 'quote', 'features', 'faq', 'video', 'cta');
        yield Checkbox::new('online')->label('Online')->default(true)->filterable();
        // language: the data list shows only entries of the current language (empty = all languages)
        yield FluidLanguage::new('lang');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'client', 'year', 'online', 'lang')->sortBy('year', 'desc');
    }
}

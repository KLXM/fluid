<?php

namespace Project\YForm;

use Klxm\Fluid\Data\FluidBlocks;
use Klxm\Fluid\Data\FluidLanguage;
use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Field\Url;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

/**
 * Short news (press, news) as a YForm table – output with the fluid block „Datenliste“:
 * cards or list, by date descending, pagination, detail view with its own address (/presse/titel-12).
 * For posts with blocks, draft and versions use the blog (pages in a blog category).
 */
#[AsTable('rex_yf_news', label: 'Meldungen (Beispiel)', icon: 'fa-newspaper')]
final class NewsTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('title')->label('Titel')->required()->maxLength(191)->filterable();
        yield Date::new('date')->label('Datum')->required()->filterable();
        yield Textarea::new('teaser')->label('Kurztext');
        yield Media::new('image')->label('Bild')->types('jpg', 'jpeg', 'png', 'webp', 'avif');
        yield Textarea::new('text')->label('Text');
        // content from fluid blocks (text, image + text, video …) – output in the detail view of the data list
        yield FluidBlocks::new('content')->label('Inhalt (Blöcke)')->blocks('richtext', 'media_text', 'video', 'quote', 'faq', 'downloads', 'cta');
        yield Url::new('link')->label('Weiterführender Link');
        yield Checkbox::new('online')->label('Online')->default(true)->filterable();
        // language: the data list only shows entries of the current language (empty = all languages)
        yield FluidLanguage::new('lang');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'date', 'online', 'lang')->sortBy('date', 'desc');
    }
}

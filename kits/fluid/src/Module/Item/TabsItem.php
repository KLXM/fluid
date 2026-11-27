<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Reiter“ im Block Tabs */
final class TabsItem
{
    #[Text(
        new Translatable('fluid_block_tabs_item_label'),
        required: true,
        maxLength: 40,
    )]
    public string $label = '';

    #[RichText(new Translatable('fluid_block_tabs_item_content'))]
    public string $text = '';

    #[MediaPicker(
        new Translatable('fluid_block_tabs_item_image'),
        preview: true,
    )]
    public ?Media $image = null;
}

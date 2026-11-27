<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Icon;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Schritte“ im Block Steps */
final class StepsItem
{
    #[Text(
        new Translatable('fluid_block_steps_item_meta'),
        maxLength: 40,
        placeholder: new Translatable('fluid_block_steps_item_meta_placeholder'),
        width: 'half',
    )]
    public string $meta = '';

    #[Text(
        new Translatable('fluid_field_item_title'),
        required: true,
        maxLength: 80,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_text'),
        maxLength: 400,
        rows: 3,
    )]
    public string $text = '';

    #[Icon(new Translatable('fluid_block_steps_item_icon'))]
    public string $icon = '';
}

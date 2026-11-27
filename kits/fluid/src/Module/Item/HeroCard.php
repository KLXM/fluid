<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Icon;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\Translation\Translatable;

/** Entry of „Karten (nur „Mit gestapelten Karten“)“ in the block Hero */
final class HeroCard
{
    #[Icon(
        new Translatable('fluid_field_icon'),
        width: 'half',
    )]
    public string $icon = '';

    #[Text(
        new Translatable('fluid_field_item_title'),
        required: true,
        maxLength: 50,
        width: 'half',
    )]
    public string $title = '';

    #[Text(
        new Translatable('fluid_field_text'),
        maxLength: 120,
    )]
    public string $text = '';
}

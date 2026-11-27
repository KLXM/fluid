<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Text;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Kennzahlen“ im Block Stats */
final class StatsItem
{
    #[Text(
        new Translatable('fluid_block_stats_item_value'),
        required: true,
        maxLength: 12,
        placeholder: new Translatable('fluid_block_stats_item_value_placeholder'),
        width: 'half',
    )]
    public string $value = '';

    #[Text(
        new Translatable('fluid_field_label'),
        required: true,
        maxLength: 50,
        width: 'half',
    )]
    public string $label = '';

    #[Text(
        new Translatable('fluid_block_stats_item_text'),
        maxLength: 140,
    )]
    public string $text = '';
}

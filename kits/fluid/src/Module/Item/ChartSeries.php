<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\Translation\Translatable;

/** Reihe im Block „Diagramm“ (Werte von Hand) */
final class ChartSeries
{
    #[Text(
        new Translatable('fluid_field_name'),
        required: true,
        maxLength: 60,
        placeholder: new Translatable('fluid_block_chart_item_name_placeholder'),
    )]
    public string $name = '';

    #[Textarea(
        new Translatable('fluid_block_chart_item_values'),
        maxLength: 4000,
        rows: 3,
        placeholder: '12; 19; 15',
    )]
    public string $values = '';
}

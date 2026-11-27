<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\Translation\Translatable;

/** Entry of „Vergleichszeilen (bei „Vergleichstabelle“)“ in the block Pricing */
final class PricingRow
{
    #[Text(
        new Translatable('fluid_block_pricing_row_item_label'),
        required: true,
        maxLength: 80,
    )]
    public string $label = '';

    #[Textarea(
        new Translatable('fluid_block_pricing_row_item_values'),
        rows: 3,
        note: new Translatable('fluid_block_pricing_row_item_values_help'),
    )]
    public string $values = '';
}

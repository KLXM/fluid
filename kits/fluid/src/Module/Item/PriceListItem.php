<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Text;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Preisliste“ im Block „Pakete / Vergleich“ (Darstellung „Preisliste (Speisekarte)“) */
final class PriceListItem
{
    #[Text(
        new Translatable('fluid_field_name'),
        required: true,
        maxLength: 80,
        width: 'half',
    )]
    public string $name = '';

    #[Text(
        new Translatable('fluid_field_price'),
        maxLength: 24,
        width: 'half',
        placeholder: new Translatable('fluid_block_pricing_list_item_price_placeholder'),
    )]
    public string $price = '';

    #[Text(
        new Translatable('fluid_block_pricing_list_item_text'),
        maxLength: 160,
    )]
    public string $text = '';
}

<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Text;

/** Eintrag von „Preisliste“ im Block „Pakete / Vergleich“ (Darstellung „Preisliste (Speisekarte)“) */
final class PriceListItem
{
    #[Text(
        'Name',
        required: true,
        maxLength: 80,
        width: 'half',
    )]
    public string $name = '';

    #[Text(
        'Preis',
        maxLength: 24,
        width: 'half',
        placeholder: 'z. B. 3,50 €',
    )]
    public string $price = '';

    #[Text(
        'Beschreibung (optional)',
        maxLength: 160,
    )]
    public string $text = '';
}

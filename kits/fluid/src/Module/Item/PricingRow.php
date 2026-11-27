<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;

/** Entry of „Vergleichszeilen (bei „Vergleichstabelle“)“ in the block Pricing */
final class PricingRow
{
    #[Text('Merkmal', required: true, maxLength: 80)]
    public string $label = '';

    #[Textarea(
        'Werte je Paket (eine Zeile pro Paket, in Reihenfolge)',
        rows: 3,
        note: '„ja“ oder „✓“ = enthalten, „nein“ oder „–“ = nicht enthalten, sonst freier Text (z. B. „3 Termine“).',
    )]
    public string $values = '';
}

<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Icon;
use Klxm\Fluid\Field\Text;

/** Entry of „Karten (nur „Mit gestapelten Karten“)“ in the block Hero */
final class HeroCard
{
    #[Icon(
        'Symbol',
        width: 'half',
    )]
    public string $icon = '';

    #[Text(
        'Titel',
        required: true,
        maxLength: 50,
        width: 'half',
    )]
    public string $title = '';

    #[Text(
        'Text',
        maxLength: 120,
    )]
    public string $text = '';
}

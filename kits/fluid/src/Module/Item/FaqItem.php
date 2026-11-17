<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;

/** Eintrag von „Fragen“ im Block Faq */
final class FaqItem
{
    #[Text('Frage', required: true, maxLength: 160)]
    public string $q = '';

    #[RichText('Antwort', required: true)]
    public string $a = '';
}

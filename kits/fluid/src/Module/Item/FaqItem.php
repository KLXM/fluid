<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Fragen“ im Block Faq */
final class FaqItem
{
    #[Text(
        new Translatable('fluid_block_faq_item'),
        required: true,
        maxLength: 160,
    )]
    public string $q = '';

    #[RichText(
        new Translatable('fluid_block_faq_item_a'),
        required: true,
    )]
    public string $a = '';
}

<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Pakete“ im Block Pricing */
final class PricingItem
{
    #[Text(
        new Translatable('fluid_field_name'),
        required: true,
        maxLength: 40,
        width: 'half',
    )]
    public string $name = '';

    #[Text(
        new Translatable('fluid_block_pricing_item_badge'),
        maxLength: 24,
        placeholder: new Translatable('fluid_block_pricing_item_badge_placeholder'),
        width: 'half',
    )]
    public string $badge = '';

    #[Text(
        new Translatable('fluid_field_price'),
        maxLength: 24,
        placeholder: new Translatable('fluid_block_pricing_item_price_placeholder'),
        width: 'half',
    )]
    public string $price = '';

    #[Text(
        new Translatable('fluid_block_pricing_item_period'),
        maxLength: 32,
        placeholder: new Translatable('fluid_block_pricing_item_period_placeholder'),
        width: 'half',
    )]
    public string $period = '';

    #[Textarea(
        new Translatable('fluid_block_pricing_item_text'),
        maxLength: 220,
        rows: 2,
    )]
    public string $text = '';

    #[Textarea(
        new Translatable('fluid_block_pricing_item_features'),
        rows: 4,
    )]
    public string $features = '';

    #[Checkbox(new Translatable('fluid_block_pricing_item_highlight'))]
    public bool $highlight = false;

    #[Text(
        new Translatable('fluid_field_button_label'),
        maxLength: 32,
        width: 'half',
    )]
    public string $buttonLabel = '';

    #[Link(
        new Translatable('fluid_field_button_link'),
        width: 'half',
    )]
    public string $buttonLink = '';
}

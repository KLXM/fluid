<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Karten“ im Block Cards */
final class CardsItem
{
    #[MediaPicker(
        new Translatable('fluid_field_image'),
        preview: true,
    )]
    public ?Media $image = null;

    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 40,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_item_title'),
        required: true,
        maxLength: 90,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_text'),
        maxLength: 240,
        rows: 2,
    )]
    public string $text = '';

    #[Text(
        new Translatable('fluid_field_link_label'),
        maxLength: 32,
        width: 'half',
    )]
    public string $linkLabel = '';

    #[Link(
        new Translatable('fluid_field_link'),
        width: 'half',
    )]
    public string $link = '';

    #[Checkbox(
        new Translatable('fluid_block_cards_item_featured'),
        width: 'half',
    )]
    public bool $featured = false;

    #[Text(
        new Translatable('fluid_block_cards_item_badge'),
        maxLength: 24,
        width: 'half',
        placeholder: new Translatable('fluid_block_cards_item_badge_placeholder'),
    )]
    public string $badge = '';

    #[Text(
        new Translatable('fluid_block_cards_item_category'),
        maxLength: 60,
        width: 'half',
        placeholder: new Translatable('fluid_block_cards_item_category_placeholder'),
    )]
    public string $category = '';
}

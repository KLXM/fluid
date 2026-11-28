<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\FaqItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.faq', new Translatable('fluid_block_faq'))]
#[AsFluidBlock(
    icon: 'question',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_faq_help'),
    variants: ['split' => new Translatable('fluid_block_faq_variant_split'), 'stacked' => new Translatable('fluid_block_faq_variant_stacked')],
    nestable: ['stacked'],
    jsonld: ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],
)]
final class Faq extends FluidBlock
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_title'),
        required: true,
        maxLength: 110,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_intro'),
        maxLength: 400,
        rows: 2,
    )]
    public string $intro = '';

    #[Collection(
        new Translatable('fluid_block_faq_items'),
        item: FaqItem::class,
        itemLabel: new Translatable('fluid_block_faq_item'),
        titleField: 'q',
    )]
    public array $items = [];
}

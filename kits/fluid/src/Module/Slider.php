<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\Slide;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.slider', new Translatable('fluid_block_slider'))]
#[AsBlock(
    icon: 'slideshow',
    group: new Translatable('fluid_block_group_media'),
    help: new Translatable('fluid_block_slider_help'),
    variants: [
        'slide' => new Translatable('fluid_block_slider_variant_slide'),
        'fade' => new Translatable('fluid_block_slider_variant_fade'),
    ],
    nestable: true,
)]
final class Slider extends FluidBlock
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_title'),
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
        new Translatable('fluid_block_slider_slides'),
        item: Slide::class,
        itemLabel: new Translatable('fluid_block_slider_slides_item_label'),
        titleField: 'title',
        max: 12,
    )]
    public array $slides = [];

    #[Choice(new Translatable('fluid_block_slider_height'), required: true, width: 'half', choices: [
        '16:9' => new Translatable('fluid_ratio_16_9'), '21:9' => new Translatable('fluid_block_slider_height_21_9'), '3:2' => new Translatable('fluid_ratio_3_2'), '4:3' => new Translatable('fluid_ratio_4_3'), 'screen' => new Translatable('fluid_block_slider_height_screen'),
    ])]
    public string $height = '16:9';

    #[Checkbox(
        new Translatable('fluid_block_slider_loop'),
        width: 'half',
    )]
    public bool $loop = true;

    #[Checkbox(
        new Translatable('fluid_block_slider_arrows'),
        width: 'half',
    )]
    public bool $arrows = true;

    #[Checkbox(
        new Translatable('fluid_block_slider_dots'),
        width: 'half',
    )]
    public bool $dots = true;

    #[Checkbox(new Translatable('fluid_block_slider_autoplay'), width: 'half',
        note: new Translatable('fluid_block_slider_autoplay_help'))]
    public bool $autoplay = false;

    #[Choice(
        new Translatable('fluid_block_slider_interval'),
        required: true,
        width: 'half',
        choices: ['4' => new Translatable('fluid_block_slider_interval_4'), '6' => new Translatable('fluid_block_slider_interval_6'), '8' => new Translatable('fluid_block_slider_interval_8'), '10' => new Translatable('fluid_block_slider_interval_10')],
    )]
    public string $interval = '6';
}

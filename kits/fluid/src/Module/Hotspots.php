<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Hotspots as HotspotsField;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/**
 * Interactive image: image from the media pool with markers (point, circle, rectangle, area) – per marker a
 * tooltip with title, text, link and optionally a glossary term. Data format Klxm\Fluid\Hotspots (version 1, like
 * KLXM Studio). Output: views/hotspots.php, styles css/hotspots.css, script js/hotspots.js.
 */
#[AsModule('fluid.hotspots', new Translatable('fluid_block_hotspots'))]
#[AsFluidBlock(
    icon: 'map-pin',
    group: new Translatable('fluid_block_group_media'),
    help: new Translatable('fluid_block_hotspots_help'),
    nestable: true,
)]
final class Hotspots extends FluidBlock
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
        note: new Translatable('fluid_block_hotspots_title_help'),
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_intro'),
        maxLength: 400,
        rows: 2,
    )]
    public string $intro = '';

    #[MediaPicker(
        new Translatable('fluid_field_image'),
        preview: true,
        note: new Translatable('fluid_block_hotspots_image_help'),
    )]
    public ?Media $image = null;

    #[HotspotsField(
        new Translatable('fluid_block_hotspots_hotspots'),
        image: 'image',
    )]
    public array $hotspots = [];

    #[Choice(
        new Translatable('fluid_block_hotspots_list'),
        required: true,
        choices: ['auto' => new Translatable('fluid_block_hotspots_list_auto'), 'always' => new Translatable('fluid_block_hotspots_list_always')],
        width: 'half',
        note: new Translatable('fluid_block_hotspots_list_help'),
    )]
    public string $list = 'auto';

    #[Choice(
        new Translatable('fluid_block_hotspots_width'),
        required: true,
        choices: ['normal' => new Translatable('fluid_block_hotspots_width_normal'), 'text' => new Translatable('fluid_block_hotspots_width_text')],
        width: 'half',
    )]
    public string $width = 'normal';
}

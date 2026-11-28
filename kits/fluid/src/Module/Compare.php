<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.compare', new Translatable('fluid_block_compare'))]
#[AsFluidBlock(
    icon: 'square-half',
    group: new Translatable('fluid_block_group_media'),
    help: new Translatable('fluid_block_compare_help'),
    nestable: true,
)]
final class Compare extends FluidBlock
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

    #[MediaPicker(
        new Translatable('fluid_block_compare_before'),
        required: true,
        width: 'half',
        preview: true,
        note: new Translatable('fluid_block_compare_before_help'),
    )]
    public ?Media $before = null;

    #[MediaPicker(
        new Translatable('fluid_block_compare_after'),
        required: true,
        width: 'half',
        preview: true,
    )]
    public ?Media $after = null;

    #[Text(
        new Translatable('fluid_block_compare_label_before'),
        maxLength: 24,
        width: 'half',
        placeholder: new Translatable('fluid_block_compare_label_before_placeholder'),
    )]
    public string $labelBefore = '';

    #[Text(
        new Translatable('fluid_block_compare_label_after'),
        maxLength: 24,
        width: 'half',
        placeholder: new Translatable('fluid_block_compare_label_after_placeholder'),
    )]
    public string $labelAfter = '';

    #[Choice(
        new Translatable('fluid_field_ratio'),
        required: true,
        choices: self::ratios(...),
        width: 'half',
    )]
    public string $ratio = '16:9';

    #[Text(
        new Translatable('fluid_field_caption_optional'),
        maxLength: 200,
    )]
    public string $caption = '';
}

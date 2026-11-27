<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\GalleryImage;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.gallery', new Translatable('fluid_block_gallery'))]
#[AsBlock(
    icon: 'images',
    group: new Translatable('fluid_block_group_media'),
    help: new Translatable('fluid_block_gallery_help'),
    variants: [
        'grid' => new Translatable('fluid_block_gallery_variant_grid'),
        'masonry' => new Translatable('fluid_block_gallery_variant_masonry'),
        'justified' => new Translatable('fluid_block_gallery_variant_justified'),
    ],
    nestable: true,
)]
final class Gallery extends FluidBlock
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
        new Translatable('fluid_block_gallery_images'),
        item: GalleryImage::class,
        itemLabel: new Translatable('fluid_field_image'),
        titleField: 'caption',
        max: 60,
    )]
    public array $images = [];

    #[Choice(new Translatable('fluid_block_gallery_columns'), required: true, width: 'half', choices: ['2' => new Translatable('fluid_block_gallery_columns_2'), '3' => new Translatable('fluid_block_gallery_columns_3'), '4' => new Translatable('fluid_block_gallery_columns_4'), '5' => new Translatable('fluid_block_gallery_columns_5')],
        note: new Translatable('fluid_block_gallery_columns_help'))]
    public string $columns = '3';

    #[Choice(
        new Translatable('fluid_field_ratio'),
        required: true,
        width: 'half',
        choices: self::ratios(...),
        variants: ['grid'],
    )]
    public string $ratio = '4:3';

    #[Checkbox(
        new Translatable('fluid_block_gallery_captions'),
        width: 'half',
    )]
    public bool $captions = true;

    #[Checkbox(
        new Translatable('fluid_block_gallery_lightbox'),
        width: 'half',
    )]
    public bool $lightbox = true;

    #[Choice(
        new Translatable('fluid_block_gallery_single_size'),
        required: true,
        width: 'half',
        choices: ['grid' => new Translatable('fluid_block_gallery_single_size_grid'), 'medium' => new Translatable('fluid_block_gallery_single_size_medium'), 'large' => new Translatable('fluid_block_gallery_single_size_large')],
        note: new Translatable('fluid_block_gallery_single_size_help'),
    )]
    public string $singleSize = 'large';
}

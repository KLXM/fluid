<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\MediaCategory;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\DownloadsFile;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.downloads', new Translatable('fluid_block_downloads'))]
#[AsFluidBlock(
    icon: 'download-simple',
    group: new Translatable('fluid_block_group_media'),
    help: new Translatable('fluid_block_downloads_help'),
    nestable: true,
)]
final class Downloads extends FluidBlock
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

    #[Choice(
        new Translatable('fluid_block_downloads_source'),
        required: true,
        choices: ['manual' => new Translatable('fluid_block_downloads_source_manual'), 'collection' => new Translatable('fluid_block_downloads_source_collection')],
        width: 'half',
    )]
    public string $source = 'manual';

    #[MediaCategory(
        new Translatable('fluid_block_downloads_collection'),
        width: 'half',
    )]
    public string $collection = '';

    #[Collection(
        new Translatable('fluid_block_downloads_files'),
        item: DownloadsFile::class,
        itemLabel: new Translatable('fluid_block_downloads_item'),
        titleField: 'label',
    )]
    public array $files = [];

    #[Checkbox(new Translatable('fluid_block_downloads_show_viewer'))]
    public bool $showViewer = true;
}

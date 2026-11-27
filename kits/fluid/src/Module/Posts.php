<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Blog;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\Number;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.posts', new Translatable('fluid_block_posts'))]
#[AsBlock(
    icon: 'newspaper',
    group: new Translatable('fluid_block_group_website'),
    help: new Translatable('fluid_block_posts_help'),
    variants: ['cards' => new Translatable('fluid_block_posts_variant_cards'), 'list' => new Translatable('fluid_block_posts_variant_list'), 'featured' => new Translatable('fluid_block_posts_variant_featured')],
    nestable: ['list'],
    reload: true,
)]
final class Posts extends FluidBlock
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

    #[Blog(
        new Translatable('fluid_block_posts_blog'),
        width: 'half',
    )]
    public string $blog = '';

    #[Number(
        new Translatable('fluid_block_posts_limit'),
        width: 'half',
    )]
    public int $limit = 9;

    #[Checkbox(
        new Translatable('fluid_field_paging'),
        width: 'half',
    )]
    public bool $pager = true;

    #[Checkbox(
        new Translatable('fluid_block_posts_tags'),
        width: 'half',
    )]
    public bool $tags = false;

    #[Choice(
        new Translatable('fluid_field_size'),
        required: true,
        choices: self::sizes(...),
        width: 'half',
        note: new Translatable('fluid_field_size_help'),
    )]
    public string $size = 'm';

    #[Link(
        new Translatable('fluid_block_posts_more'),
        width: 'half',
        note: new Translatable('fluid_block_posts_more_help'),
    )]
    public string $more = '';

    #[Text(
        new Translatable('fluid_block_posts_empty_text'),
        maxLength: 200,
    )]
    public string $emptyText = '';
}

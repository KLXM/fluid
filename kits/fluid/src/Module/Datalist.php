<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\ListTemplate;
use Klxm\Fluid\Field\Number;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Field\YFormField;
use Klxm\Fluid\Field\YFormTable;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.datalist', new Translatable('fluid_block_datalist'))]
#[AsBlock(
    icon: 'rows',
    group: new Translatable('fluid_block_group_data'),
    help: new Translatable('fluid_block_datalist_help'),
    requires: 'yform',
    variants: ['cards' => new Translatable('fluid_block_datalist_variant_cards'), 'list' => new Translatable('fluid_block_datalist_variant_list'), 'table' => new Translatable('fluid_block_datalist_variant_table'), 'toc' => new Translatable('fluid_block_datalist_variant_toc')],
    reload: true,
    listTemplate: true,
)]
final class Datalist extends FluidBlock
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

    #[YFormTable(
        new Translatable('fluid_block_datalist_table'),
        required: true,
    )]
    public string $table = '';

    #[ListTemplate(
        new Translatable('fluid_block_datalist_template'),
        note: new Translatable('fluid_block_datalist_template_help'),
    )]
    public string $template = '';

    #[YFormField(
        new Translatable('fluid_field_item_title'),
        width: 'half',
        note: new Translatable('fluid_block_datalist_title_field_help'),
    )]
    public string $titleField = '';

    #[YFormField(
        new Translatable('fluid_field_image'),
        width: 'half',
        note: new Translatable('fluid_block_datalist_image_field_help'),
    )]
    public string $imageField = '';

    #[YFormField(
        new Translatable('fluid_field_text'),
        width: 'half',
        note: new Translatable('fluid_block_datalist_text_field_help'),
    )]
    public string $textField = '';

    #[YFormField(
        new Translatable('fluid_block_datalist_meta_field'),
        width: 'half',
    )]
    public string $metaField = '';

    #[YFormField(
        new Translatable('fluid_block_datalist_filter_field'),
        width: 'half',
        note: new Translatable('fluid_block_datalist_filter_field_help'),
    )]
    public string $filterField = '';

    #[Text(
        new Translatable('fluid_block_datalist_filter_value'),
        maxLength: 100,
        placeholder: '1',
        width: 'half',
    )]
    public string $filterValue = '';

    #[YFormField(
        new Translatable('fluid_block_datalist_sort_field'),
        width: 'half',
        note: new Translatable('fluid_block_datalist_sort_field_help'),
    )]
    public string $sortField = '';

    #[Choice(
        new Translatable('fluid_block_datalist_sort_dir'),
        required: true,
        choices: ['asc' => new Translatable('fluid_block_datalist_sort_dir_asc'), 'desc' => new Translatable('fluid_block_datalist_sort_dir_desc')],
        width: 'half',
    )]
    public string $sortDir = 'asc';

    #[Checkbox(
        new Translatable('fluid_block_datalist_table_sort'),
        width: 'half',
        variants: ['table'],
        note: new Translatable('fluid_block_datalist_table_sort_help'),
    )]
    public bool $tableSort = false;

    #[YFormField(
        new Translatable('fluid_block_datalist_visitor_filter'),
        width: 'half',
        variants: ['cards', 'list'],
        note: new Translatable('fluid_block_datalist_visitor_filter_help'),
    )]
    public string $visitorFilter = '';

    #[Checkbox(
        new Translatable('fluid_block_datalist_visitor_search'),
        width: 'half',
        note: new Translatable('fluid_block_datalist_visitor_search_help'),
    )]
    public bool $visitorSearch = false;

    #[Number(
        new Translatable('fluid_block_datalist_limit'),
        width: 'half',
    )]
    public int $limit = 0;

    #[Checkbox(
        new Translatable('fluid_field_paging'),
        width: 'half',
        note: new Translatable('fluid_block_datalist_pager_help'),
    )]
    public bool $pager = false;

    #[Choice(
        new Translatable('fluid_field_size'),
        required: true,
        choices: self::sizes(...),
        width: 'half',
        note: new Translatable('fluid_field_size_help'),
    )]
    public string $size = 'm';

    #[Checkbox(
        new Translatable('fluid_block_datalist_detail'),
        note: new Translatable('fluid_block_datalist_detail_help'),
    )]
    public bool $detail = true;

    #[Choice(
        new Translatable('fluid_block_datalist_menu_entries'),
        choices: ['' => new Translatable('fluid_block_datalist_menu_entries_none'), 'top' => new Translatable('fluid_block_datalist_menu_entries_top'), 'two' => new Translatable('fluid_block_datalist_menu_entries_two')],
        width: 'half',
        note: new Translatable('fluid_block_datalist_menu_entries_help'),
    )]
    public string $menuEntries = '';

    #[Number(
        new Translatable('fluid_block_datalist_menu_limit'),
        min: 1,
        max: 50,
        width: 'half',
    )]
    public int $menuLimit = 12;

    #[Choice(
        new Translatable('fluid_block_datalist_knowledge_style'),
        required: true,
        choices: ['magazin' => new Translatable('fluid_block_datalist_knowledge_style_magazin'), 'sachlich' => new Translatable('fluid_block_datalist_knowledge_style_sachlich')],
        note: new Translatable('fluid_block_datalist_knowledge_style_help'),
    )]
    public string $knowledgeStyle = 'magazin';

    #[Checkbox(
        new Translatable('fluid_block_datalist_ics'),
        note: new Translatable('fluid_block_datalist_ics_help'),
    )]
    public bool $ics = false;

    #[Text(
        new Translatable('fluid_block_datalist_empty_text'),
        maxLength: 160,
        placeholder: new Translatable('fluid_block_datalist_empty_text_placeholder'),
    )]
    public string $emptyText = '';
}

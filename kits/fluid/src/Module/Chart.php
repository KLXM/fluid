<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Chart\Chart as Charts;
use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\ChartSeries;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.chart', new Translatable('fluid_block_chart'))]
#[AsBlock(
    icon: 'chart-line-up',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_chart_help'),
    variants: [
        'standard' => new Translatable('fluid_block_chart_variant_standard'),
        'split' => new Translatable('fluid_block_chart_variant_split'),
    ],
    nestable: true,
)]
final class Chart extends FluidBlock
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
        maxLength: 600,
        rows: 3,
    )]
    public string $intro = '';

    #[Choice(
        new Translatable('fluid_field_kind'),
        choices: static function (): array { return Charts::choices('type'); },
        required: true,
        width: 'half',
    )]
    public string $chartType = 'bar';

    #[Choice(new Translatable('fluid_block_chart_source'), choices: static function (): array { return Charts::sourceChoices(); }, width: 'half',
        note: new Translatable('fluid_block_chart_source_help'))]
    public string $source = '';

    #[Textarea(new Translatable('fluid_block_chart_labels'), maxLength: 4000, rows: 5, placeholder: new Translatable('fluid_block_chart_labels_placeholder', "\n"),
        note: new Translatable('fluid_block_chart_labels_help'))]
    public string $labels = '';

    #[Collection(
        new Translatable('fluid_block_chart_series'),
        item: ChartSeries::class,
        itemLabel: new Translatable('fluid_block_chart_series_item'),
        titleField: 'name',
        max: 8,
    )]
    public array $series = [];

    #[Choice(
        new Translatable('fluid_block_chart_format'),
        choices: static function (): array { return Charts::choices('format'); },
        required: true,
        width: 'half',
    )]
    public string $format = 'number';

    #[Text(
        new Translatable('fluid_block_chart_unit'),
        maxLength: 12,
        placeholder: new Translatable('fluid_block_chart_unit_placeholder'),
        width: 'half',
    )]
    public string $unit = '';

    #[Checkbox(
        new Translatable('fluid_block_chart_stacked'),
        width: 'half',
    )]
    public bool $stacked = false;

    #[Choice(
        new Translatable('fluid_field_height'),
        choices: static function (): array { return Charts::choices('height'); },
        required: true,
        width: 'half',
    )]
    public string $height = '320';

    #[Choice(new Translatable('fluid_block_chart_table_view'), choices: static function (): array { return Charts::choices('table'); }, width: 'half',
        note: new Translatable('fluid_block_chart_table_view_help'))]
    public string $tableView = '';

    #[Text(new Translatable('fluid_block_chart_description'), maxLength: 300,
        note: new Translatable('fluid_block_chart_description_help'))]
    public string $description = '';

    /**
     * Chart description from the fields (by hand or from the selected source).
     *
     * @param array<string, mixed> $d values of the block (keys as stored)
     * @return array<string, mixed>
     */
    public static function spec(array $d): array
    {
        $key = (string) ($d['source'] ?? '');
        $spec = '' !== $key ? Charts::fromSource($key) : Charts::fromInput((string) ($d['labels'] ?? ''), array_values((array) ($d['series'] ?? [])));

        return [
            'type' => (string) ($d['chart_type'] ?? '') ?: ($spec['type'] ?? 'bar'),
            'format' => (string) ($d['format'] ?? '') ?: ($spec['format'] ?? 'number'),
            'unit' => (string) ($d['unit'] ?? '') ?: (string) ($spec['unit'] ?? ''),
            'stacked' => !empty($d['stacked']),
            'height' => (int) ($d['height'] ?? 320),
            'description' => trim((string) ($d['description'] ?? '')) ?: trim((string) ($spec['description'] ?? '')) ?: trim((string) ($d['title'] ?? '')),
        ] + $spec;
    }

    /** Field „Tabelle unter dem Diagramm“ → option table of Chart::render() */
    public static function tableOption(array $d): bool|string
    {
        return match ((string) ($d['table_view'] ?? '')) {
            'open' => true,
            'none' => 'none',
            default => false,
        };
    }
}

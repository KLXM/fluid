<?php

namespace Klxm\Fluid\Chart\Attribute;

use Attribute;

/**
 * Data source for the block „Diagramm“ – on a static method `fn (): array` that returns labels and series
 * (optionally type, format, unit, description). The values are fresh on every page request:
 *
 *     #[AsChartSource('news.per_month', 'Neuigkeiten je Monat')]
 *     public static function perMonth(): array
 *     {
 *         return ['labels' => ['Jan', 'Feb'], 'series' => [['name' => 'Beiträge', 'data' => [4, 7]]]];
 *     }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsChartSource
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}

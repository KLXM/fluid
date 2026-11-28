<?php

namespace Klxm\Fluid\Chart;

/**
 * Chart settings, set by the project (like the core policies) – in Project::configure():
 *
 *     Chart::$config = new ChartConfig(engine: 'apex');
 *
 * Without a setting ECharts draws.
 */
final readonly class ChartConfig
{
    /** @param string $engine name of an engine registered via #[AsFluidChartEngine] (default: echarts) */
    public function __construct(
        public string $engine = 'echarts',
    ) {}
}

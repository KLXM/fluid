<?php

namespace Klxm\Fluid\Chart;

use Klxm\Fluid\Chart\Attribute\AsChartEngine;
use Klxm\Fluid\FluidAddon;

/**
 * Default engine: Apache ECharts (Apache-2.0), bundled and self-hosted – only line, bar, pie, canvas
 * (≈ 550 KB, gzip ≈ 180 KB, loads only when a chart comes into view).
 */
#[AsChartEngine('echarts')]
final class EchartsEngine implements ChartEngine
{
    public function url(): string
    {
        return FluidAddon::instance()->getAssetsUrl('vendor/echarts/charts-echarts.mjs');
    }
}

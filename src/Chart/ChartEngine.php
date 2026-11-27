<?php

namespace Klxm\Fluid\Chart;

/**
 * Engine that draws charts – register with #[AsChartEngine('name')] on the class.
 *
 * url() is the address of an ES module with exactly one function:
 *
 *     export function render(el, spec, theme) → { update(theme, opts), resize(), dispose(), image() }
 *
 * spec = Chart::normalize() (type, labels, series, title, description, format, unit, stacked, height, locale),
 * theme = { colors[], ink, muted, grid, bg, font, dark } from the CSS variables where the chart sits.
 * Template: resources/js/charts-echarts.mjs (source of assets/vendor/echarts/charts-echarts.mjs).
 */
interface ChartEngine
{
    /** Address of the ES module – absolute or relative to the website */
    public function url(): string;
}

<?php

namespace Klxm\Fluid\Chart\Attribute;

use Attribute;

/**
 * Chart engine – on a class implementing ChartEngine:
 *
 *     #[AsFluidChartEngine('apex')]
 *     final class ApexEngine implements ChartEngine { public function url(): string { … } }
 *
 * Selected with Chart::$config = new ChartConfig(engine: 'apex').
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsFluidChartEngine
{
    public function __construct(
        public string $name,
    ) {}
}

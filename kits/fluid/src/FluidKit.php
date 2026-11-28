<?php

namespace Klxm\Fluid\Kit\Fluid;

use Klxm\Fluid\AsFluidKit;
use Klxm\Fluid\Kit;
use Override;
use Redaxo\Core\Filesystem\Url;

use function dirname;

/**
 * Kit „fluid“: breakpoint-free, for many kinds of projects (from KLXM Studio). Base kit all others inherit from.
 * Its assets live in the AddOn under assets/kits/fluid/ and are published with the AddOn.
 */
#[AsFluidKit('fluid', 'Fluid')]
final class FluidKit extends Kit
{
    #[Override]
    public function key(): string
    {
        return self::DEFAULT;
    }

    #[Override]
    public function path(string $file = ''): string
    {
        return dirname(__DIR__) . ('' !== $file ? '/' . $file : '');
    }

    #[Override]
    protected function sourceAsset(string $file): string
    {
        return dirname(__DIR__, 3) . '/assets/kits/fluid/' . $file;
    }

    #[Override]
    protected function publicAssetUrl(string $file): string
    {
        return Url::addonAssets('fluid', 'kits/fluid/' . $file);
    }

    #[Override]
    protected function publish(): void
    {
        // published by the core with the AddOn's assets
    }
}

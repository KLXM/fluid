<?php

namespace Klxm\Fluid\Routing;

use Redaxo\Core\AbstractProject;
use Redaxo\Core\Filesystem\DefaultPathProvider;

/**
 * Addresses from the base path of the website (/assets/…, /media/…, /index.php) instead of relative (./assets/…).
 * Needed for pretty URLs: on /leistungen/beratung relative addresses would otherwise be looked up below /leistungen/.
 * Router::boot sets it in the frontend via Url::init() (core extension point).
 *
 * @internal
 */
final class RootPathProvider extends DefaultPathProvider
{
    public function __construct(AbstractProject $project, string $basePath)
    {
        $this->base = rtrim($basePath, '/') . '/';
        $this->frontend = '';
        $this->backend = $this->base . $project->backendDirectory . '/';
        $this->core = $this->base;
        $this->provideAbsolutes = false;
    }
}

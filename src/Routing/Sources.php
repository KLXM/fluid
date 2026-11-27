<?php

namespace Klxm\Fluid\Routing;

use Klxm\Fluid\Routing\Attribute\AsRouteSource;
use Redaxo\Core\ClassDiscovery;
use Throwable;

/**
 * All sources of custom URLs: found via #[AsRouteSource] (the core's class discovery) and registered via add().
 */
final class Sources
{
    /** @var array<string, RouteSource>|null */
    private static ?array $all = null;

    /** @var list<RouteSource> */
    private static array $added = [];

    public static function add(RouteSource $source): void
    {
        self::$added[] = $source;
        self::$all = null;
    }

    /** @return array<string, RouteSource> Kennung => Quelle */
    public static function all(): array
    {
        if (null !== self::$all) {
            return self::$all;
        }
        $all = [];
        try {
            foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsRouteSource::class, RouteSource::class) as $class => $_) {
                $s = new $class();
                $all[$s->key()] = $s;
            }
        } catch (Throwable) {
            // class discovery not available (setup)
        }
        foreach (self::$added as $s) {
            $all[$s->key()] = $s;
        }

        return self::$all = $all;
    }

    public static function get(string $key): ?RouteSource
    {
        return self::all()[$key] ?? null;
    }
}

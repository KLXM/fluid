<?php

namespace Klxm\Fluid\Kit\Fluid;

use Klxm\Fluid\Chart\Attribute\AsChartSource;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;
use ReflectionClass;
use Throwable;

use function count;
use function is_string;

/**
 * Data sources of the kit for the block „Diagramm“ – also an example of #[AsChartSource]:
 * a static method that returns labels and series. The values are fresh on every call.
 */
final class ChartSources
{
    /** @return array{labels: list<string>, series: list<array{name: string, data: list<int>}>, description: string} */
    #[AsChartSource('fluid.blocks', 'Inhalte der Website: Blöcke je Art (live)')]
    public static function blocks(): array
    {
        $rows = Sql::factory()->getArray(
            'SELECT module, COUNT(*) AS n FROM rex_article_slice WHERE language_id = ? AND revision = 0 AND status = 1 GROUP BY module ORDER BY n DESC',
            [Language::getCurrentId()],
        );
        $labels = [];
        $data = [];
        foreach ($rows as $row) {
            $key = (string) $row['module'];
            if ('fluid.layout' === $key) {
                continue;   // grid, no content
            }
            $labels[] = self::label($key);
            $data[] = (int) $row['n'];
            if (8 === count($labels)) {
                break;
            }
        }

        return ['labels' => $labels, 'series' => [['name' => lt('Blöcke'), 'data' => $data]],
            'description' => lt('Die häufigsten Blöcke auf dieser Website, gezählt aus der Datenbank')];
    }

    private static function label(string $key): string
    {
        try {
            $class = Module::getClass($key);
            $attr = null !== $class ? new ReflectionClass($class)->getAttributes(AsModule::class)[0] ?? null : null;
            $name = $attr?->newInstance()->name;

            return null !== $name ? (is_string($name) ? $name : $name->translate()) : $key;
        } catch (Throwable) {
            return $key;
        }
    }
}

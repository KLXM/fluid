<?php

use Klxm\Fluid\Kit\Native\NcssUpdate;

/*
 * NativeCSS im Kit „NativeCSS“ aktualisieren – im Repo von fluid, ohne REDAXO (gleiche Logik wie `fluid:kit:ncss-update`).
 *
 *   php tools/ncss-update.php --from=../nativecss [--ref=HEAD] [--dry-run]
 *   php tools/ncss-update.php --tag=v1.2.0 [--dry-run]
 */

require dirname(__DIR__) . '/kits/native/src/NcssUpdate.php';

$o = getopt('', ['from:', 'ref:', 'tag:', 'dry-run', 'help']);
if (isset($o['help']) || (isset($o['from']) === isset($o['tag']))) {
    fwrite(STDERR, "php tools/ncss-update.php --from=/pfad/zu/nativecss [--ref=HEAD] [--dry-run]\nphp tools/ncss-update.php --tag=v1.2.0 [--dry-run]\n");
    exit(isset($o['help']) ? 0 : 2);
}
$up = new NcssUpdate(dirname(__DIR__) . '/kits/native/assets');
try {
    isset($o['from']) ? $up->fromDirectory((string) $o['from'], (string) ($o['ref'] ?? '')) : $up->fromGithub((string) $o['tag']);
    $plan = $up->plan();
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
    exit(1);
}
$i = $up->info();
echo 'NativeCSS ', $i['version'], '' !== $i['commit'] && $i['commit'] !== $i['version'] ? ' (' . $i['commit'] . ')' : '', ' vom ', $i['date'], "\nQuelle: ", $i['source'], "\n";
if ($i['dirty']) {
    echo "Achtung: nicht committete Änderungen in dist/ – mit --ref=HEAD nur den committeten Stand übernehmen.\n";
}
printf("neu %d · geändert %d · entfernt %d · unverändert %d\n", count($plan['added']), count($plan['changed']), count($plan['removed']), $plan['unchanged']);
foreach (['added' => '+', 'changed' => '~', 'removed' => '-'] as $k => $sign) {
    foreach ($plan[$k] as $f) {
        echo "  $sign $f\n";
    }
}
echo count($plan['manifest']), " Dateien im Manifest css/ncss-base.css\n";
if (isset($o['dry-run'])) {
    echo "Probelauf – nichts geschrieben.\n";
    exit(0);
}
$up->apply();
echo "Übernommen. Bitte die Seiten im Kit „NativeCSS“ prüfen und VERSION.txt mit committen.\n";

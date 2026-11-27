<?php

use Klxm\Fluid\BlockSelfTest;

/*
 * Prüft die Views der Kits (Blöcke, Seitenrahmen, Fragmente) auf Code hinter einem Zeilenkommentar – ohne REDAXO.
 *
 *   php tools/check-views.php
 *
 * Hintergrund: In `<?php $a = 1; // Kommentar $b = 2; ?>` beendet erst `?>` den Kommentar – `$b = 2;` fehlt still. Das
 * gleiche prüft `console fluid:selftest` (Klxm\Fluid\BlockSelfTest). Läuft in der CI bei jedem Push.
 */

require dirname(__DIR__) . '/src/BlockSelfTest.php';

$bad = BlockSelfTest::viewComments();
if ($bad) {
    fwrite(STDERR, "Code hinter einem Zeilenkommentar in Views:\n  " . implode("\n  ", $bad) . "\n");
    exit(1);
}
echo "Views ohne Code hinter Zeilenkommentaren.\n";

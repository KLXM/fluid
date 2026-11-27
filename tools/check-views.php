<?php

use Klxm\Fluid\BlockSelfTest;

/*
 * Checks the views of the kits (blocks, page frames, fragments) for code after a line comment – without REDAXO.
 *
 *   php tools/check-views.php
 *
 * Background: in `<?php $a = 1; // Kommentar $b = 2; ?>` only `?>` ends the comment – `$b = 2;` silently goes missing. The
 * same is checked by `console fluid:selftest` (Klxm\Fluid\BlockSelfTest). Runs in CI on every push.
 */

require dirname(__DIR__) . '/src/BlockSelfTest.php';

$bad = BlockSelfTest::viewComments();
if ($bad) {
    fwrite(STDERR, "Code hinter einem Zeilenkommentar in Views:\n  " . implode("\n  ", $bad) . "\n");
    exit(1);
}
echo "Views ohne Code hinter Zeilenkommentaren.\n";

<?php

use Klxm\Fluid\Backend\SliceGrid;
use Redaxo\Core\View\Fragment;

/**
 * Backend slice list (overrides the core fragment, same variables): inserts the panels of the column blocks
 * into their layout's grid (Klxm\Fluid\Backend\SliceGrid). Unchanged without layouts.
 *
 * - string $content  The rendered HTML of all slice <li> elements
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
if ('' === $this->content) {
    return;
}
?>
<?php $content = SliceGrid::assemble($this->content) ?>
<ul class="rex-slices"<?= SliceGrid::opsAttr() ?>>
    <?= $content ?>
</ul>

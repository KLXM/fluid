<?php
use Klxm\Fluid\Maps\Maps;

/** Karte (Kopf im Kit-Stil, Ausgabe Klxm\Fluid\Maps\Maps). @var \Klxm\Fluid\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?= Maps::renderBlock($d) ?>
</div>

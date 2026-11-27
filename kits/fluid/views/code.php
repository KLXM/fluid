<?php

use Klxm\Fluid\Block;

/**
 * Code – one or more files as a code sample, output escaped (no HTML from the field), with copy button (js/code.js).
 * @var Block $b  @var array $d
 */
$langs = ['php' => 'PHP', 'bash' => 'Terminal', 'env' => '.env', 'json' => 'JSON', 'yaml' => 'YAML', 'html' => 'HTML', 'css' => 'CSS', 'js' => 'JavaScript', 'text' => 'Text'];
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['code'] ?? ''))));
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <div class="code-files">
    <?php foreach ($items as $i => $it): $lang = (string) ($it['lang'] ?? 'text');
    $file = trim((string) ($it['file'] ?? '')) ?>
    <figure class="code">
      <figcaption class="code__head">
        <span class="code__file"<?= $b->edit("items.$i.file") ?>><?= e('' !== $file ? $file : ($langs[$lang] ?? $lang)) ?></span>
        <?php if ('' !== $file): ?><span class="code__lang"><?= e($langs[$lang] ?? $lang) ?></span><?php endif ?>
        <button type="button" class="code__copy" data-code-copy hidden><?= icon('copy') ?><span data-code-label><?= e(lt('Kopieren')) ?></span></button>
      </figcaption>
      <pre class="code__pre" tabindex="0"><code class="lang-<?= e($lang) ?>"><?= e(rtrim((string) $it['code'])) ?></code></pre>
    </figure>
    <?php endforeach ?>
  </div>
  <?php if ('' !== trim((string) ($d['note'] ?? ''))): ?><p class="code__note"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif ?>
</div>

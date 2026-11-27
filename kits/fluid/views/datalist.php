<?php
/**
 * Datenliste: Einträge einer YForm-Tabelle als Karten, Liste oder Tabelle (Tabelle = #[AsTable]-Klasse aus YForm; Markup und CSS wie der Studio-Block
 * „Datenliste“, css/data.css). Detailansicht auf derselben Seite über ?eintrag={id} (alle Felder als Liste).
 * Angemeldete Redakteure sehen im Bearbeiten-Modus „Bearbeiten“ je Eintrag und „Neuer Eintrag“ (YForm im Backend).
 * @var Block $b  @var array $d
 */
use Klxm\Fluid\Block;
use Klxm\Fluid\Data\Detail;
use Klxm\Fluid\Data\FluidBlocks;
use Klxm\Fluid\Data\Ics;
use Klxm\Fluid\Data\Knowledge;
use Klxm\Fluid\Data\RecordBlocks;
use Klxm\Fluid\Data\RecordInline;
use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Live;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;

$t = YFormBridge::table((string) ($d['table'] ?? ''));
if (!$t) {
    if (is_editing()) {
        echo '<div class="wrap"><p class="empty-hint">' . e(YFormBridge::available() ? 'Bitte in der Seitenleiste eine YForm-Tabelle wählen.' : 'Für diesen Block wird das AddOn YForm benötigt.') . '</p></div>';
    }

    return;
}
$layout = in_array($b->variant(), ['cards', 'list', 'table', 'toc'], true) ? $b->variant() : 'cards';
$titleF = YFormBridge::field($t, (string) ($d['title_field'] ?? ''), [Text::class]);
$imageF = YFormBridge::field($t, (string) ($d['image_field'] ?? ''), [Media::class]);
$textF = YFormBridge::field($t, (string) ($d['text_field'] ?? ''), [Textarea::class]);
$metaF = YFormBridge::field($t, (string) ($d['meta_field'] ?? ''));
$filterF = YFormBridge::field($t, (string) ($d['filter_field'] ?? ''));
$same = static fn ($a, $c) => $a && $c && $a->name === $c->name;
$filterV = trim((string) ($d['filter_value'] ?? ''));
$here = Url::article($b->articleId, Language::getCurrentId());
$detailOn = !empty($d['detail']);
$edit = is_editing() ? static fn (?int $id = null) => YFormBridge::editUrl($t, $id) : static fn (?int $id = null) => null;
$editBtn = static fn (?string $url, string $label) => $url ? '<a class="fdl-edit" href="' . e($url) . '" target="_blank" rel="noopener">' . e($label) . '</a>' : '';
$title = static fn ($row) => $titleF ? YFormBridge::text($row, $titleF) : '#' . $row->id;
// Detailansicht mit schöner Adresse /seite/titel-ID (Data\Detail, Router)
$href = static fn ($row) => Detail::url($here, $title($row), $row->id);
$pic = static fn ($row, string $sizes, string $ratio) => '' !== ($file = YFormBridge::file($row, $imageF)) ? img($file, $sizes, ['ratio' => $ratio]) : '';

// ------------------------------------------------------------------ Detailansicht
$entry = $detailOn ? Request::get('eintrag', 'int', 0) : 0;
$row = $entry > 0 ? YFormBridge::row($t, $entry, $filterF, $filterV) : null;
if ($entry > 0 && !$row && !is_editing()) {
    // Eintrag gibt es nicht (mehr) bzw. erfüllt die Bedingung nicht: 404 wie eine fehlende Seite
    Response::setStatus(Response::HTTP_NOT_FOUND);
    echo '<div class="wrap"><p class="dl-empty">' . e(lt('Diesen Eintrag gibt es nicht mehr.')) . ' <a href="' . e($here) . '">' . e(lt('Zur Übersicht')) . '</a></p></div>';

    return;
}
// Wissensbereich (Tabellenart „Wissen“): Kapitel mit Kapitelnavigation
if ($row && Knowledge::is($t)) {
    echo Knowledge::detail($b, $d, $t, $row, $here);

    return;
}
if ($row):
    $img = $pic($row, '(min-width: 1280px) 1200px, 100vw', '16:9');
    // Seitenkopf (Titel, Beschreibung, Bild, Canonical, hreflang) kommt vom Resolver der Detailansicht (Data\Detail)
?>
<div class="wrap df df--head fdl-detail">
  <p class="df-back"><a href="<?= e($here) ?>">← <?= e(lt('Zur Übersicht')) ?></a></p>
  <header class="df-head">
    <?php $metaInline = $metaF ? RecordInline::attrs($t, $row, $metaF) : '' ?>
    <?php if ($metaF && (($m = YFormBridge::html($row, $metaF)) !== '' || '' !== $metaInline)): ?><p class="df-meta"<?= $metaInline ?>><?= $m ?></p><?php endif ?>
    <h2 class="h2 df-title"<?= $titleF ? RecordInline::attrs($t, $row, $titleF) : '' ?>><?= e($title($row)) ?></h2>
    <?= $editBtn($edit($row->id), 'Eintrag bearbeiten') ?>
    <?php // Termine: „In den Kalender“ als .ics (Data\Ics) – nur mit Datumsfeld und eingeschalteter Option
    if (!empty($d['ics']) && null !== Ics::dateField($t) && '' !== YFormBridge::sortValue($row, Ics::dateField($t))): ?>
    <p class="df-actions"><a class="btn btn--secondary btn--small" href="<?= e(Ics::url($b->articleId, $row->id)) ?>" download><?= icon('calendar-plus') ?><?= e(lt('In den Kalender')) ?></a></p>
    <?php endif ?>
    <?php
    // Bild wechseln (Medienpool) – als Knopf am Bild, damit die Bildwerkzeuge (Bearbeiten, Anpassen …) frei bleiben
    $imgInline = $imageF ? RecordInline::attrs($t, $row, $imageF) : '';
    $imgBtn = '' !== $imgInline ? '<button type="button" class="fdl-recimg"' . $imgInline . '>' . e('' !== $img ? 'Bild wechseln' : '+ Bild wählen') . '</button>' : '';
    ?>
    <?php if ('' !== $img): ?><div class="df-img ratio-16-9"><?= $img ?><?= $imgBtn ?></div><?php elseif ('' !== $imgBtn): ?><p class="df-img-empty"><?= $imgBtn ?></p><?php endif ?>
  </header>
  <dl class="df-dl">
    <?php $blocksHtml = '';
    foreach (YFormBridge::detailFields($t) as $f):
        // Feld „Fluid-Blöcke“: als Blöcke unter der Detailansicht (volle Breite), nicht als Text
        if ($f instanceof FluidBlocks) {
        $blocksHtml .= RecordBlocks::render($t, $row, $f, $b->articleId);
        continue;
        }
        if ($same($f, $titleF) || $same($f, $imageF) || $same($f, $filterF)) {
        continue;
        }
        // Bearbeitungsmodus: Textfelder direkt auf der Seite (Data\RecordInline) – auch leere, damit man sie füllen kann
        $inline = RecordInline::attrs($t, $row, $f);
        if (($h = YFormBridge::html($row, $f)) === '' && '' === $inline) {
        continue;
        } ?>
    <div><dt><?= e($f->translatedLabel()) ?></dt><dd<?= $inline ?>><?= $h ?></dd></div>
    <?php endforeach ?>
  </dl>
</div>
<?php if ('' !== $blocksHtml): ?><div class="df-blocks"><?= $blocksHtml ?></div><?php endif ?>
<?php
    return;
endif;

// ------------------------------------------------------------------ Liste
$opt = [
    'filter' => $filterF, 'value' => $filterV,
    'sort' => YFormBridge::field($t, (string) ($d['sort_field'] ?? '')), 'dir' => (string) ($d['sort_dir'] ?? 'asc'),
    'limit' => (int) ($d['limit'] ?? 0),
];
// Blättern: „Höchstens“ = Einträge je Seite, ?seite=2 …
$pg = !empty($d['pager']) && $opt['limit'] > 0 ? YFormBridge::page($t, $opt, Request::get('seite', 'int', 1), $opt['limit']) : null;
$rows = $pg ? $pg['rows'] : YFormBridge::rows($t, $opt);
// Wissensbereich als Karten oder Liste: Kapitel der obersten Ebene in der Reihenfolge des Kapitelbaums
if (!$pg && in_array($layout, ['cards', 'list'], true) && Knowledge::is($t)) {
    $order = array_flip(Knowledge::tree($t, $d + ['_language' => Language::getCurrentId()])['roots']);
    $rows = array_values(array_filter($rows, static fn ($r) => isset($order[(int) $r->id])));
    usort($rows, static fn ($a, $c) => $order[(int) $a->id] <=> $order[(int) $c->id]);
}
$cols = ['s' => 4, 'm' => 3, 'l' => 2][$d['size'] ?? 'm'] ?? 3;
$hTag = '' !== trim((string) ($d['title'] ?? '')) ? 'h3' : 'h2';
// Blättern ohne Seitenwechsel (Live::region – Links mit data-live-nav, ohne JavaScript normale Links)
$reload = $pg && $pg['pages'] > 1 ? Live::region($b) : '';
?>
<div class="wrap dl dl--<?= e($layout) ?>"<?= $reload ?>>
  <?= fluid_head($b, 'dl-head') ?>
  <?php if ('toc' === $layout && $rows): // Inhaltsverzeichnis: Kapitelbaum (Wissen) bzw. nummerierte Liste?>
  <?php Kit::active()->markUsed('@knowledge') ?>
  <?= Knowledge::is($t) ? Knowledge::toc($b, $d, $t, $here)
      : '<div class="kn-toc"><ol class="kn-toc__list" role="list">' . implode('', array_map(static fn ($r, $i) => '<li class="kn-toc__item kn-toc__item--0">' . ($detailOn ? '<a href="' . e($href($r)) . '">' : '<span class="kn-toc__a">') . '<span class="kn-toc__n">' . ($i + 1) . '</span><span class="kn-toc__t">' . e($title($r)) . '</span>' . ($detailOn ? '</a>' : '</span>') . '</li>', $rows, array_keys($rows))) . '</ol></div>' ?>
  <?php elseif (!$rows): ?>
  <p class="dl-empty"><?= e(trim((string) ($d['empty_text'] ?? '')) ?: lt('Zurzeit gibt es hier keine Einträge.')) ?></p>
  <?php elseif ('table' === $layout): ?>
  <?php Kit::active()->markUsed('@table');
  if (!empty($d['table_sort']) && !is_editing()) {
  Kit::active()->markUsed('@tablesort');
  } ?>
  <div class="dl-tablewrap tbl-scroll ncss-table-scroll ncss-scroll-shadow" role="region" tabindex="0" aria-label="<?= e(lt('Tabelle') . ('' !== trim((string) $d['title']) ? ': ' . strip_emphasis(trim((string) $d['title'])) : '')) ?>"><table class="dl-table"<?= !empty($d['table_sort']) && !is_editing() ? ' data-ncss-sort' : '' ?>>
    <thead><tr>
      <th scope="col"><?= e($titleF ? $titleF->translatedLabel() : lt('Eintrag')) ?></th>
      <?php // Zahlen rechtsbündig, Typ fest statt erraten (Datum/Zahl, data-sort-value = gespeicherter Wert)
      $sortT = static fn ($f) => !empty($d['table_sort']) ? YFormBridge::sortType($f) : '';
      foreach ([$metaF, $textF] as $f): if (!$f || $same($f, $titleF)) {
      continue;
      } $st = $sortT($f) ?><th scope="col"<?= '' !== $st ? ' data-sort-type="' . $st . '"' . ('number' === $st ? ' data-sort-align="end" class="dl-num"' : '') : '' ?>><?= e($f->translatedLabel()) ?></th><?php endforeach ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $detailOn ? '<a href="' . e($href($r)) . '">' . e($title($r)) . '</a>' : e($title($r)) ?> <?= $editBtn($edit($r->id), 'Bearbeiten') ?></td>
        <?php foreach ([$metaF, $textF] as $f): if (!$f || $same($f, $titleF)) {
        continue;
        } $st = $sortT($f);
        $raw = '' !== $st ? YFormBridge::sortValue($r, $f) : '' ?><td<?= 'number' === $st ? ' class="dl-num"' : '' ?><?= '' !== $raw ? ' data-sort-value="' . e($raw) . '"' : '' ?>><?= YFormBridge::html($r, $f) ?></td><?php endforeach ?>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <?php // Filter für Besucher (Feld „Filter für Besucher nach …“): Kategorien aus dem Feld, Leiste über der Liste (fluid_filter())
  $visF = YFormBridge::field($t, (string) ($d['visitor_filter'] ?? ''));
  $rowsL = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);
  $flt = $visF ? fluid_filter($b, array_map(static fn ($r) => trim(html_entity_decode(strip_tags(YFormBridge::html($r, $visF)))), $rowsL)) : ['bar' => '', 'item' => []];
  if ('' !== $flt['bar']): ?><ncss-filter class="ncss-filter"><?= $flt['bar'] ?><?php endif ?>
  <ul class="dl-items<?= 'cards' === $layout ? ' dl-cols-' . $cols : '' ?>" role="list">
    <?php foreach ($rowsL as $ri => $r): $name = $title($r);
    $url = $detailOn ? $href($r) : null ?>
    <li class="dl-item<?= isset($flt['item'][$ri]) ? ' ncss-filter-item' : '' ?>"<?= isset($flt['item'][$ri]) ? ' data-category="' . e($flt['item'][$ri]) . '"' : '' ?>><?= $editBtn($edit($r->id), 'Eintrag bearbeiten') ?>
      <?php if ($imageF): $p = $pic($r, 'cards' === $layout ? '(min-width: 1080px) 400px, 100vw' : '(min-width: 1080px) 320px, 40vw', '16:10') ?>
      <div class="dl-img ratio-16-10"><?= '' !== $p ? $p : '<span class="dl-ph" aria-hidden="true"></span>' ?></div>
      <?php endif ?>
      <div class="dl-body">
        <?php if ($metaF && !$same($metaF, $titleF) && ($m = YFormBridge::html($r, $metaF)) !== ''): ?><p class="dl-meta"><?= $m ?></p><?php endif ?>
        <<?= $hTag ?> class="dl-name"><?= $url ? '<a href="' . e($url) . '" class="dl-link">' . e($name) . '</a>' : e($name) ?></<?= $hTag ?>>
        <?php if ($textF && !$same($textF, $titleF) && ($x = YFormBridge::text($r, $textF)) !== ''): ?><div class="dl-f dl-clamp dl-clamp-3"><p><?= e(mb_strimwidth(strip_tags($x), 0, 320, ' …')) ?></p></div><?php endif ?>
      </div>
    </li>
    <?php endforeach ?>
  </ul>
  <?php if ('' !== $flt['bar']): ?></ncss-filter><?php endif ?>
  <?php endif ?>
  <?php if ($pg && $pg['pages'] > 1): $pq = static fn (int $n) => $here . ($n > 1 ? (str_contains($here, '?') ? '&' : '?') . 'seite=' . $n : '') . '#' . $b->domId() ?>
  <nav class="posts-pager" aria-label="<?= e(lt('Blättern')) ?>">
    <?php if ($pg['page'] > 1): ?><a href="<?= e($pq($pg['page'] - 1)) ?>" data-live-nav rel="prev">← <?= e(lt('Zurück')) ?></a><?php else: ?><span></span><?php endif ?>
    <span class="posts-pager__n" data-live-count><?= e(lt('Seite {n} von {total}', ['n' => $pg['page'], 'total' => $pg['pages']])) ?></span>
    <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e($pq($pg['page'] + 1)) ?>" data-live-nav rel="next"><?= e(lt('Weiter')) ?> →</a><?php else: ?><span></span><?php endif ?>
  </nav>
  <?php endif ?>
  <?php if ($new = $edit()): ?><p class="fdl-new"><?= $editBtn($new, '+ Neuer Eintrag') ?></p><?php endif ?>
</div>
<?= '' !== $reload ? Live::assets() : '' /* live.js nur, wenn die Liste nachladen kann */ ?>

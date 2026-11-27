<?php
/**
 * Glossar (Klxm\Fluid\Glossary\Glossary): Übersicht von A bis Z mit Suche – auf der Adresse eines Begriffs ({seite}/{slug}) seine
 * Detailseite mit Kurztext und ausführlicher Erklärung (Blöcke, im Bearbeiten-Modus bearbeitbar). Markup der Liste wie die
 * Datenliste (.dl-az), Begriffe als Beschreibungsliste.
 * @var Block $b  @var array $d
 */
use Klxm\Fluid\Block;
use Klxm\Fluid\Editing;
use Klxm\Fluid\Glossary\Glossary;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Routing\Router;
use Redaxo\Core\Language\Language;

if (!Glossary::available()) {
    if (is_editing()) {
        echo '<div class="wrap"><p class="empty-hint">' . e('Für das Glossar wird das AddOn YForm benötigt (danach console migrate).') . '</p></div>';
    }

    return;
}
Kit::active()->markUsed('@glossary');
$here = Router::url($b->articleId, Language::getCurrentId());
$term = Glossary::current();
if (null !== $term):
    $long = $term['long'] || is_editing() ? Glossary::store()->longHtml((int) $term['id'], $b->articleId) : '';
    $edit = Glossary::store()->canEdit(Editing::user()) && is_editing() ? Glossary::store()->editUrl((int) $term['id']) : null;
?>
<div class="wrap glx glx--term">
  <p class="df-back"><a href="<?= e($here) ?>">← <?= e(lt('Zum Glossar')) ?></a></p>
  <header class="glx-term__head">
    <p class="eyebrow"><?= e(lt('Glossar')) ?></p>
    <?php $hx = null === $b->prev ? 'h1' : 'h2' ?><<?= $hx ?> class="glx-term__title"><?= e($term['term']) ?></<?= $hx ?>>
    <?php if ($term['variants']): ?><p class="glx-term__alt"><?= e(lt('Auch')) ?>: <?= e(implode(', ', array_map(static fn ($v) => trim($v, '"„“'), $term['variants']))) ?></p><?php endif ?>
    <p class="glx-term__short"><?= e($term['short']) ?></p>
    <?php if ($edit): ?><p><a class="fdl-edit" href="<?= e($edit) ?>" target="_blank" rel="noopener"><?= e('Begriff bearbeiten') ?></a></p><?php endif ?>
  </header>
</div>
<?php if ('' !== $long): ?><div class="glx-term__long"><?= $long ?></div><?php endif ?>
<?php
    return;
endif;

$terms = Glossary::terms();
$groups = [];
foreach ($terms as $t) {
    $first = mb_strtoupper(mb_substr(Normalizer::normalize($t['term'], Normalizer::FORM_D) ?: $t['term'], 0, 1));
    $groups[preg_match('~\p{Lu}~u', $first) ? $first : '#'][] = $t;
}
ksort($groups);
$id = $b->domId();
?>
<div class="wrap dl glx" data-glx-list data-glossary="off">
  <?= fluid_head($b, 'dl-head') ?>
  <?php if (!$terms): ?>
  <p class="dl-empty"><?= e(lt('Im Glossar stehen noch keine Begriffe.')) ?></p>
  <?php else: ?>
  <?php if (!empty($d['search'])): ?>
  <div class="dl-tools glx-search" data-glx-search hidden>
    <label class="dl-search__label" for="<?= e($id) ?>-q"><?= e(lt('Im Glossar suchen')) ?></label>
    <div class="dl-search__row"><input class="dl-search__input" id="<?= e($id) ?>-q" type="search" autocomplete="off"></div>
    <p class="dl-count" role="status" data-glx-count data-none="<?= e(lt('Kein Begriff gefunden.')) ?>" data-one="<?= e(lt('1 Begriff')) ?>" data-many="<?= e(lt('{n} Begriffe')) ?>"></p>
  </div>
  <?php endif ?>
  <?php if (!empty($d['letters']) && count($groups) > 1): ?>
  <nav class="dl-az" aria-label="<?= e(lt('Buchstaben')) ?>"><ul class="dl-az__list" role="list">
    <?php foreach (array_keys($groups) as $l): ?><li><a href="#<?= e($id . '-' . ('#' === $l ? 'x' : $l)) ?>" data-glx-letter="<?= e($id . '-' . ('#' === $l ? 'x' : $l)) ?>"><?= e($l) ?></a></li><?php endforeach ?>
  </ul></nav>
  <?php endif ?>
  <?php foreach ($groups as $l => $list): ?>
  <div class="glx-group" id="<?= e($id . '-' . ('#' === $l ? 'x' : $l)) ?>" data-glx-group>
    <h2 class="dl-az__head"><?= e($l) ?></h2>
    <dl class="glx-list">
      <?php foreach ($list as $t): ?>
      <div class="glx-item" data-glx="<?= e($t['term'] . ' ' . implode(' ', $t['variants']) . ' ' . $t['short']) ?>">
        <dt><?= null !== $t['url'] ? '<a href="' . e($t['url']) . '">' . e($t['term']) . '</a>' : e($t['term']) ?><?= !$t['online'] ? ' <span class="glx-draft">' . e(lt('Entwurf')) . '</span>' : '' ?></dt>
        <dd><?= e($t['short']) ?></dd>
      </div>
      <?php endforeach ?>
    </dl>
  </div>
  <?php endforeach ?>
  <?php endif ?>
</div>

<?php
/**
 * Contact: details (address, phone/email, opening hours) as a card grid, next to it map and/or form (from KLXM
 * Studio). Sidebar pattern: form/map take the larger part and wrap below the details when space is tight.
 * Everything from „Website“; form = #[AsForm] class from YForm (Klxm\Fluid\Data\YFormBridge).
 * @var Block $b  @var array $d
 */
use Klxm\Fluid\Block;
use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Maps\Maps;

$address = fluid_address_lines();
$phone = fluid_phone();
$tel = fluid_phone_href();
$email = fluid_email();
$hours = !empty($d['show_hours']) ? fluid_hours() : [];
$hoursNote = trim((string) setting('hours_note', ''));
$map = !empty($d['show_map']) && Maps::siteLocation() ? Maps::renderBlock(['location' => 'site', 'zoom' => 15, 'height' => 'm', 'route' => true]) : '';
$def = YFormBridge::formDefinition((string) ($d['form'] ?? ''));
$form = $def ? YFormBridge::runForm($def) : '';
$c = $b->central('Adresse, Telefon, E-Mail und Zeiten unter „Website“ (Menü ⋯) ändern');
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <div class="contact<?= ('' !== $form || '' !== $map) ? ' contact--aside' : '' ?>">
    <div class="contact__info"<?= $c ?>>
      <?php if ($address || is_editing()): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('map-pin') ?></span>
        <h3 class="contact__h"><?= e(lt('Adresse')) ?></h3>
        <address class="contact__address"><?= e(fluid_name()) ?><?php if ($address): ?><br><?= implode('<br>', array_map('e', $address)) ?><?php else: ?><br>[<?= e('Adresse unter „Website“ eintragen') ?>]<?php endif ?></address>
      </div>
      <?php endif ?>
      <?php if (($tel && '' !== $phone) || '' !== $email || is_editing()): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('chat-circle-text') ?></span>
        <h3 class="contact__h"><?= e(lt('Telefon & E-Mail')) ?></h3>
        <ul class="contact__list" role="list">
          <?php if ($tel && '' !== $phone): ?><li><span class="contact__label"><?= e(lt('Telefon')) ?></span><a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif ?>
          <?php if ('' !== $email): ?><li><span class="contact__label"><?= e(lt('E-Mail')) ?></span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif ?>
          <?php if (!($tel && '' !== $phone) && '' === $email): ?><li>[<?= e('Telefon und E-Mail unter „Website“ eintragen') ?>]</li><?php endif ?>
        </ul>
      </div>
      <?php endif ?>
      <?php if ($hours): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('clock') ?></span>
        <h3 class="contact__h"><?= e(lt('Öffnungszeiten')) ?></h3>
        <dl class="hours"><?php foreach ($hours as $h): ?><div class="hours__row"><dt><?= e($h['days']) ?></dt><dd><?= e($h['time']) ?></dd></div><?php endforeach ?></dl>
        <?php if ('' !== $hoursNote): ?><p class="contact__note"><?= e($hoursNote) ?></p><?php endif ?>
      </div>
      <?php endif ?>
    </div>
    <?php if ('' !== $form || '' !== $map): ?>
    <div class="contact__main">
      <?php if ('' !== $form): ?>
      <div class="contact__form dff-wrap card">
        <?php if ('' !== trim((string) ($d['form_title'] ?? ''))): ?><h3 class="contact__formtitle h3"<?= $b->edit('form_title') ?>><?= fluid_title((string) $d['form_title']) ?></h3><?php endif ?>
        <?= $form ?>
      </div>
      <?php endif ?>
      <?php if ('' !== $map): ?><div class="contact__map"><?= $map ?></div><?php endif ?>
    </div>
    <?php endif ?>
  </div>
  <?php if ('' !== (string) ($d['note'] ?? '') || is_editing()): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= fluid_title((string) ($d['note'] ?? '')) ?></p><?php endif ?>
</div>

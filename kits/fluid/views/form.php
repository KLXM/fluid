<?php
/**
 * Form from YForm (#[AsForm] class in the project): YForm handles submitting, validation, CSRF, honeypot and actions;
 * fluid renders it in the kit's style (YFormBridge::theme). single: below the headline · split: header left, form right.
 * @var Block $b  @var array $d
 */
use Klxm\Fluid\Block;
use Klxm\Fluid\Data\BuiltForm;
use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Legal\Legal;
use Klxm\Fluid\Legal\LegalDialog;
use Redaxo\Core\Content\Article;

$v = 'split' === $b->variant() ? 'split' : 'single';
// form class from the project (#[AsForm]) or the own form from the block's fields (Data\BuiltForm)
$def = YFormBridge::formDefinition((string) ($d['form'] ?? ''));
if (!$def && YFormBridge::available() && [] !== (array) ($d['fields'] ?? [])) {
    $def = BuiltForm::make($b->id, $d, Article::getCurrent());
}
$form = '';
if ($def) {
    $form = YFormBridge::runForm($def);
    if (is_editing() && $def instanceof BuiltForm) {
        $mode = ['save_mail' => 'speichert und sendet per E-Mail', 'save' => 'speichert', 'mail' => 'sendet per E-Mail'][$d['mode'] ?? 'save_mail'] ?? '';
        $warn = in_array($d['mode'] ?? 'save_mail', ['mail', 'save_mail'], true) && '' === trim((string) ($d['mail_to'] ?? '')) ? ' – <b>E-Mail-Empfänger fehlt</b>' : '';
        $form = '<p class="empty-hint">Eigenes Formular: ' . e($mode) . $warn . '. Felder und Ablauf in der Seitenleiste.</p>' . $form;
    }
} elseif (is_editing()) {
    $form = '<p class="empty-hint">' . e(YFormBridge::available() ? 'Bitte in der Seitenleiste Felder anlegen oder ein Formular wählen.' : 'Für diesen Block wird das AddOn YForm benötigt.') . '</p>';
}
$note = trim((string) ($d['note'] ?? ''));
$privacy = '' !== $form ? Legal::url('privacy') : '';
?>
<div class="wrap fform fform--<?= e($v) ?>">
  <?= fluid_head($b, 'split' === $v ? 'fform__head' : '') ?>
  <div class="fform__body dff-wrap">
    <?= $form ?>
    <?php if ('' !== $note || (is_editing() && $def)): ?><p class="dff-note fform__note"<?= $b->edit('note', 'lines') ?>><?= nl2br(e($note), false) ?></p><?php endif ?>
    <?php if ('' !== $privacy): ?><p class="dff-note fform__privacy"><a href="<?= e($privacy) ?>"<?= LegalDialog::attrs() ?>><?= e(lt('Datenschutzhinweise')) ?></a></p><?= LegalDialog::script() ?><?php endif ?>
  </div>
</div>

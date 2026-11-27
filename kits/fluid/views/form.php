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
use Redaxo\Core\Translation\I18n;

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
        $mode = ['save_mail' => I18n::rawMsg('fluid_kit_form_mode_save_mail'), 'save' => I18n::rawMsg('fluid_kit_form_mode_save'), 'mail' => I18n::rawMsg('fluid_kit_form_mode_mail')][$d['mode'] ?? 'save_mail'] ?? '';
        $warn = in_array($d['mode'] ?? 'save_mail', ['mail', 'save_mail'], true) && '' === trim((string) ($d['mail_to'] ?? ''));
        $form = '<p class="empty-hint">' . I18n::msg($warn ? 'fluid_kit_form_own_no_recipient' : 'fluid_kit_form_own', $mode) . '</p>' . $form;
    }
} elseif (is_editing()) {
    $form = '<p class="empty-hint">' . e(YFormBridge::available() ? I18n::rawMsg('fluid_kit_form_empty') : I18n::rawMsg('fluid_kit_needs_yform')) . '</p>';
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

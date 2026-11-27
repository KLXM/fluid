<?php

namespace Klxm\Fluid\Data;

use Klxm\Fluid\Activity;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Translation\I18n;
use Throwable;
use Yakamara\YForm\Form\Action\Action;
use Yakamara\YForm\Form\Action\SendMail;
use Yakamara\YForm\Form\Action\Submission;

// only with YForm (fluid also runs without it)
if (!class_exists(Action::class)) {
    return;
}

/**
 * Mail of a form action (YForm `SendMail`) whose failure does not discard the input.
 *
 * Without this wrapper YForm aborts all further actions on a mail error and shows „Fehler – bitte später noch
 * einmal“ – although the entry is already saved. Visitors then submit again (duplicate entries), the
 * confirmation is missing. If the entry is saved ($saved), the error is logged (system log,
 * activity) and the next action continues; without a saved entry the error stands (otherwise data would
 * be lost).
 */
final class SafeMail extends Action
{
    public const string TEAM = 'team';
    public const string CONFIRM = 'confirm';

    /** @var list<string> mails that failed in this request (TEAM, CONFIRM) */
    public static array $failed = [];

    public function __construct(
        private readonly SendMail $mail,
        private readonly string $kind,
        private readonly bool $saved,
    ) {}

    public function execute(Submission $submission): void
    {
        try {
            $this->mail->execute($submission);
        } catch (Throwable $e) {
            if (!$this->saved) {
                throw $e;
            }
            self::$failed[] = $this->kind;
            Logger::logException($e);
            Activity::log('form_mail_failed', null, I18n::rawMsg(self::TEAM === $this->kind ? 'fluid_safemail_team_failed' : 'fluid_safemail_confirm_failed', $e->getMessage()));
        }
    }
}

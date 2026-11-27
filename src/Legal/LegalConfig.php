<?php

namespace Klxm\Fluid\Legal;

/**
 * Settings of the legal texts, set by the project (like the core policies) – in Project::configure():
 *
 *     Legal::$config = new LegalConfig(dialog: true);
 *
 * By default the dialog is off: links to legal texts stay ordinary links.
 */
final readonly class LegalConfig
{
    /** @param bool $dialog open privacy notices of forms in a dialog above the page (input is kept) */
    public function __construct(
        public bool $dialog = false,
    ) {}
}

<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\ExtensionPoint\ArticleUsageCheck;
use Redaxo\Core\ExtensionPoint\Extension;

/**
 * Protect the category „Seitenvorlagen“: not deletable while fluid is installed (the protection hangs on this
 * extension point – without fluid it is an ordinary category). Who sees it in the backend is governed by the core's
 * structure permission; on the website (page tree, search, drafts) fluid hides it, it is managed in the dialog „Vorlagen …“.
 */
final class PageTemplatesGuard
{
    public static function boot(): void
    {
        Extension::register(ArticleUsageCheck::class, static function (ArticleUsageCheck $ep): void {
            if (null !== PageTemplates::categoryId() && $ep->article->id === PageTemplates::categoryId()) {
                $ep->usages->add('fluid', 'Seitenvorlagen der Website – lässt sich erst löschen, wenn fluid deinstalliert ist');
            }
        });
    }
}

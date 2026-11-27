<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Database\Sql;
use Throwable;

/**
 * Activity log (model from KLXM Studio – Core\Activity): who saved, published, created, moved … which page
 * when (table rex_fluid_activity, the last MAX entries). No IP addresses.
 */
final class Activity
{
    public const string TABLE = 'rex_fluid_activity';

    public const array LABELS = [
        'save' => 'gespeichert', 'publish' => 'veröffentlicht', 'discard' => 'Entwurf verworfen', 'restore' => 'Version wiederhergestellt',
        'page_add' => 'Seite angelegt', 'page_settings' => 'Seiteneinstellungen geändert', 'tree' => 'Seitenstruktur geändert',
        'design' => 'Design geändert', 'site' => 'Website-Einstellungen geändert', 'block_add' => 'Block eingefügt', 'block_delete' => 'Block gelöscht',
        'form_mail_failed' => 'Formular-Mail nicht gesendet', 'lock_taken' => 'Bearbeitung übernommen',
    ];
    private const int MAX = 2000;

    public static function log(string $action, ?int $articleId = null, string $detail = ''): void
    {
        // search index: every logged change (editor, MCP) can be live (without working version) – have it rebuilt
        if (!Editing::isGuest()) {   // guests (guest access) never change anything permanently
            Search\Search::markDirty();
        }
        try {
            $user = Editing::user();
            Sql::factory()->setTable(self::TABLE)->setValues([
                'user' => $user ? ($user->name ?: $user->login) : '', 'action' => $action, 'article_id' => $articleId,
                'detail' => mb_substr($detail, 0, 190),
            ])->setRawValue('created', 'NOW()')->insert();
            if (1 === random_int(1, 50)) {
                Sql::factory()->setQuery('DELETE FROM ' . self::TABLE . ' WHERE id < (SELECT m FROM (SELECT MAX(id) - ' . self::MAX . ' AS m FROM ' . self::TABLE . ') x)');
            }
        } catch (Throwable) {
            // the log is never more important than the action itself
        }
    }

    /** @return list<array{when: string, user: string, action: string, page: string, url: string, detail: string}> */
    public static function recent(int $limit = 100): array
    {
        try {
            $rows = Sql::factory()->getArray('SELECT * FROM ' . self::TABLE . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
        } catch (Throwable) {
            return [];
        }

        return array_map(static function (array $r): array {
            $a = $r['article_id'] ? Article::get((int) $r['article_id']) : null;

            return ['when' => (string) $r['created'], 'user' => (string) $r['user'], 'action' => self::LABELS[$r['action']] ?? (string) $r['action'],
                'page' => $a->name ?? '', 'url' => $a?->getUrl() ?? '', 'detail' => (string) $r['detail']];
        }, $rows);
    }
}

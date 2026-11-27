<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Throwable;

use function usort;

/**
 * Edit lock (Website → Einstellungen → Redaktion, off by default; requires presence) – from KLXM Studio
 * (Core\EditLock, MIT).
 *
 * The lock is held by whoever has had a page in one language in edit mode the longest without interruption – derived from
 * presence (heartbeat about every 15 s, released at most Presence::TTL seconds after closing). No own table, no stale
 * locks. Whoever is locked out only views the page (even with ?fluid_edit=1); fluid rejects saving via the editor, the
 * sidebar and MCP with HTTP 423. Administrators take over with take(): their entry moves ahead of the previous holder,
 * who learns about it on the next heartbeat. Guests (guest access) and processes without a person (console, cronjob)
 * lock nothing. The core's backend doesn't know about the lock.
 */
final class EditLock
{
    public const string TYPE = 'article';

    /** Self-test: on/off regardless of the setting */
    private static ?bool $force = null;

    public static function on(): bool
    {
        if (null !== self::$force) {
            return self::$force && PresenceApi::on();
        }
        try {
            return (bool) Settings::get('edit_lock', false) && PresenceApi::on();
        } catch (Throwable) {
            return false;
        }
    }

    /** Presence key for a page in one language („language:article“) */
    public static function id(Article $article): string
    {
        return $article->languageId . ':' . $article->id;
    }

    /**
     * Lock holder: active presence entry with the earliest start – or null.
     *
     * @return array<string, mixed>|null
     */
    public static function holder(Article $article): ?array
    {
        if (!self::on()) {
            return null;
        }
        $list = Presence::list(['site' => PresenceApi::SITE, 'type' => self::TYPE, 'id' => self::id($article)]);
        usort($list, static fn (array $a, array $b): int => [(int) $a['since'], (string) $a['user']] <=> [(int) $b['since'], (string) $b['user']]);

        return $list[0] ?? null;
    }

    /**
     * Does someone else lock the page for this person? Entry of the holder, otherwise null.
     *
     * @param int|false|null $userId ID of the person; false = MCP key without a person (always counts as someone else),
     *     null = whoever is currently logged in
     * @return array<string, mixed>|null
     */
    public static function blockedFor(Article $article, int|false|null $userId = null): ?array
    {
        if (null === $userId) {
            if (Editing::isGuest()) {
                return null;   // Guests never save permanently
            }
            $userId = Editing::user()?->id;
            if (null === $userId) {
                return null;   // without a person (console, cronjob)
            }
        }
        $holder = self::holder($article);
        if (null === $holder || (false !== $userId && (string) $holder['user'] === (string) $userId)) {
            return null;
        }

        return $holder;
    }

    /** @param array<string, mixed> $holder */
    public static function message(array $holder): string
    {
        return I18n::rawMsg('fluid_editlock_message', (string) ($holder['name'] ?? '?'));
    }

    /**
     * Prevent saving when someone else locks the page (HTTP 423).
     *
     * @param int|false|null $userId see blockedFor()
     */
    public static function assert(Article $article, int|false|null $userId = null): void
    {
        $holder = self::blockedFor($article, $userId);
        if (null !== $holder) {
            throw new ApiError(self::message($holder), 423);
        }
    }

    /** May the person take over someone else's lock? (administration) */
    public static function canTake(?User $user = null): bool
    {
        $user ??= Editing::user();

        return null !== $user && $user->admin && !Editing::isGuest();
    }

    /**
     * Take over the lock: the own presence entry (must exist) moves ahead of the previous holder.
     *
     * @param bool $log record in the activity log (the self-test doesn't)
     */
    public static function take(Article $article, int $userId, bool $log = true): bool
    {
        $holder = self::holder($article);
        if (null === $holder || (string) $holder['user'] === (string) $userId) {
            return false;
        }
        Presence::setSince(['site' => PresenceApi::SITE, 'type' => self::TYPE, 'id' => self::id($article), 'user' => (string) $userId], (int) $holder['since'] - 1);
        if ($log) {
            Activity::log('lock_taken', $article->id, 'von ' . (string) ($holder['name'] ?? '?'));
        }

        return true;
    }

    /**
     * Self-test (fluid:selftest): holder, lock on save, MCP without a person, take over, disabled. Works with
     * presence entries for a page that doesn't exist and cleans them up again.
     *
     * @return array{ok: int, fails: list<string>}
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = static function (string $label, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) {
                ++$ok;
            } else {
                $fails[] = 'Sperre – ' . $label . ': ' . var_export($got, true) . ' statt ' . var_export($want, true);
            }
        };
        $article = Article::getSiteStartArticle();
        if (null === $article) {
            return ['ok' => 0, 'fails' => ['Sperre – keine Startseite für den Test']];
        }
        // Test persons without an account: IDs that don't exist
        $a = 990001;
        $b = 990002;
        $who = static fn (int $u): array => ['site' => PresenceApi::SITE, 'type' => self::TYPE, 'id' => self::id($article), 'user' => (string) $u];
        try {
            self::$force = true;
            Presence::beat($who($a), ['name' => 'Anna'], time() - 30);
            Presence::beat($who($b), ['name' => 'Ben']);
            $eq('Inhaber = wer zuerst bearbeitet', (string) (self::holder($article)['user'] ?? ''), (string) $a);
            $eq('für Ben gesperrt', (string) (self::blockedFor($article, $b)['name'] ?? ''), 'Anna');
            $eq('für Anna frei', self::blockedFor($article, $a), null);
            $eq('Speichern durch Ben abgelehnt (423)', (static function () use ($article, $b): int {
                try {
                    self::assert($article, $b);

                    return 0;
                } catch (ApiError $e) {
                    return $e->getCode();
                }
            })(), 423);
            $eq('MCP-Schlüssel ohne Person gilt als fremd', null !== self::blockedFor($article, false), true);
            $eq('Übernehmen', self::take($article, $b, false), true);
            $eq('nach Übernahme: Ben hält', (string) (self::holder($article)['user'] ?? ''), (string) $b);
            $eq('nach Übernahme: Anna gesperrt', (string) (self::blockedFor($article, $a)['name'] ?? ''), 'Ben');
            self::$force = false;
            $eq('abgeschaltet: keine Sperre', self::blockedFor($article, $a), null);
        } finally {
            foreach ([$a, $b] as $u) {
                Presence::leave($who($u));
            }
            self::$force = null;
        }

        return ['ok' => $ok, 'fails' => $fails];
    }
}

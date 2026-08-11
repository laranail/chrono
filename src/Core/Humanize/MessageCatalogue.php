<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Humanize;

use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\TimeUnit;

/**
 * ICU MessageFormat patterns for humanised time, per locale.
 *
 * Every pattern is an ICU plural selector rather than a `singular|plural` pair, because that pair is
 * a fiction outside a handful of languages. Arabic distinguishes **six** categories — zero, one,
 * two, few, many, other — and Laravel's `trans_choice` pipe syntax cannot express them:
 *
 *     1 → يوم واحد     2 → يومان     5 → ٥ أيام     21 → ٢١ يوماً
 *
 * Russian needs one/few/many; Polish and Welsh differ again. `MessageFormatter` gets all of this
 * right from CLDR, so the catalogue holds patterns and lets ICU choose.
 *
 * English, Swahili, Arabic and French ship built in. Any other locale falls back to English until
 * an application registers its own via `with()` — the Laravel bridge wires published translation
 * files in that way.
 */
final readonly class MessageCatalogue
{
    /** @param array<string, array<string, string>> $overrides locale => key => ICU pattern */
    public function __construct(private array $overrides = []) {}

    /**
     * Register or replace patterns for a locale.
     *
     * @param array<string, string> $patterns
     */
    #[NoDiscard]
    public function with(string $locale, array $patterns): self
    {
        $merged = $this->overrides;
        $merged[$locale] = [...($merged[$locale] ?? []), ...$patterns];

        return clone ($this, ['overrides' => $merged]);
    }

    public function pattern(string $locale, string $key): string
    {
        return $this->resolve($locale, $key)['pattern'];
    }

    /**
     * The pattern *and* the locale it came from.
     *
     * Both are needed together. `MessageFormatter` selects a plural category using the locale it is
     * given, so formatting an English pattern under an unknown locale tag picks the wrong branch —
     * `xx_YY` has no CLDR rules, falls back to "other", and renders "1 days". The caller must format
     * with the locale that actually owns the pattern.
     *
     * @return array{pattern: string, locale: string}
     */
    public function resolve(string $locale, string $key): array
    {
        foreach ($this->candidates($locale) as $candidate) {
            if (isset($this->overrides[$candidate][$key])) {
                return ['pattern' => $this->overrides[$candidate][$key], 'locale' => $candidate];
            }

            if (isset(self::BUILT_IN[$candidate][$key])) {
                return ['pattern' => self::BUILT_IN[$candidate][$key], 'locale' => $candidate];
            }
        }

        return ['pattern' => self::BUILT_IN['en'][$key] ?? '{n} ' . $key, 'locale' => 'en'];
    }

    public function has(string $locale, string $key): bool
    {
        return array_any($this->candidates($locale), fn (string $candidate): bool => isset($this->overrides[$candidate][$key]) || isset(self::BUILT_IN[$candidate][$key]));
    }

    /**
     * `sw_KE` is tried, then `sw`, then `en`.
     *
     * @return list<string>
     */
    private function candidates(string $locale): array
    {
        $normalised = str_replace('-', '_', $locale);
        $primary = strstr($normalised, '_', true);

        return array_values(array_unique(array_filter(
            [$normalised, $primary === false ? null : $primary, 'en'],
            static fn (?string $candidate): bool => $candidate !== null && $candidate !== '',
        )));
    }

    /** @var array<string, array<string, string>> */
    private const array BUILT_IN = [
        'en' => [
            'second' => '{n, plural, one {# second} other {# seconds}}',
            'minute' => '{n, plural, one {# minute} other {# minutes}}',
            'hour' => '{n, plural, one {# hour} other {# hours}}',
            'day' => '{n, plural, one {# day} other {# days}}',
            'week' => '{n, plural, one {# week} other {# weeks}}',
            'month' => '{n, plural, one {# month} other {# months}}',
            'year' => '{n, plural, one {# year} other {# years}}',
            'past' => '{value} ago',
            'future' => 'in {value}',
            'now' => 'just now',
            'separator' => ' ',
        ],
        'sw' => [
            'second' => '{n, plural, one {sekunde #} other {sekunde #}}',
            'minute' => '{n, plural, one {dakika #} other {dakika #}}',
            'hour' => '{n, plural, one {saa #} other {masaa #}}',
            'day' => '{n, plural, one {siku #} other {siku #}}',
            'week' => '{n, plural, one {wiki #} other {wiki #}}',
            'month' => '{n, plural, one {mwezi #} other {miezi #}}',
            'year' => '{n, plural, one {mwaka #} other {miaka #}}',
            'past' => 'tangu {value}',
            'future' => 'baada ya {value}',
            'now' => 'sasa hivi',
            'separator' => ' ',
        ],
        'ar' => [
            'second' => '{n, plural, zero {# ثانية} one {ثانية واحدة} two {ثانيتان} few {# ثوانٍ} many {# ثانية} other {# ثانية}}',
            'minute' => '{n, plural, zero {# دقيقة} one {دقيقة واحدة} two {دقيقتان} few {# دقائق} many {# دقيقة} other {# دقيقة}}',
            'hour' => '{n, plural, zero {# ساعة} one {ساعة واحدة} two {ساعتان} few {# ساعات} many {# ساعة} other {# ساعة}}',
            'day' => '{n, plural, zero {# يوم} one {يوم واحد} two {يومان} few {# أيام} many {# يوماً} other {# يوم}}',
            'week' => '{n, plural, zero {# أسبوع} one {أسبوع واحد} two {أسبوعان} few {# أسابيع} many {# أسبوعاً} other {# أسبوع}}',
            'month' => '{n, plural, zero {# شهر} one {شهر واحد} two {شهران} few {# أشهر} many {# شهراً} other {# شهر}}',
            'year' => '{n, plural, zero {# سنة} one {سنة واحدة} two {سنتان} few {# سنوات} many {# سنة} other {# سنة}}',
            'past' => 'منذ {value}',
            'future' => 'خلال {value}',
            'now' => 'الآن',
            'separator' => ' و',
        ],
        'fr' => [
            'second' => '{n, plural, one {# seconde} other {# secondes}}',
            'minute' => '{n, plural, one {# minute} other {# minutes}}',
            'hour' => '{n, plural, one {# heure} other {# heures}}',
            'day' => '{n, plural, one {# jour} other {# jours}}',
            'week' => '{n, plural, one {# semaine} other {# semaines}}',
            'month' => '{n, plural, one {# mois} other {# mois}}',
            'year' => '{n, plural, one {# an} other {# ans}}',
            'past' => 'il y a {value}',
            'future' => 'dans {value}',
            'now' => "à l'instant",
            'separator' => ' ',
        ],
    ];

    /** @return list<string> the locales with built-in patterns */
    public static function builtInLocales(): array
    {
        return array_keys(self::BUILT_IN);
    }

    public static function keyFor(TimeUnit $unit): string
    {
        return $unit->value;
    }
}

<?php

/**
 * @package Tagged
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Tagged\Html\Plugins;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;

use DateInterval;
use DateTime;
use DateTimeZone;

use DecodeLabs\Exceptional;
use DecodeLabs\Tagged\Html\Element;
use DecodeLabs\Tagged\Html\Factory as HtmlFactory;
use DecodeLabs\Veneer\Plugin;

use IntlDateFormatter;

class Time implements Plugin
{
    use SystemicProxyTrait;

    protected $html;

    /**
     * Init with parent factory
     */
    public function __construct(HtmlFactory $html)
    {
        $this->html = $html;
    }

    /**
     * Custom format a date and wrap it
     */
    public function format($date, string $format, $timezone = true): ?Element
    {
        if (!$date = $this->prepare($date, $timezone, true)) {
            return null;
        }

        return $this->wrap(
            $date->format($timezone === false ? 'Y-m-d' : \DateTime::W3C),
            $date->format($format)
        );
    }

    /**
     * Custom format a date and wrap it
     */
    public function formatDate($date, string $format): ?Element
    {
        if (!$date = $this->prepare($date, false, true)) {
            return null;
        }

        return $this->wrap(
            $date->format('Y-m-d'),
            $date->format($format)
        );
    }

    /**
     * Format date according to locale
     */
    public function locale($date, $dateSize = true, $timeSize = true, $timezone = true): ?Element
    {
        $dateSize = $this->normalizeLocaleSize($dateSize);
        $timeSize = $this->normalizeLocaleSize($timeSize);

        $hasDate = $dateSize !== IntlDateFormatter::NONE;
        $hasTime = ($timeSize !== IntlDateFormatter::NONE) && ($timezone !== false);

        if (!$hasDate && !$hasTime) {
            return null;
        }

        if ($hasDate && $hasTime) {
            $format = DateTime::W3C;
        } elseif ($hasDate) {
            $format = 'Y-m-d';
        } elseif ($hasTime) {
            $format = 'H:i:s';
        } else {
            $format = '';
        }

        if (!$date = $this->prepare($date, $timezone, $hasTime)) {
            return null;
        }

        $formatter = new IntlDateFormatter(
            $this->getLocale(),
            $dateSize,
            $timeSize
        );

        $formatter->setTimezone($date->getTimezone());

        return $this->wrap(
            $date->format($format),
            $formatter->format($date)
        );
    }

    /**
     * Format full date time
     */
    public function fullDateTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'full', 'full', $timezone);
    }

    /**
     * Format full date
     */
    public function fullDate($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'full', false, $timezone);
    }

    /**
     * Format full time
     */
    public function fullTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, false, 'full', $timezone);
    }


    /**
     * Format long date time
     */
    public function longDateTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'long', 'long', $timezone);
    }

    /**
     * Format long date
     */
    public function longDate($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'long', false, $timezone);
    }

    /**
     * Format long time
     */
    public function longTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, false, 'long', $timezone);
    }


    /**
     * Format medium date time
     */
    public function mediumDateTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'medium', 'medium', $timezone);
    }

    /**
     * Format medium date
     */
    public function mediumDate($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'medium', false, $timezone);
    }

    /**
     * Format medium time
     */
    public function mediumTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, false, 'medium', $timezone);
    }


    /**
     * Format short date time
     */
    public function shortDateTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'short', 'short', $timezone);
    }

    /**
     * Format short date
     */
    public function shortDate($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'short', false, $timezone);
    }

    /**
     * Format short time
     */
    public function shortTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, false, 'short', $timezone);
    }




    /**
     * Format default date time
     */
    public function dateTime($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'medium', 'medium', $timezone);
    }

    /**
     * Format default date
     */
    public function date($date, $timezone = true): ?Element
    {
        return $this->locale($date, 'medium', false, $timezone);
    }

    /**
     * Format default time
     */
    public function time($date, $timezone = true): ?Element
    {
        return $this->locale($date, false, 'short', $timezone);
    }




    /**
     * Format interval since date
     */
    public function since($date, ?bool $positive = null, ?int $parts = 1): ?Element
    {
        return $this->wrapInterval($date, false, $parts, false, false, $positive);
    }

    /**
     * Format interval since date
     */
    public function sinceAbs($date, ?bool $positive = null, ?int $parts = 1): ?Element
    {
        return $this->wrapInterval($date, false, $parts, false, true, $positive);
    }

    /**
     * Format interval since date
     */
    public function sinceAbbr($date, ?bool $positive = null, ?int $parts = 1): ?Element
    {
        return $this->wrapInterval($date, false, $parts, true, true, $positive);
    }

    /**
     * Format interval until date
     */
    public function until($date, ?bool $positive = null, ?int $parts = 1): ?Element
    {
        return $this->wrapInterval($date, true, $parts, false, false, $positive);
    }

    /**
     * Format interval until date
     */
    public function untilAbs($date, ?bool $positive = null, ?int $parts = 1): ?Element
    {
        return $this->wrapInterval($date, true, $parts, false, true, $positive);
    }

    /**
     * Format interval until date
     */
    public function untilAbbr($date, ?bool $positive = null, ?int $parts = 1): ?Element
    {
        return $this->wrapInterval($date, true, $parts, true, true, $positive);
    }


    /**
     * Format interval
     */
    protected function wrapInterval($date, bool $invert, ?int $parts, bool $short = false, bool $absolute = false, ?bool $positive = false): ?Element
    {
        $this->checkCarbon();

        if (!$date = $this->normalizeDate($date)) {
            return null;
        }

        if (null === ($now = $this->normalizeDate('now'))) {
            throw Exceptional::UnexpectedValue('Unable to create now date');
        }

        if (null === ($interval = CarbonInterval::make($date->diff($now)))) {
            throw Exceptional::UnexpectedValue('Unable to create interval');
        }

        $formatter = new IntlDateFormatter(
            $this->getLocale(),
            IntlDateFormatter::LONG,
            IntlDateFormatter::LONG
        );

        if (null === ($interval = CarbonInterval::make($interval))) {
            throw Exceptional::UnexpectedValue('Unable to create interval');
        }

        $inverted = $interval->invert;

        if ($invert) {
            if ($inverted) {
                $absolute = true;
            }

            $inverted = !$inverted;
        }

        $output = $this->wrap(
            $date->format(DateTime::W3C),
            ($inverted && $absolute ? '-' : '') .
            $interval->forHumans([
                'short' => $short,
                'join' => true,
                'parts' => $parts,
                'options' => CarbonInterface::JUST_NOW | CarbonInterface::ONE_DAY_WORDS,
                'syntax' => $absolute ? CarbonInterface::DIFF_ABSOLUTE : CarbonInterface::DIFF_RELATIVE_TO_NOW
            ]),
            $formatter->format($date)
        );

        if ($interval->invert) {
            $output->addClass('future');
        } else {
            $output->addClass('past');
        }

        if ($positive !== null) {
            $positiveClass = $positive ? 'positive' : 'negative';
            $negativeClass = $positive ? 'negative' : 'positive';

            if ($interval->invert) {
                $output->addClass($invert ? $positiveClass : $negativeClass . ' pending');
            } else {
                $output->addClass($invert ? $negativeClass : $positiveClass);
            }
        }

        return $output;
    }




    /**
     * Format interval until date
     */
    public function between($date1, $date2, ?int $parts = 1): ?Element
    {
        return $this->betweenRaw($date1, $date2, $parts, false);
    }

    /**
     * Format interval until date
     */
    public function betweenAbbr($date1, $date2, ?int $parts = 1): ?Element
    {
        return $this->betweenRaw($date1, $date2, $parts, true);
    }

    /**
     * Format interval until date
     */
    protected function betweenRaw($date1, $date2, ?int $parts = 1, bool $short = false): ?Element
    {
        $this->checkCarbon();

        if (!$date1 = $this->normalizeDate($date1)) {
            return null;
        }

        if (!$date2 = $this->normalizeDate($date2)) {
            return null;
        }

        if (null === ($interval = CarbonInterval::make($date1->diff($date2)))) {
            throw Exceptional::UnexpectedValue('Unable to create interval');
        }

        $output = $this->html->el(
            'span.interval',
            ($interval->invert ? '-' : '') .
            $interval->forHumans([
                'short' => $short,
                'join' => true,
                'parts' => $parts,
                'options' => CarbonInterface::JUST_NOW | CarbonInterface::ONE_DAY_WORDS,
                'syntax' => CarbonInterface::DIFF_ABSOLUTE
            ])
        );

        if ($interval->invert) {
            $output->addClass('negative');
        } else {
            $output->addClass('positive');
        }

        return $output;
    }






    /**
     * Prepare date for formatting
     */
    protected function prepare($date, $timezone = true, bool $includeTime = true): ?DateTime
    {
        if (null === ($date = $this->normalizeDate($date))) {
            return null;
        }

        if ($timezone === false) {
            $timezone = null;
            //$includeTime = false;
        }

        if ($timezone !== null) {
            $date = clone $date;

            if ($timezone = $this->normalizeTimezone($timezone)) {
                $date->setTimezone($timezone);
            }
        }

        return $date;
    }

    /**
     * Normalize a date input
     */
    protected function normalizeDate($date): ?DateTime
    {
        if ($date === null) {
            return null;
        } elseif ($date instanceof DateTime) {
            return $date;
        }

        if ($date instanceof DateInterval) {
            $int = $date;

            if (null === ($now = $this->normalizeDate('now'))) {
                throw Exceptional::UnexpectedValue('Unable to create now date');
            }

            return $now->add($int);
        }

        $timestamp = null;

        if (is_numeric($date)) {
            $timestamp = $date;
            $date = 'now';
        }

        $date = new DateTime((string)$date);

        if ($timestamp !== null) {
            $date->setTimestamp((int)$timestamp);
        }

        return $date;
    }

    /**
     * Normalize timezone
     */
    protected function normalizeTimezone($timezone): ?DateTimeZone
    {
        if ($timezone === false || $timezone === null) {
            return null;
        }

        if ($timezone === true) {
            $timezone = $this->getTimezone();
        }

        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        return new DateTimeZone((string)$timezone);
    }


    /**
     * Wrap date / time in Markup
     */
    protected function wrap(string $w3c, string $formatted, string $title = null): Element
    {
        $output = $this->html->el('time', $formatted, [
            'datetime' => $w3c
        ]);

        if ($title !== null) {
            $output->setTitle($title);
        }

        return $output;
    }

    /**
     * Normalize locale format size
     */
    protected function normalizeLocaleSize($size): int
    {
        if ($size === false || $size === null) {
            return IntlDateFormatter::NONE;
        }

        if ($size === true) {
            return IntlDateFormatter::LONG;
        }

        switch ($size) {
            case 'full':
                return IntlDateFormatter::FULL;

            case 'long':
                return IntlDateFormatter::LONG;

            case 'medium':
                return IntlDateFormatter::MEDIUM;

            case 'short':
                return IntlDateFormatter::SHORT;

            case IntlDateFormatter::FULL:
            case IntlDateFormatter::LONG:
            case IntlDateFormatter::MEDIUM:
            case IntlDateFormatter::SHORT:
                return $size;

            default:
                throw Exceptional::InvalidArgument(
                    'Invalid locale formatter size: ' . $size
                );
        }
    }


    /**
     * Check Carbon installed
     */
    protected function checkCarbon(): void
    {
        if (!class_exists(Carbon::class)) {
            throw Exceptional::ComponentUnavailable(
                'nesbot/carbon is required for formatting intervals'
            );
        }
    }
}

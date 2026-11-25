<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Counts time inside a company's working hours, or around the clock when there are none.
 * Weekday windows and holidays are read in the company's timezone.
 */
class BusinessCalendar
{
    /** Sunday–Thursday, 08:00–17:00 until a company sets its own. */
    public const DEFAULT_HOURS = [
        '0' => ['08:00', '17:00'], '1' => ['08:00', '17:00'], '2' => ['08:00', '17:00'],
        '3' => ['08:00', '17:00'], '4' => ['08:00', '17:00'], '5' => null, '6' => null,
    ];

    /** Longest stretch searched for open time, so a calendar with no open days can't loop forever. */
    private const MAX_DAYS = 3660;

    /**
     * @param  array<string, array{0: string, 1: string}|null>|null  $hours  null = 24/7
     * @param  array<string, true>  $holidays  'Y-m-d' dates, plus 'm-d' for yearly ones
     */
    public function __construct(
        private readonly ?array $hours = null,
        private readonly array $holidays = [],
        private readonly string $timezone = 'UTC',
    ) {}

    public static function always(): self
    {
        return new self;
    }

    /** The calendar a ticket's SLA counts in. */
    public static function forTicket(Ticket $ticket): self
    {
        return $ticket->sla?->business_hours_only ? self::forTenant($ticket->tenant_id) : self::always();
    }

    public static function forTenant(int $tenantId): self
    {
        $settings = Tenant::find($tenantId)?->settings;
        $holidays = Holiday::where('tenant_id', $tenantId)->get(['date', 'is_recurring'])
            ->mapWithKeys(fn ($h) => [$h->date->format($h->is_recurring ? 'm-d' : 'Y-m-d') => true])
            ->all();

        return new self($settings?->business_hours ?? self::DEFAULT_HOURS, $holidays, $settings?->timezone ?: 'UTC');
    }

    /** Working minutes between two moments (0 if $to is not after $from). */
    public function minutesBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);

        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }
        if ($this->hours === null) {
            return (int) $from->diffInMinutes($to);
        }

        $seconds = 0;
        foreach ($this->windows($from, $to) as [$start, $end]) {
            $seconds += max(0, $end->getTimestamp() - $start->getTimestamp());
        }

        return intdiv($seconds, 60);
    }

    /** The moment $minutes of working time after $from. */
    public function addMinutes(DateTimeInterface $from, int $minutes): CarbonImmutable
    {
        $from = CarbonImmutable::instance($from);

        if ($this->hours === null) {
            return $from->addMinutes($minutes);
        }

        $left = $minutes * 60;
        foreach ($this->windows($from, null) as [$start, $end]) {
            $available = $end->getTimestamp() - $start->getTimestamp();
            if ($left <= $available) {
                return $start->addSeconds($left)->setTimezone($from->getTimezone());
            }
            $left -= $available;
        }

        // No working time within the search range: fall back to plain time rather than never.
        return $from->addMinutes($minutes);
    }

    public function isOpen(DateTimeInterface $at): bool
    {
        if ($this->hours === null) {
            return true;
        }
        $at = CarbonImmutable::instance($at);

        foreach ($this->windows($at, $at->addSecond()) as $window) {
            return true;
        }

        return false;
    }

    /**
     * Open windows overlapping [$from, $to), clipped to that range; $to = null means "keep going".
     *
     * @return \Generator<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function windows(CarbonImmutable $from, ?CarbonImmutable $to): \Generator
    {
        $day = $from->setTimezone($this->timezone)->startOfDay();

        for ($i = 0; $i < self::MAX_DAYS; $i++, $day = $day->addDay()) {
            if ($to && $day->greaterThanOrEqualTo($to)) {
                return;
            }

            $window = $this->hours[(string) $day->dayOfWeek] ?? null;
            if (! $window || isset($this->holidays[$day->format('Y-m-d')]) || isset($this->holidays[$day->format('m-d')])) {
                continue;
            }

            [$open, $close] = [$day->setTimeFromTimeString($window[0]), $day->setTimeFromTimeString($window[1])];
            $start = $open->max($from);
            $end = $to ? $close->min($to) : $close;

            if ($end->greaterThan($start)) {
                yield [$start, $end];
            }
        }
    }
}

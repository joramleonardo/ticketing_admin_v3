<?php

namespace App\Services;

use App\Enums\TicketStatus;
use Carbon\Carbon;

class TicketSlaService
{
    /**
     * Standard SLA resolution threshold in hours.
     */
    public const SLA_TARGET_HOURS = 24;

    /**
     * SLA warning threshold in hours.
     */
    public const SLA_WARNING_HOURS = 48;

    /**
     * Calculate SLA and Turnaround Time (TAT) metrics for a ticket.
     *
     * @return array{
     *     type: string,
     *     state: string,
     *     label: string,
     *     elapsed_hours: float,
     *     formatted_duration: string,
     *     is_breached: boolean,
     *     is_warning: boolean
     * }
     */
    public static function calculate(
        string|\DateTimeInterface|null $dateRequested,
        ?string $status = null,
        string|\DateTimeInterface|null $dateCompleted = null
    ): array {
        if (! $dateRequested) {
            return [
                'type' => 'unknown',
                'state' => 'unknown',
                'label' => 'No Date',
                'elapsed_hours' => 0.0,
                'formatted_duration' => '—',
                'is_breached' => false,
                'is_warning' => false,
            ];
        }

        $start = Carbon::parse($dateRequested);
        $normStatus = trim((string) $status);
        $isResolved = in_array($normStatus, [TicketStatus::Completed->value, TicketStatus::Closed->value], true);

        if ($isResolved) {
            $end = $dateCompleted ? Carbon::parse($dateCompleted) : Carbon::now();
            if ($end->lessThan($start)) {
                $end = $start->copy();
            }

            $diffMinutes = $start->diffInMinutes($end);
            $diffHours = round($diffMinutes / 60, 1);
            $formatted = self::formatDuration($diffMinutes);
            $breached = $diffMinutes > (self::SLA_TARGET_HOURS * 60);

            return [
                'type' => 'tat',
                'state' => $breached ? 'resolved_after_sla' : 'resolved_within_sla',
                'label' => $breached ? "TAT: {$formatted} (Overdue)" : "TAT: {$formatted}",
                'elapsed_hours' => $diffHours,
                'formatted_duration' => $formatted,
                'is_breached' => $breached,
                'is_warning' => false,
            ];
        }

        // Active ticket (New, Pending, In Progress)
        $now = Carbon::now();
        $diffMinutes = $start->diffInMinutes($now);
        $diffHours = round($diffMinutes / 60, 1);
        $formatted = self::formatDuration($diffMinutes);

        if ($diffMinutes > (self::SLA_WARNING_HOURS * 60)) {
            return [
                'type' => 'sla',
                'state' => 'breached',
                'label' => "Overdue ({$formatted})",
                'elapsed_hours' => $diffHours,
                'formatted_duration' => $formatted,
                'is_breached' => true,
                'is_warning' => false,
            ];
        }

        if ($diffMinutes > (self::SLA_TARGET_HOURS * 60)) {
            return [
                'type' => 'sla',
                'state' => 'warning',
                'label' => "Due Soon ({$formatted})",
                'elapsed_hours' => $diffHours,
                'formatted_duration' => $formatted,
                'is_breached' => false,
                'is_warning' => true,
            ];
        }

        return [
            'type' => 'sla',
            'state' => 'on_track',
            'label' => "On Track ({$formatted})",
            'elapsed_hours' => $diffHours,
            'formatted_duration' => $formatted,
            'is_breached' => false,
            'is_warning' => false,
        ];
    }

    /**
     * Format minutes into human-readable duration (e.g. 45m, 3h 15m, 2d 4h).
     */
    public static function formatDuration(int|float $totalMinutes): string
    {
        $minutes = (int) round($totalMinutes);
        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0 ? "{$days}d {$remainingHours}h" : "{$days}d";
    }
}

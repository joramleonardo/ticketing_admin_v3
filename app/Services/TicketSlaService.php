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
     * Calculate Response Time (Assigned -> Attended) and Resolution TAT / SLA (Attended -> Completed) metrics.
     *
     * @return array{
     *     type: string,
     *     state: string,
     *     label: string,
     *     elapsed_hours: float,
     *     formatted_duration: string,
     *     response_hours: float|null,
     *     response_duration: string|null,
     *     is_breached: boolean,
     *     is_warning: boolean
     * }
     */
    public static function calculate(
        string|\DateTimeInterface|null $dateRequested = null,
        ?string $status = null,
        string|\DateTimeInterface|null $dateCompleted = null,
        string|\DateTimeInterface|null $dateAssigned = null,
        string|\DateTimeInterface|null $dateAttended = null,
    ): array {
        $normStatus = trim((string) $status);

        // 1. Unassigned / New ticket: SLA & TAT clock has not started yet
        if (strcasecmp($normStatus, TicketStatus::New->value) === 0 && ! $dateAssigned && ! $dateAttended) {
            return [
                'type' => 'unassigned',
                'state' => 'awaiting_assignment',
                'label' => 'Awaiting Assignment',
                'elapsed_hours' => 0.0,
                'formatted_duration' => '—',
                'response_hours' => null,
                'response_duration' => null,
                'is_breached' => false,
                'is_warning' => false,
            ];
        }

        // Determine Response Time (Assigned -> Attended) when available
        $assignedCarbon = $dateAssigned ? Carbon::parse($dateAssigned) : ($dateRequested ? Carbon::parse($dateRequested) : null);
        $attendedCarbon = $dateAttended ? Carbon::parse($dateAttended) : null;

        $responseHours = null;
        $responseDuration = null;
        if ($assignedCarbon && $attendedCarbon) {
            $respEnd = $attendedCarbon->lessThan($assignedCarbon) ? $assignedCarbon->copy() : $attendedCarbon;
            $respMins = $assignedCarbon->diffInMinutes($respEnd);
            $responseHours = round($respMins / 60, 1);
            $responseDuration = self::formatDuration($respMins);
        }

        // 2. Resolved ticket (Completed or Closed): TAT = Attended -> Completed
        $isResolved = in_array($normStatus, [TicketStatus::Completed->value, TicketStatus::Closed->value], true);

        if ($isResolved) {
            $tatStartRaw = $dateAttended ?? $dateAssigned ?? $dateRequested;
            if (! $tatStartRaw) {
                return [
                    'type' => 'unknown',
                    'state' => 'unknown',
                    'label' => 'No Date',
                    'elapsed_hours' => 0.0,
                    'formatted_duration' => '—',
                    'response_hours' => $responseHours,
                    'response_duration' => $responseDuration,
                    'is_breached' => false,
                    'is_warning' => false,
                ];
            }

            $start = Carbon::parse($tatStartRaw);
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
                'response_hours' => $responseHours,
                'response_duration' => $responseDuration,
                'is_breached' => $breached,
                'is_warning' => false,
            ];
        }

        // 3. Pending ticket (Assigned to IT Staff, waiting to be Attended): Response Time Clock
        if (strcasecmp($normStatus, TicketStatus::Pending->value) === 0 && ! $attendedCarbon) {
            if (! $assignedCarbon) {
                return [
                    'type' => 'unknown',
                    'state' => 'unknown',
                    'label' => 'No Date',
                    'elapsed_hours' => 0.0,
                    'formatted_duration' => '—',
                    'response_hours' => null,
                    'response_duration' => null,
                    'is_breached' => false,
                    'is_warning' => false,
                ];
            }

            $now = Carbon::now();
            $diffMinutes = $assignedCarbon->diffInMinutes($now);
            $diffHours = round($diffMinutes / 60, 1);
            $formatted = self::formatDuration($diffMinutes);

            if ($diffMinutes > (self::SLA_WARNING_HOURS * 60)) {
                return [
                    'type' => 'response',
                    'state' => 'breached',
                    'label' => "Unattended ({$formatted})",
                    'elapsed_hours' => $diffHours,
                    'formatted_duration' => $formatted,
                    'response_hours' => $diffHours,
                    'response_duration' => $formatted,
                    'is_breached' => true,
                    'is_warning' => false,
                ];
            }

            if ($diffMinutes > (self::SLA_TARGET_HOURS * 60)) {
                return [
                    'type' => 'response',
                    'state' => 'warning',
                    'label' => "Due Soon ({$formatted})",
                    'elapsed_hours' => $diffHours,
                    'formatted_duration' => $formatted,
                    'response_hours' => $diffHours,
                    'response_duration' => $formatted,
                    'is_breached' => false,
                    'is_warning' => true,
                ];
            }

            return [
                'type' => 'response',
                'state' => 'awaiting_attendance',
                'label' => "Awaiting Attend ({$formatted})",
                'elapsed_hours' => $diffHours,
                'formatted_duration' => $formatted,
                'response_hours' => $diffHours,
                'response_duration' => $formatted,
                'is_breached' => false,
                'is_warning' => false,
            ];
        }

        // 4. Active In-Progress ticket (Attended -> Now): Resolution SLA Clock
        $activeStartRaw = $dateAttended ?? $dateAssigned ?? $dateRequested;
        if (! $activeStartRaw) {
            return [
                'type' => 'unknown',
                'state' => 'unknown',
                'label' => 'No Date',
                'elapsed_hours' => 0.0,
                'formatted_duration' => '—',
                'response_hours' => $responseHours,
                'response_duration' => $responseDuration,
                'is_breached' => false,
                'is_warning' => false,
            ];
        }

        $start = Carbon::parse($activeStartRaw);
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
                'response_hours' => $responseHours,
                'response_duration' => $responseDuration,
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
                'response_hours' => $responseHours,
                'response_duration' => $responseDuration,
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
            'response_hours' => $responseHours,
            'response_duration' => $responseDuration,
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

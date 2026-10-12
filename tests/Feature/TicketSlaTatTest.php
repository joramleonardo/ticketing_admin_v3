<?php

use App\Services\TicketSlaService;
use Carbon\Carbon;

it('calculates active ticket SLA as on track when under 24 hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-07 08:00:00'); // 4 hours ago
    $result = TicketSlaService::calculate($dateRequested, 'In Progress');

    expect($result['type'])->toBe('sla')
        ->and($result['state'])->toBe('on_track')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['is_warning'])->toBeFalse()
        ->and($result['label'])->toContain('On Track');
});

it('calculates active ticket SLA as warning when between 24 and 48 hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-06 06:00:00'); // 30 hours ago
    $result = TicketSlaService::calculate($dateRequested, 'Pending');

    expect($result['type'])->toBe('sla')
        ->and($result['state'])->toBe('warning')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['is_warning'])->toBeTrue()
        ->and($result['label'])->toContain('Due Soon');
});

it('calculates active ticket SLA as breached when over 48 hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-04 12:00:00'); // 72 hours ago
    $result = TicketSlaService::calculate($dateRequested, 'New');

    expect($result['type'])->toBe('sla')
        ->and($result['state'])->toBe('breached')
        ->and($result['is_breached'])->toBeTrue()
        ->and($result['label'])->toContain('Overdue');
});

it('calculates turnaround time (TAT) correctly for completed tickets within SLA', function (): void {
    $dateRequested = '2026-10-01 08:00:00';
    $dateCompleted = '2026-10-01 10:30:00'; // 2h 30m

    $result = TicketSlaService::calculate($dateRequested, 'Completed', $dateCompleted);

    expect($result['type'])->toBe('tat')
        ->and($result['state'])->toBe('resolved_within_sla')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['formatted_duration'])->toBe('2h 30m')
        ->and($result['label'])->toBe('TAT: 2h 30m');
});

it('calculates turnaround time (TAT) correctly for completed tickets exceeding SLA', function (): void {
    $dateRequested = '2026-10-01 08:00:00';
    $dateCompleted = '2026-10-03 10:00:00'; // ~50 hours

    $result = TicketSlaService::calculate($dateRequested, 'Closed', $dateCompleted);

    expect($result['type'])->toBe('tat')
        ->and($result['state'])->toBe('resolved_after_sla')
        ->and($result['is_breached'])->toBeTrue()
        ->and($result['label'])->toContain('(Overdue)');
});

<?php

use App\Services\TicketSlaService;
use Carbon\Carbon;

it('does not count unassigned New tickets against technician SLA', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-04 12:00:00'); // 72 hours ago, but still unassigned
    $result = TicketSlaService::calculate($dateRequested, 'New');

    expect($result['type'])->toBe('unassigned')
        ->and($result['state'])->toBe('awaiting_assignment')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['is_warning'])->toBeFalse()
        ->and($result['label'])->toBe('Awaiting Assignment');
});

it('tracks response wait time from date_assigned when ticket is Pending', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-05 08:00:00'); // requested 52h ago
    $dateAssigned = Carbon::parse('2026-10-07 10:00:00');  // assigned 2h ago
    $result = TicketSlaService::calculate($dateRequested, 'Pending', null, $dateAssigned);

    expect($result['type'])->toBe('response')
        ->and($result['state'])->toBe('awaiting_attendance')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['elapsed_hours'])->toBe(2.0)
        ->and($result['label'])->toContain('Awaiting Attend (2h)');
});

it('calculates active ticket SLA as on track from date_attended when under 24 hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-05 08:00:00'); // 52 hours ago
    $dateAssigned = Carbon::parse('2026-10-07 07:00:00');  // 5 hours ago
    $dateAttended = Carbon::parse('2026-10-07 08:00:00');  // 4 hours ago (1h response time)
    $result = TicketSlaService::calculate($dateRequested, 'In Progress', null, $dateAssigned, $dateAttended);

    expect($result['type'])->toBe('sla')
        ->and($result['state'])->toBe('on_track')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['is_warning'])->toBeFalse()
        ->and($result['elapsed_hours'])->toBe(4.0)
        ->and($result['response_hours'])->toBe(1.0)
        ->and($result['response_duration'])->toBe('1h')
        ->and($result['label'])->toContain('On Track');
});

it('calculates active ticket SLA as warning when between 24 and 48 hours from assignment/attendance', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-05 06:00:00');
    $dateAssigned = Carbon::parse('2026-10-06 06:00:00'); // 30 hours ago
    $result = TicketSlaService::calculate($dateRequested, 'Pending', null, $dateAssigned);

    expect($result['type'])->toBe('response')
        ->and($result['state'])->toBe('warning')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['is_warning'])->toBeTrue()
        ->and($result['label'])->toContain('Due Soon');
});

it('calculates active ticket SLA as breached when over 48 hours from attendance', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

    $dateRequested = Carbon::parse('2026-10-04 08:00:00');
    $dateAssigned = Carbon::parse('2026-10-04 10:00:00');
    $dateAttended = Carbon::parse('2026-10-04 12:00:00'); // 72 hours ago
    $result = TicketSlaService::calculate($dateRequested, 'In Progress', null, $dateAssigned, $dateAttended);

    expect($result['type'])->toBe('sla')
        ->and($result['state'])->toBe('breached')
        ->and($result['is_breached'])->toBeTrue()
        ->and($result['label'])->toContain('Overdue');
});

it('calculates turnaround time (TAT) from date_attended to date_completed and response time from date_assigned to date_attended', function (): void {
    $dateRequested = '2026-10-01 08:00:00';
    $dateAssigned = '2026-10-02 09:00:00';  // Assigned next day
    $dateAttended = '2026-10-02 09:30:00';  // Attended 30m after assignment
    $dateCompleted = '2026-10-02 12:00:00'; // Completed 2h 30m after attendance

    $result = TicketSlaService::calculate($dateRequested, 'Completed', $dateCompleted, $dateAssigned, $dateAttended);

    expect($result['type'])->toBe('tat')
        ->and($result['state'])->toBe('resolved_within_sla')
        ->and($result['is_breached'])->toBeFalse()
        ->and($result['formatted_duration'])->toBe('2h 30m')
        ->and($result['response_duration'])->toBe('30m')
        ->and($result['label'])->toBe('TAT: 2h 30m');
});

it('calculates turnaround time (TAT) correctly for completed tickets exceeding SLA from attendance', function (): void {
    $dateRequested = '2026-10-01 08:00:00';
    $dateAssigned = '2026-10-01 08:15:00';
    $dateAttended = '2026-10-01 09:00:00';
    $dateCompleted = '2026-10-03 11:00:00'; // 50 hours after attendance

    $result = TicketSlaService::calculate($dateRequested, 'Closed', $dateCompleted, $dateAssigned, $dateAttended);

    expect($result['type'])->toBe('tat')
        ->and($result['state'])->toBe('resolved_after_sla')
        ->and($result['is_breached'])->toBeTrue()
        ->and($result['response_duration'])->toBe('45m')
        ->and($result['label'])->toContain('(Overdue)');
});

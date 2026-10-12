<?php

use App\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('can complete an active ticket with Ticket Cancelled remarks', function (): void {
    $user = User::factory()->create(['name' => 'Agent Smith']);

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => 'REF-CAN-001',
        'ticket_type' => 'Technical Support',
        'ticket_status' => TicketStatus::InProgress->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->put(route('tickets.complete', $ticketId), [
        'ticket_status' => TicketStatus::Completed->value,
        'ticket_remarks' => 'Ticket Cancelled',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $ticket = DB::table('v2_ticket_details')->where('ticket_id', $ticketId)->first();
    expect($ticket->ticket_status)->toBe(TicketStatus::Completed->value)
        ->and($ticket->ticket_remarks)->toBe('Ticket Cancelled');

    $log = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', 'REF-CAN-001')
        ->where('activity_name', 'Ticket Completed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->activity_user)->toBe('Agent Smith');
});

it('can formally close a completed ticket', function (): void {
    $user = User::factory()->create(['name' => 'Support Manager']);

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => 'REF-CLS-001',
        'ticket_type' => 'Technical Support',
        'ticket_status' => TicketStatus::Completed->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->put(route('tickets.close', $ticketId), [
        'ticket_remarks' => 'Formally archived after sign-off.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $ticket = DB::table('v2_ticket_details')->where('ticket_id', $ticketId)->first();
    expect($ticket->ticket_status)->toBe(TicketStatus::Closed->value)
        ->and($ticket->ticket_remarks)->toContain('Closed: Formally archived after sign-off.');

    $log = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', 'REF-CLS-001')
        ->where('activity_name', 'Ticket Closed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->activity_user)->toBe('Support Manager');
});

it('cannot close a ticket that is not completed', function (): void {
    $user = User::factory()->create();

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => 'REF-CLS-002',
        'ticket_type' => 'Technical Support',
        'ticket_status' => TicketStatus::InProgress->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->putJson(route('tickets.close', $ticketId), [
        'ticket_remarks' => 'Trying to close early',
    ]);

    $response->assertStatus(422);

    $ticket = DB::table('v2_ticket_details')->where('ticket_id', $ticketId)->first();
    expect($ticket->ticket_status)->toBe(TicketStatus::InProgress->value);
});

it('automatically closes a completed ticket when client submits rating', function (): void {
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => 'REF-RATE-001',
        'ticket_type' => 'Technical Support',
        'ticket_status' => TicketStatus::Completed->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson('/client/ticket/REF-RATE-001/rating', [
        'rating_result' => 'Excellent',
        'rating_comment_suggestion' => 'Great work!',
    ]);

    $response->assertSuccessful();

    $ticket = DB::table('v2_ticket_details')->where('client_referenceNo', 'REF-RATE-001')->first();
    expect($ticket->ticket_status)->toBe(TicketStatus::Closed->value);

    $closeLog = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', 'REF-RATE-001')
        ->where('activity_name', 'Ticket Closed')
        ->first();

    expect($closeLog)->not->toBeNull()
        ->and($closeLog->activity_user)->toBe('System (Client Rated)');
});

it('can update ticket parameters including problem description and record activity log', function (): void {
    $user = User::factory()->create(['name' => 'Tech Specialist']);

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => 'REF-EDIT-001',
        'ticket_type' => 'Technical Support',
        'ticket_status' => TicketStatus::New->value,
        'ticket_problem_that_needed_support' => 'Initial problem description',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->put(route('tickets.update', $ticketId), [
        'ticket_type' => 'Livestream',
        'ticket_assigned_staff' => 'John Doe',
        'ticket_problem_that_needed_support' => 'Updated and detailed problem narrative description.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $ticket = DB::table('v2_ticket_details')->where('ticket_id', $ticketId)->first();
    expect($ticket->ticket_type)->toBe('Livestream')
        ->and($ticket->ticket_assigned_staff)->toBe('John Doe')
        ->and($ticket->ticket_problem_that_needed_support)->toBe('Updated and detailed problem narrative description.');

    $editLog = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', 'REF-EDIT-001')
        ->where('activity_name', 'Staff Assigned')
        ->first();

    expect($editLog)->not->toBeNull()
        ->and($editLog->activity_user)->toBe('Tech Specialist');
});


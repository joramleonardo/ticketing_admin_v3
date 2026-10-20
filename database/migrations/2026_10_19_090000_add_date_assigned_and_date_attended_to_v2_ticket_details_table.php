<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('v2_ticket_details')) {
            Schema::table('v2_ticket_details', function (Blueprint $table): void {
                if (! Schema::hasColumn('v2_ticket_details', 'date_assigned')) {
                    $table->dateTime('date_assigned')->nullable()->index()->after('date_requested');
                }
                if (! Schema::hasColumn('v2_ticket_details', 'date_attended')) {
                    $table->dateTime('date_attended')->nullable()->index()->after('date_assigned');
                }
            });

            // Backfill existing tickets using activity logs where available
            if (Schema::hasTable('v2_ticket_activity_logs')) {
                $assignedLogs = DB::table('v2_ticket_activity_logs')
                    ->select('client_referenceNo', DB::raw('MIN(activity_date) as first_assigned_at'))
                    ->where(function ($q): void {
                        $q->whereIn('activity_name', ['Ticket Assigned', 'Staff Assigned', 'Staff Reassigned'])
                            ->orWhere('activity_name', 'like', 'Assigned to %');
                    })
                    ->groupBy('client_referenceNo')
                    ->pluck('first_assigned_at', 'client_referenceNo');

                $attendedLogs = DB::table('v2_ticket_activity_logs')
                    ->select('client_referenceNo', DB::raw('MIN(activity_date) as first_attended_at'))
                    ->where('activity_name', 'Ticket Attended')
                    ->groupBy('client_referenceNo')
                    ->pluck('first_attended_at', 'client_referenceNo');

                $tickets = DB::table('v2_ticket_details')
                    ->select([
                        'ticket_id',
                        'client_referenceNo',
                        'ticket_status',
                        'ticket_assigned_staff',
                        'ticket_attended_by',
                        'date_requested',
                        'date_assigned',
                        'date_attended',
                        'date_completed',
                        'created_at',
                    ])
                    ->get();

                foreach ($tickets as $ticket) {
                    $ref = $ticket->client_referenceNo;
                    $status = trim((string) $ticket->ticket_status);
                    $hasAssignedStaff = ! empty(trim((string) $ticket->ticket_assigned_staff));
                    $hasAttendedBy = ! empty(trim((string) $ticket->ticket_attended_by));

                    $assignedAt = $ticket->date_assigned;
                    if (! $assignedAt && ($hasAssignedStaff || in_array($status, ['Pending', 'In Progress', 'Completed', 'Closed'], true))) {
                        $assignedAt = $assignedLogs[$ref] ?? $ticket->date_requested ?? $ticket->created_at;
                    }

                    $attendedAt = $ticket->date_attended;
                    if (! $attendedAt && ($hasAttendedBy || in_array($status, ['In Progress', 'Completed', 'Closed'], true))) {
                        $attendedAt = $attendedLogs[$ref] ?? $assignedAt ?? $ticket->date_requested ?? $ticket->created_at;
                    }

                    if ($assignedAt !== $ticket->date_assigned || $attendedAt !== $ticket->date_attended) {
                        DB::table('v2_ticket_details')
                            ->where('ticket_id', $ticket->ticket_id)
                            ->update([
                                'date_assigned' => $assignedAt,
                                'date_attended' => $attendedAt,
                            ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('v2_ticket_details')) {
            Schema::table('v2_ticket_details', function (Blueprint $table): void {
                $columnsToDrop = [];
                if (Schema::hasColumn('v2_ticket_details', 'date_assigned')) {
                    $columnsToDrop[] = 'date_assigned';
                }
                if (Schema::hasColumn('v2_ticket_details', 'date_attended')) {
                    $columnsToDrop[] = 'date_attended';
                }
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};

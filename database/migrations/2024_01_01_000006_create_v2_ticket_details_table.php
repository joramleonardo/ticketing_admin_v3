<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('v2_ticket_details')) {
            Schema::create('v2_ticket_details', function (Blueprint $table): void {
                $table->id('ticket_id');
                $table->string('client_referenceNo', 255)->unique();
                $table->enum('ticket_type', ['Events', 'Livestream', 'Technical Support'])->default('Technical Support');
                $table->string('ticket_type_support', 191)->nullable();
                $table->enum('ticket_nature_of_support', [
                    'Events', 'Hardware', 'Software', 'Hardware & Software', 'System Modification', 'Livestream', 'Others',
                ])->nullable();
                $table->string('ticket_support_type', 255)->nullable();
                $table->enum('ticket_related_type', [
                    'Installation related', 'Livestream related', 'Network related', 'PC Setup & Troubleshooting',
                    'Printer related', 'STARBOOKS related', 'Website and System related', 'Events related', 'Others',
                ])->nullable();
                $table->text('ticket_assigned_by')->nullable();
                $table->text('ticket_assigned_staff')->nullable();
                $table->text('ticket_attended_by')->nullable();
                $table->text('ticket_assisted_by')->nullable();
                $table->enum('ticket_status', ['New', 'Pending', 'In Progress', 'Completed', 'Closed'])->default('New')->index();
                $table->text('ticket_problem_that_needed_support')->nullable();
                $table->text('ticket_actions_taken')->nullable();
                $table->text('ticket_remarks')->nullable();
                $table->dateTime('date_requested')->nullable()->index();
                $table->dateTime('date_completed')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_ticket_details');
    }
};

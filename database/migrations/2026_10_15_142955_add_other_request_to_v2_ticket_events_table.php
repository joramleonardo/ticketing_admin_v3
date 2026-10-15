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
        Schema::table('v2_ticket_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('v2_ticket_events', 'other_request')) {
                $table->text('other_request')->nullable()->after('event_remarks');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_ticket_events', function (Blueprint $table): void {
            if (Schema::hasColumn('v2_ticket_events', 'other_request')) {
                $table->dropColumn('other_request');
            }
        });
    }
};

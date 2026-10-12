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
        if (! Schema::hasTable('v2_ticket_events')) {
            Schema::create('v2_ticket_events', function (Blueprint $table): void {
                $table->id('event_id');
                $table->string('client_referenceNo', 100)->index();
                $table->date('event_start_date')->nullable();
                $table->date('event_end_date')->nullable();
                $table->string('event_venue', 255)->nullable();
                $table->text('event_remarks')->nullable();
                $table->timestamps();

                $table->index(['event_start_date', 'event_end_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_ticket_events');
    }
};

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
        if (! Schema::hasTable('v2_ticket_activity_logs')) {
            Schema::create('v2_ticket_activity_logs', function (Blueprint $table): void {
                $table->id('activity_id');
                $table->string('client_referenceNo', 255)->index();
                $table->string('activity_name', 255)->nullable();
                $table->timestamp('activity_date')->useCurrent();
                $table->string('activity_user', 255)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_ticket_activity_logs');
    }
};

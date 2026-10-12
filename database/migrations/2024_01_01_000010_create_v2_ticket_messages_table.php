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
        if (! Schema::hasTable('v2_ticket_messages')) {
            Schema::create('v2_ticket_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('client_referenceNo', 255)->index();
                $table->enum('sender_type', ['client', 'staff'])->index();
                $table->string('sender_name', 255)->nullable();
                $table->text('message');
                $table->timestamps();

                $table->index(['client_referenceNo', 'id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_ticket_messages');
    }
};

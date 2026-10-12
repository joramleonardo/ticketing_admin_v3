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
        if (! Schema::hasTable('v2_ticket_client_details')) {
            Schema::create('v2_ticket_client_details', function (Blueprint $table): void {
                $table->id('client_id');
                $table->enum('client_type', ['Internal', 'External'])->nullable();
                $table->string('client_name', 255)->nullable();
                $table->string('client_division_agency', 255)->nullable();
                $table->string('client_empID', 255)->nullable();
                $table->string('client_email', 191)->nullable()->index();
                $table->string('client_sex', 50)->nullable();
                $table->string('client_referenceNo', 255)->unique();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_ticket_client_details');
    }
};

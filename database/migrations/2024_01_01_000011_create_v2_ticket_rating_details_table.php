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
        if (! Schema::hasTable('v2_ticket_rating_details')) {
            Schema::create('v2_ticket_rating_details', function (Blueprint $table): void {
                $table->id('rating_id');
                $table->string('client_referenceNo', 255)->unique();
                $table->enum('rating_status', ['Pending', 'Received'])->default('Pending')->index();
                $table->enum('rating_result', ['Excellent', 'Very Good', 'Good', 'Poor', 'Very Poor'])->nullable()->index();
                $table->text('rating_comment_suggestion')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_ticket_rating_details');
    }
};

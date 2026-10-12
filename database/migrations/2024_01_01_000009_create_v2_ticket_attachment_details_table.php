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
        if (! Schema::hasTable('v2_ticket_attachment_details')) {
            Schema::create('v2_ticket_attachment_details', function (Blueprint $table): void {
                $table->id();
                $table->string('client_referenceNo', 255)->index();
                $table->string('original_name', 255);
                $table->string('stored_path', 255);
                $table->string('mime_type', 191)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->boolean('is_image')->default(false);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_ticket_attachment_details');
    }
};

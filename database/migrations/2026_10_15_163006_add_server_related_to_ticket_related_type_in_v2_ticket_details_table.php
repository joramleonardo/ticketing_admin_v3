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
        Schema::table('v2_ticket_details', function (Blueprint $table): void {
            $table->enum('ticket_related_type', [
                'Installation related',
                'Livestream related',
                'Network related',
                'PC Setup & Troubleshooting',
                'Printer related',
                'Server Related',
                'STARBOOKS related',
                'Website and System related',
                'Events related',
                'Others',
            ])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_ticket_details', function (Blueprint $table): void {
            $table->enum('ticket_related_type', [
                'Installation related',
                'Livestream related',
                'Network related',
                'PC Setup & Troubleshooting',
                'Printer related',
                'STARBOOKS related',
                'Website and System related',
                'Events related',
                'Others',
            ])->nullable()->change();
        });
    }
};

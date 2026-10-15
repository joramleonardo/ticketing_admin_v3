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
        if (Schema::hasTable('v2_ticket_client_details')) {
            Schema::table('v2_ticket_client_details', function (Blueprint $table) {
                if (! Schema::hasColumn('v2_ticket_client_details', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('v2_ticket_details')) {
            Schema::table('v2_ticket_details', function (Blueprint $table) {
                if (! Schema::hasColumn('v2_ticket_details', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('v2_ticket_details')) {
            Schema::table('v2_ticket_details', function (Blueprint $table) {
                if (Schema::hasColumn('v2_ticket_details', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('v2_ticket_client_details')) {
            Schema::table('v2_ticket_client_details', function (Blueprint $table) {
                if (Schema::hasColumn('v2_ticket_client_details', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};

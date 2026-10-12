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
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 191);
                $table->string('username', 191)->nullable()->unique();
                $table->string('email', 191)->nullable()->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password', 191);
                $table->string('role', 191)->default('Staff');
                $table->string('isActivated', 191)->default('1');
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

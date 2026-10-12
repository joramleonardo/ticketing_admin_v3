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
        if (! Schema::hasTable('ticket_employees')) {
            Schema::create('ticket_employees', function (Blueprint $table): void {
                $table->id();
                $table->string('fname', 255)->nullable();
                $table->string('lname', 255)->nullable();
                $table->string('mname', 255)->nullable();
                $table->string('exname', 255)->nullable();
                $table->string('employee_code', 255)->nullable()->unique();
                $table->string('division_id', 10)->nullable();
                $table->integer('section_id')->nullable();
                $table->enum('sex', ['Male', 'Female'])->nullable();
                $table->string('position', 255)->default('');
                $table->string('email', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_employees');
    }
};

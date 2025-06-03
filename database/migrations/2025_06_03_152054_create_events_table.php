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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('calendar_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->boolean('is_all_day')->default(false);
            $table->string('timezone');
            $table->text('recurrence_rule')->nullable();
            $table->string('location')->nullable();
            $table->uuid('creator_user_id');
            $table->enum('status', ['confirmed', 'canceled', 'tentative', 'pending_approval'])->default('confirmed');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('calendar_id');
            $table->index('creator_user_id');
            $table->index('start_datetime');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

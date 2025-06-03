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
        Schema::create('event_participants', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->enum('status', ['pending', 'accepted', 'declined', 'tentative'])->default('pending');
            $table->uuid('assigned_calendar_id')->nullable();
            $table->enum('role', ['organizer', 'attendee'])->default('attendee');
            $table->timestamps();
            
            // Composite primary key
            $table->primary(['event_id', 'user_id']);
            
            // Foreign key constraints
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('assigned_calendar_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'assigned_calendar_id',
        'role',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Indicates if the model should use UUIDs for the primary key.
     * Since we're using a composite primary key, we disable auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key for the model.
     *
     * @var array
     */
    protected $primaryKey = ['event_id', 'user_id'];

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get the event that owns the participant.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Scope a query to only include accepted participants.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope a query to only include pending participants.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include organizers.
     */
    public function scopeOrganizers($query)
    {
        return $query->where('role', 'organizer');
    }

    /**
     * Scope a query to only include attendees.
     */
    public function scopeAttendees($query)
    {
        return $query->where('role', 'attendee');
    }

    /**
     * Check if the participant has accepted the invitation.
     */
    public function hasAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if the participant has declined the invitation.
     */
    public function hasDeclined(): bool
    {
        return $this->status === 'declined';
    }

    /**
     * Check if the invitation is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the participant is an organizer.
     */
    public function isOrganizer(): bool
    {
        return $this->role === 'organizer';
    }
}

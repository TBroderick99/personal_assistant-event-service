<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'calendar_id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'is_all_day',
        'timezone',
        'recurrence_rule',
        'location',
        'creator_user_id',
        'status',
        'color',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'is_all_day' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the participants for the event.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /**
     * Get user IDs of participants for this event.
     * Use this to fetch user details from User microservice.
     */
    public function getParticipantUserIds(): array
    {
        return $this->participants()->pluck('user_id')->toArray();
    }

    /**
     * Get accepted participants only.
     */
    public function acceptedParticipants(): HasMany
    {
        return $this->participants()->where('status', 'accepted');
    }

    /**
     * Get pending participants only.
     */
    public function pendingParticipants(): HasMany
    {
        return $this->participants()->where('status', 'pending');
    }

    /**
     * Scope a query to only include events for a specific calendar.
     */
    public function scopeForCalendar($query, $calendarId)
    {
        return $query->where('calendar_id', $calendarId);
    }

    /**
     * Scope a query to only include events for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('creator_user_id', $userId)
                    ->orWhereHas('participants', function ($q) use ($userId) {
                        $q->where('user_id', $userId)
                          ->where('status', 'accepted');
                    });
    }

    /**
     * Scope a query to only include events within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_datetime', [$startDate, $endDate])
              ->orWhereBetween('end_datetime', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_datetime', '<=', $startDate)
                     ->where('end_datetime', '>=', $endDate);
              });
        });
    }
}

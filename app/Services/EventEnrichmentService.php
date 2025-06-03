<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Collection;

class EventEnrichmentService
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Enrich a single event with participant user details.
     *
     * @param Event $event
     * @return array
     */
    public function enrichEventWithUserDetails(Event $event): array
    {
        $eventData = $event->toArray();
        
        // Load participants
        $participants = $event->participants;
        
        // Get all user IDs from participants plus creator
        $userIds = $participants->pluck('user_id')->toArray();
        if (!in_array($event->creator_user_id, $userIds)) {
            $userIds[] = $event->creator_user_id;
        }
        
        // Fetch user details from User microservice
        $users = $this->userService->getUsersByIds($userIds);
        
        // Enrich participants with user details
        $enrichedParticipants = $participants->map(function ($participant) use ($users) {
            $participantData = $participant->toArray();
            $participantData['user'] = $users[$participant->user_id] ?? [
                'id' => $participant->user_id,
                'name' => 'Unknown User',
                'email' => null
            ];
            return $participantData;
        });
        
        // Add creator user details
        $eventData['creator_user'] = $users[$event->creator_user_id] ?? [
            'id' => $event->creator_user_id,
            'name' => 'Unknown User',
            'email' => null
        ];
        
        $eventData['participants'] = $enrichedParticipants;
        
        return $eventData;
    }

    /**
     * Enrich a collection of events with participant user details.
     *
     * @param Collection $events
     * @return array
     */
    public function enrichEventsWithUserDetails(Collection $events): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        // Load all participants for all events
        $events->load('participants');
        
        // Collect all unique user IDs from participants and creators
        $allUserIds = collect();
        
        foreach ($events as $event) {
            $allUserIds = $allUserIds->merge($event->participants->pluck('user_id'));
            $allUserIds->push($event->creator_user_id);
        }
        
        $uniqueUserIds = $allUserIds->unique()->values()->toArray();
        
        // Fetch all user details in one request
        $users = $this->userService->getUsersByIds($uniqueUserIds);
        
        // Enrich each event
        return $events->map(function ($event) use ($users) {
            $eventData = $event->toArray();
            
            // Enrich participants with user details
            $enrichedParticipants = $event->participants->map(function ($participant) use ($users) {
                $participantData = $participant->toArray();
                $participantData['user'] = $users[$participant->user_id] ?? [
                    'id' => $participant->user_id,
                    'name' => 'Unknown User',
                    'email' => null
                ];
                return $participantData;
            });
            
            // Add creator user details
            $eventData['creator_user'] = $users[$event->creator_user_id] ?? [
                'id' => $event->creator_user_id,
                'name' => 'Unknown User',
                'email' => null
            ];
            
            $eventData['participants'] = $enrichedParticipants;
            
            return $eventData;
        })->toArray();
    }

    /**
     * Get events for a specific user with enriched details.
     * This includes events created by the user and events where they are participants.
     *
     * @param string $userId
     * @param array $filters Optional filters (calendar_id, start_date, end_date)
     * @return array
     */
    public function getEventsForUser(string $userId, array $filters = []): array
    {
        $query = Event::forUser($userId);
        
        // Apply filters
        if (isset($filters['calendar_id'])) {
            $query->forCalendar($filters['calendar_id']);
        }
        
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->inDateRange($filters['start_date'], $filters['end_date']);
        }
        
        $events = $query->get();
        
        return $this->enrichEventsWithUserDetails($events);
    }

    /**
     * Get events for a specific calendar with enriched details.
     *
     * @param string $calendarId
     * @param array $filters Optional filters (start_date, end_date)
     * @return array
     */
    public function getEventsForCalendar(string $calendarId, array $filters = []): array
    {
        $query = Event::forCalendar($calendarId);
        
        // Apply filters
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->inDateRange($filters['start_date'], $filters['end_date']);
        }
        
        $events = $query->get();
        
        return $this->enrichEventsWithUserDetails($events);
    }
}

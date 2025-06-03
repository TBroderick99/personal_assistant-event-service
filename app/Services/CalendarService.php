<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CalendarService
{
    private string $calendarServiceBaseUrl;

    public function __construct()
    {
        $this->calendarServiceBaseUrl = config('services.calendar_service.base_url', 'http://calendar-service');
    }

    /**
     * Validate if a calendar exists and is accessible.
     *
     * @param string $calendarId Calendar UUID
     * @param string|null $userId User ID for access validation
     * @return bool True if calendar exists and is accessible, false otherwise
     */
    public function validateCalendar(string $calendarId, ?string $userId = null): bool
    {
        try {
            $params = [];
            if ($userId) {
                $params['user_id'] = $userId;
            }

            $response = Http::timeout(10)
                ->get("{$this->calendarServiceBaseUrl}/api/calendars/{$calendarId}/validate", $params);

            return $response->successful() && $response->json('data.valid', false);

        } catch (Exception $e) {
            Log::error('Error validating calendar with Calendar service', [
                'error' => $e->getMessage(),
                'calendar_id' => $calendarId,
                'user_id' => $userId
            ]);

            // In case of service failure, we can either:
            // 1. Fail closed (return false) - more secure
            // 2. Fail open (return true) - more resilient
            // For now, we'll fail open to allow development to continue
            return true;
        }
    }

    /**
     * Get calendar details by ID.
     *
     * @param string $calendarId Calendar UUID
     * @return array|null Calendar data or null if not found
     */
    public function getCalendarById(string $calendarId): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->calendarServiceBaseUrl}/api/calendars/{$calendarId}");

            if ($response->successful()) {
                return $response->json('data');
            }

            if ($response->status() === 404) {
                return null;
            }

            Log::warning('Failed to fetch calendar from Calendar service', [
                'status' => $response->status(),
                'response' => $response->body(),
                'calendar_id' => $calendarId
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Error communicating with Calendar service', [
                'error' => $e->getMessage(),
                'calendar_id' => $calendarId
            ]);

            return null;
        }
    }

    /**
     * Get calendars accessible by a user.
     *
     * @param string $userId User UUID
     * @return array Array of calendars
     */
    public function getUserCalendars(string $userId): array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->calendarServiceBaseUrl}/api/users/{$userId}/calendars");

            if ($response->successful()) {
                return $response->json('data', []);
            }

            Log::warning('Failed to fetch user calendars from Calendar service', [
                'status' => $response->status(),
                'response' => $response->body(),
                'user_id' => $userId
            ]);

            return [];

        } catch (Exception $e) {
            Log::error('Error fetching user calendars from Calendar service', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return [];
        }
    }

    /**
     * Check if a user has access to a specific calendar.
     *
     * @param string $userId User UUID
     * @param string $calendarId Calendar UUID
     * @return bool True if user has access, false otherwise
     */
    public function userHasAccessToCalendar(string $userId, string $calendarId): bool
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->calendarServiceBaseUrl}/api/calendars/{$calendarId}/access", [
                    'user_id' => $userId
                ]);

            return $response->successful() && $response->json('data.has_access', false);

        } catch (Exception $e) {
            Log::error('Error checking calendar access with Calendar service', [
                'error' => $e->getMessage(),
                'calendar_id' => $calendarId,
                'user_id' => $userId
            ]);

            // Fail open for development
            return true;
        }
    }
}

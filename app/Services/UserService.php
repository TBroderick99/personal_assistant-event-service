<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class UserService
{
    private string $userServiceBaseUrl;

    public function __construct()
    {
        $this->userServiceBaseUrl = config('services.user_service.base_url', 'http://user-service');
    }

    /**
     * Fetch user details for multiple user IDs from the User microservice.
     *
     * @param array $userIds Array of user UUIDs
     * @return array Array of user data indexed by user_id
     */
    public function getUsersByIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->userServiceBaseUrl}/api/users", [
                    'ids' => implode(',', $userIds)
                ]);

            if ($response->successful()) {
                $users = $response->json('data', []);
                
                // Index users by their ID for easy lookup
                $indexedUsers = [];
                foreach ($users as $user) {
                    $indexedUsers[$user['id']] = $user;
                }
                
                return $indexedUsers;
            }

            Log::warning('Failed to fetch users from User service', [
                'status' => $response->status(),
                'response' => $response->body(),
                'user_ids' => $userIds
            ]);

            return [];

        } catch (Exception $e) {
            Log::error('Error communicating with User service', [
                'error' => $e->getMessage(),
                'user_ids' => $userIds
            ]);

            return [];
        }
    }

    /**
     * Fetch a single user by ID from the User microservice.
     *
     * @param string $userId User UUID
     * @return array|null User data or null if not found
     */
    public function getUserById(string $userId): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->userServiceBaseUrl}/api/users/{$userId}");

            if ($response->successful()) {
                return $response->json('data');
            }

            if ($response->status() === 404) {
                return null;
            }

            Log::warning('Failed to fetch user from User service', [
                'status' => $response->status(),
                'response' => $response->body(),
                'user_id' => $userId
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Error communicating with User service', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return null;
        }
    }

    /**
     * Validate if a user exists in the User microservice.
     *
     * @param string $userId User UUID
     * @return bool True if user exists, false otherwise
     */
    public function userExists(string $userId): bool
    {
        return $this->getUserById($userId) !== null;
    }
}

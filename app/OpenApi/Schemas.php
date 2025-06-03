<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class Schemas {
    #[OA\Schema(
        schema: "ValidationError",
        title: "Validation Error",
        description: "Standard validation error response",
        properties: [
             new OA\Property(property: "message", type: "string", example: "The given data was invalid."),
             new OA\Property(property: "errors", type: "object", example: '{"email": ["The email has already been taken."], "password": ["The password confirmation does not match."]}')
        ],
        type: "object"
    )]
    public function validationError(): void {}

    #[OA\Schema(
        schema: "Event",
        title: "Event",
        description: "Event model",
        properties: [
            new OA\Property(property: "id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440000"),
            new OA\Property(property: "calendar_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440001"),
            new OA\Property(property: "title", type: "string", example: "Team Meeting"),
            new OA\Property(property: "description", type: "string", nullable: true, example: "Weekly team sync meeting"),
            new OA\Property(property: "start_datetime", type: "string", format: "date-time", example: "2025-06-10T10:00:00Z"),
            new OA\Property(property: "end_datetime", type: "string", format: "date-time", example: "2025-06-10T11:00:00Z"),
            new OA\Property(property: "is_all_day", type: "boolean", example: false),
            new OA\Property(property: "timezone", type: "string", example: "America/Argentina/Buenos_Aires"),
            new OA\Property(property: "recurrence_rule", type: "string", nullable: true, example: "FREQ=WEEKLY;BYDAY=MO"),
            new OA\Property(property: "location", type: "string", nullable: true, example: "Conference Room A"),
            new OA\Property(property: "creator_user_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440002"),
            new OA\Property(property: "status", type: "string", enum: ["confirmed", "canceled", "tentative", "pending_approval"], example: "confirmed"),
            new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2025-06-03T15:20:00Z"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2025-06-03T15:20:00Z"),
            new OA\Property(
                property: "participants",
                type: "array",
                items: new OA\Items(ref: "#/components/schemas/EventParticipant")
            )
        ],
        type: "object"
    )]
    public function event(): void {}

    #[OA\Schema(
        schema: "EventParticipant",
        title: "Event Participant",
        description: "Event participant model",
        properties: [
            new OA\Property(property: "event_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440000"),
            new OA\Property(property: "user_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440003"),
            new OA\Property(property: "status", type: "string", enum: ["pending", "accepted", "declined", "tentative"], example: "accepted"),
            new OA\Property(property: "assigned_calendar_id", type: "string", format: "uuid", nullable: true, example: "550e8400-e29b-41d4-a716-446655440004"),
            new OA\Property(property: "role", type: "string", enum: ["organizer", "attendee"], example: "attendee"),
            new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2025-06-03T15:20:00Z"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2025-06-03T15:20:00Z")
        ],
        type: "object"
    )]
    public function eventParticipant(): void {}

    #[OA\Schema(
        schema: "CreateEventRequest",
        title: "Create Event Request",
        description: "Request payload for creating an event",
        required: ["calendar_id", "title", "start_datetime", "end_datetime", "timezone", "creator_user_id"],
        properties: [
            new OA\Property(property: "calendar_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440001"),
            new OA\Property(property: "title", type: "string", example: "Team Meeting"),
            new OA\Property(property: "description", type: "string", nullable: true, example: "Weekly team sync meeting"),
            new OA\Property(property: "start_datetime", type: "string", format: "date-time", example: "2025-06-10T10:00:00Z"),
            new OA\Property(property: "end_datetime", type: "string", format: "date-time", example: "2025-06-10T11:00:00Z"),
            new OA\Property(property: "is_all_day", type: "boolean", example: false),
            new OA\Property(property: "timezone", type: "string", example: "America/Argentina/Buenos_Aires"),
            new OA\Property(property: "recurrence_rule", type: "string", nullable: true, example: "FREQ=WEEKLY;BYDAY=MO"),
            new OA\Property(property: "location", type: "string", nullable: true, example: "Conference Room A"),
            new OA\Property(property: "creator_user_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440002"),
            new OA\Property(property: "status", type: "string", enum: ["confirmed", "canceled", "tentative", "pending_approval"], example: "confirmed")
        ],
        type: "object"
    )]
    public function createEventRequest(): void {}

    #[OA\Schema(
        schema: "UpdateEventRequest",
        title: "Update Event Request",
        description: "Request payload for updating an event",
        properties: [
            new OA\Property(property: "title", type: "string", example: "Updated Team Meeting"),
            new OA\Property(property: "description", type: "string", nullable: true, example: "Updated weekly team sync meeting"),
            new OA\Property(property: "start_datetime", type: "string", format: "date-time", example: "2025-06-10T10:30:00Z"),
            new OA\Property(property: "end_datetime", type: "string", format: "date-time", example: "2025-06-10T11:30:00Z"),
            new OA\Property(property: "is_all_day", type: "boolean", example: false),
            new OA\Property(property: "timezone", type: "string", example: "America/Argentina/Buenos_Aires"),
            new OA\Property(property: "recurrence_rule", type: "string", nullable: true, example: "FREQ=WEEKLY;BYDAY=TU"),
            new OA\Property(property: "location", type: "string", nullable: true, example: "Conference Room B"),
            new OA\Property(property: "status", type: "string", enum: ["confirmed", "canceled", "tentative", "pending_approval"], example: "confirmed")
        ],
        type: "object"
    )]
    public function updateEventRequest(): void {}

    #[OA\Schema(
        schema: "InviteParticipantRequest",
        title: "Invite Participant Request",
        description: "Request payload for inviting a participant to an event",
        required: ["user_id"],
        properties: [
            new OA\Property(property: "user_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440003"),
            new OA\Property(property: "role", type: "string", enum: ["organizer", "attendee"], example: "attendee")
        ],
        type: "object"
    )]
    public function inviteParticipantRequest(): void {}

    #[OA\Schema(
        schema: "UpdateParticipantRequest",
        title: "Update Participant Request",
        description: "Request payload for updating participant status",
        properties: [
            new OA\Property(property: "status", type: "string", enum: ["pending", "accepted", "declined", "tentative"], example: "accepted"),
            new OA\Property(property: "assigned_calendar_id", type: "string", format: "uuid", nullable: true, example: "550e8400-e29b-41d4-a716-446655440004")
        ],
        type: "object"
    )]
    public function updateParticipantRequest(): void {}
}
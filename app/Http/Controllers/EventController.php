<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class EventController extends Controller
{
    #[OA\Get(
        path: "/api/events",
        summary: "List events",
        description: "Retrieve a list of events. Can be filtered by calendar_id, user_id, or date range.",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(
                name: "calendar_id",
                description: "Filter events by calendar ID",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "user_id",
                description: "Filter events by user ID (creator or participant)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "start_date",
                description: "Filter events starting from this date",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", format: "date-time")
            ),
            new OA\Parameter(
                name: "end_date",
                description: "Filter events ending before this date",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", format: "date-time")
            ),
            new OA\Parameter(
                name: "with_participants",
                description: "Include participant details",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "boolean", default: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of events",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Event")
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();

        // Filter by calendar_id
        if ($request->has('calendar_id')) {
            $query->forCalendar($request->calendar_id);
        }

        // Filter by user_id
        if ($request->has('user_id')) {
            $query->forUser($request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->start_date, $request->end_date);
        }

        // Include participants if requested
        if ($request->boolean('with_participants')) {
            $query->with('participants');
        }

        $events = $query->orderBy('start_datetime')->get();

        return response()->json($events);
    }

    #[OA\Get(
        path: "/api/events/{id}",
        summary: "Get a specific event",
        description: "Retrieve details of a specific event by ID",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Event ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "with_participants",
                description: "Include participant details",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "boolean", default: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Event details",
                content: new OA\JsonContent(ref: "#/components/schemas/Event")
            ),
            new OA\Response(
                response: 404,
                description: "Event not found"
            )
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $query = Event::where('id', $id);

        if ($request->boolean('with_participants')) {
            $query->with('participants');
        }

        $event = $query->firstOrFail();

        return response()->json($event);
    }

    #[OA\Post(
        path: "/api/events",
        summary: "Create a new event",
        description: "Create a new event in the system",
        tags: ["Events"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(ref: "#/components/schemas/CreateEventRequest")
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Event created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Event")
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calendar_id' => 'required|uuid',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'is_all_day' => 'boolean',
            'timezone' => 'required|string|max:100',
            'recurrence_rule' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'creator_user_id' => 'required|uuid',
            'status' => 'in:confirmed,canceled,tentative,pending_approval'
        ]);

        $event = Event::create($validated);

        // Automatically add the creator as an organizer
        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => $event->creator_user_id,
            'status' => 'accepted',
            'role' => 'organizer'
        ]);

        return response()->json($event->load('participants'), 201);
    }

    #[OA\Put(
        path: "/api/events/{id}",
        summary: "Update an event",
        description: "Update an existing event",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Event ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(ref: "#/components/schemas/UpdateEventRequest")
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Event updated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Event")
            ),
            new OA\Response(
                response: 404,
                description: "Event not found"
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")
            )
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'sometimes|date',
            'end_datetime' => 'sometimes|date|after:start_datetime',
            'is_all_day' => 'boolean',
            'timezone' => 'sometimes|string|max:100',
            'recurrence_rule' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'status' => 'in:confirmed,canceled,tentative,pending_approval'
        ]);

        $event->update($validated);

        return response()->json($event->load('participants'));
    }

    #[OA\Delete(
        path: "/api/events/{id}",
        summary: "Delete an event",
        description: "Delete an event and all its participants",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Event ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Event deleted successfully"
            ),
            new OA\Response(
                response: 404,
                description: "Event not found"
            )
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/events/{id}/participants",
        summary: "Invite a participant to an event",
        description: "Add a new participant to an event",
        tags: ["Event Participants"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Event ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(ref: "#/components/schemas/InviteParticipantRequest")
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Participant invited successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/EventParticipant")
            ),
            new OA\Response(
                response: 404,
                description: "Event not found"
            ),
            new OA\Response(
                response: 409,
                description: "Participant already exists"
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")
            )
        ]
    )]
    public function inviteParticipant(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|uuid',
            'role' => 'in:organizer,attendee'
        ]);

        // Check if participant already exists
        $existingParticipant = EventParticipant::where('event_id', $id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existingParticipant) {
            return response()->json(['message' => 'Participant already exists for this event'], 409);
        }

        $participant = EventParticipant::create([
            'event_id' => $id,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'] ?? 'attendee',
            'status' => 'pending'
        ]);

        return response()->json($participant, 201);
    }

    #[OA\Put(
        path: "/api/events/{eventId}/participants/{userId}",
        summary: "Update participant status",
        description: "Update a participant's status and assigned calendar",
        tags: ["Event Participants"],
        parameters: [
            new OA\Parameter(
                name: "eventId",
                description: "Event ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "userId",
                description: "User ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(ref: "#/components/schemas/UpdateParticipantRequest")
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Participant updated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/EventParticipant")
            ),
            new OA\Response(
                response: 404,
                description: "Participant not found"
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")
            )
        ]
    )]
    public function updateParticipant(Request $request, string $eventId, string $userId): JsonResponse
    {
        $participant = EventParticipant::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,accepted,declined,tentative',
            'assigned_calendar_id' => 'nullable|uuid'
        ]);

        $participant->update($validated);

        return response()->json($participant);
    }

    #[OA\Delete(
        path: "/api/events/{eventId}/participants/{userId}",
        summary: "Remove a participant from an event",
        description: "Remove a participant from an event",
        tags: ["Event Participants"],
        parameters: [
            new OA\Parameter(
                name: "eventId",
                description: "Event ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "userId",
                description: "User ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Participant removed successfully"
            ),
            new OA\Response(
                response: 404,
                description: "Participant not found"
            )
        ]
    )]
    public function removeParticipant(string $eventId, string $userId): JsonResponse
    {
        $participant = EventParticipant::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $participant->delete();

        return response()->json(null, 204);
    }
}

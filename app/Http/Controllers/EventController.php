<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiSuccessResponse;
use App\Http\Responses\ApiErrorResponse;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Rules\ValidCalendar;
use App\Services\EventEnrichmentService;
use App\Services\UserService;
use App\Services\CalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use OpenApi\Attributes as OA;
use Exception;

class EventController extends Controller
{
    public function __construct(
        private EventEnrichmentService $eventEnrichmentService,
        private UserService $userService,
        private CalendarService $calendarService
    ) {
    }
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
    public function index(Request $request)
    {
        try {
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
                $request->validate([
                    'start_date' => 'date',
                    'end_date' => 'date|after_or_equal:start_date'
                ]);
                $query->inDateRange($request->start_date, $request->end_date);
            }

            $events = $query->orderBy('start_datetime')->get();

            // Check if we should enrich with user details
            if ($request->boolean('with_participants')) {
                $enrichedEvents = $this->eventEnrichmentService->enrichEventsWithUserDetails($events);
                
                return new ApiSuccessResponse(
                    statusCode: Response::HTTP_OK,
                    result: ['events' => $enrichedEvents],
                    metaData: ['total' => count($enrichedEvents), 'enriched' => true]
                );
            }

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_OK,
                result: ['events' => $events],
                metaData: ['total' => $events->count(), 'enriched' => false]
            );

        } catch (ValidationException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                errorType: 'ERR_VALIDATION',
                message: 'Validation failed for event listing',
                errors: $e->errors()
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_EVENT_LISTING',
                message: 'Failed to retrieve events',
                errors: ['database' => ['Unable to fetch events. Please try again.']],
                exception: $e
            );
        }
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
    public function show(Request $request, string $id)
    {
        try {
            $event = Event::findOrFail($id);

            // Check if we should enrich with user details
            if ($request->boolean('with_participants')) {
                $enrichedEvent = $this->eventEnrichmentService->enrichEventWithUserDetails($event);
                
                return new ApiSuccessResponse(
                    statusCode: Response::HTTP_OK,
                    result: ['event' => $enrichedEvent],
                    metaData: ['enriched' => true]
                );
            }

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_OK,
                result: ['event' => $event],
                metaData: ['enriched' => false]
            );

        } catch (ModelNotFoundException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                errorType: 'ERR_EVENT_NOT_FOUND',
                message: 'Event not found',
                errors: ['event' => ['The requested event does not exist.']]
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_EVENT_RETRIEVAL',
                message: 'Failed to retrieve event',
                errors: ['database' => ['Unable to fetch event details. Please try again.']],
                exception: $e
            );
        }
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
    public function store(Request $request)
    {
        try {
            // Get current user ID (this would typically come from authentication middleware)
            $currentUserId = $request->header('X-User-ID') ?? $request->input('creator_user_id');
            
            if (!$currentUserId) {
                return new ApiErrorResponse(
                    statusCode: Response::HTTP_UNAUTHORIZED,
                    errorType: 'ERR_AUTHENTICATION',
                    message: 'User authentication required',
                    errors: ['auth' => ['User ID must be provided in X-User-ID header or creator_user_id field.']]
                );
            }

            $validated = $request->validate([
                'calendar_id' => ['required', 'uuid', new ValidCalendar($currentUserId, $this->calendarService)],
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_datetime' => 'required|date',
                'end_datetime' => 'required|date|after:start_datetime',
                'is_all_day' => 'boolean',
                'timezone' => 'required|string|max:100',
                'recurrence_rule' => 'nullable|string',
                'location' => 'nullable|string|max:255',
                'creator_user_id' => 'sometimes|uuid',
                'status' => 'in:confirmed,canceled,tentative,pending_approval'
            ]);

            // Verify the creator user exists
            if (!$this->userService->userExists($currentUserId)) {
                return new ApiErrorResponse(
                    statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errorType: 'ERR_USER_NOT_FOUND',
                    message: 'Creator user does not exist',
                    errors: ['creator_user_id' => ['The specified user does not exist in the system.']]
                );
            }

            // Set creator_user_id to current user
            $validated['creator_user_id'] = $currentUserId;

            $event = Event::create($validated);

            // Automatically add the creator as an organizer
            EventParticipant::create([
                'event_id' => $event->id,
                'user_id' => $event->creator_user_id,
                'status' => 'accepted',
                'role' => 'organizer'
            ]);

            // Return enriched event data
            $enrichedEvent = $this->eventEnrichmentService->enrichEventWithUserDetails($event);

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_CREATED,
                result: ['event' => $enrichedEvent],
                metaData: ['message' => 'Event created successfully']
            );

        } catch (ValidationException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                errorType: 'ERR_VALIDATION',
                message: 'Validation failed for event creation',
                errors: $e->errors()
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_EVENT_CREATION',
                message: 'Event creation failed due to a server error',
                errors: ['database' => ['Failed to create event. Please try again. If problem persists, contact support.']],
                exception: $e
            );
        }
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
    public function update(Request $request, string $id)
    {
        try {
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

            // Return enriched event data
            $enrichedEvent = $this->eventEnrichmentService->enrichEventWithUserDetails($event);

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_OK,
                result: ['event' => $enrichedEvent],
                metaData: ['message' => 'Event updated successfully']
            );

        } catch (ModelNotFoundException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                errorType: 'ERR_EVENT_NOT_FOUND',
                message: 'Event not found',
                errors: ['event' => ['The requested event does not exist.']]
            );
        } catch (ValidationException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                errorType: 'ERR_VALIDATION',
                message: 'Validation failed for event update',
                errors: $e->errors()
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_EVENT_UPDATE',
                message: 'Event update failed due to a server error',
                errors: ['database' => ['Failed to update event. Please try again. If problem persists, contact support.']],
                exception: $e
            );
        }
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
    public function destroy(string $id)
    {
        try {
            $event = Event::findOrFail($id);
            $eventTitle = $event->title; // Store for response message
            
            $event->delete();

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_OK,
                result: ['deleted' => true],
                metaData: ['message' => "Event '{$eventTitle}' has been deleted successfully"]
            );

        } catch (ModelNotFoundException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                errorType: 'ERR_EVENT_NOT_FOUND',
                message: 'Event not found',
                errors: ['event' => ['The requested event does not exist.']]
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_EVENT_DELETION',
                message: 'Event deletion failed due to a server error',
                errors: ['database' => ['Failed to delete event. Please try again. If problem persists, contact support.']],
                exception: $e
            );
        }
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
    public function inviteParticipant(Request $request, string $id)
    {
        try {
            $event = Event::findOrFail($id);

            $validated = $request->validate([
                'user_id' => 'required|uuid',
                'role' => 'in:organizer,attendee'
            ]);

            // Verify the user exists in the User service
            if (!$this->userService->userExists($validated['user_id'])) {
                return new ApiErrorResponse(
                    statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errorType: 'ERR_USER_NOT_FOUND',
                    message: 'User does not exist',
                    errors: ['user_id' => ['The specified user does not exist in the system.']]
                );
            }

            // Check if participant already exists
            $existingParticipant = EventParticipant::where('event_id', $id)
                ->where('user_id', $validated['user_id'])
                ->first();

            if ($existingParticipant) {
                return new ApiErrorResponse(
                    statusCode: Response::HTTP_CONFLICT,
                    errorType: 'ERR_PARTICIPANT_EXISTS',
                    message: 'Participant already exists for this event',
                    errors: ['user_id' => ['This user is already a participant in the event.']]
                );
            }

            $participant = EventParticipant::create([
                'event_id' => $id,
                'user_id' => $validated['user_id'],
                'role' => $validated['role'] ?? 'attendee',
                'status' => 'pending'
            ]);

            // Get user details for the response
            $userData = $this->userService->getUserById($validated['user_id']);
            $participantWithUser = [
                'event_id' => $participant->event_id,
                'user_id' => $participant->user_id,
                'role' => $participant->role,
                'status' => $participant->status,
                'user' => $userData
            ];

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_CREATED,
                result: ['participant' => $participantWithUser],
                metaData: ['message' => 'Participant invited successfully']
            );

        } catch (ModelNotFoundException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                errorType: 'ERR_EVENT_NOT_FOUND',
                message: 'Event not found',
                errors: ['event' => ['The requested event does not exist.']]
            );
        } catch (ValidationException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                errorType: 'ERR_VALIDATION',
                message: 'Validation failed for participant invitation',
                errors: $e->errors()
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_PARTICIPANT_INVITATION',
                message: 'Participant invitation failed due to a server error',
                errors: ['database' => ['Failed to invite participant. Please try again. If problem persists, contact support.']],
                exception: $e
            );
        }
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
    public function updateParticipant(Request $request, string $eventId, string $userId)
    {
        try {
            $participant = EventParticipant::where('event_id', $eventId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $validated = $request->validate([
                'status' => 'sometimes|in:pending,accepted,declined,tentative',
                'assigned_calendar_id' => 'nullable|uuid'
            ]);

            $participant->update($validated);

            // Get user details for the response
            $userData = $this->userService->getUserById($userId);
            $participantWithUser = [
                'event_id' => $participant->event_id,
                'user_id' => $participant->user_id,
                'role' => $participant->role,
                'status' => $participant->status,
                'assigned_calendar_id' => $participant->assigned_calendar_id,
                'user' => $userData
            ];

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_OK,
                result: ['participant' => $participantWithUser],
                metaData: ['message' => 'Participant status updated successfully']
            );

        } catch (ModelNotFoundException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                errorType: 'ERR_PARTICIPANT_NOT_FOUND',
                message: 'Participant not found',
                errors: ['participant' => ['The requested participant does not exist for this event.']]
            );
        } catch (ValidationException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                errorType: 'ERR_VALIDATION',
                message: 'Validation failed for participant update',
                errors: $e->errors()
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_PARTICIPANT_UPDATE',
                message: 'Participant update failed due to a server error',
                errors: ['database' => ['Failed to update participant. Please try again. If problem persists, contact support.']],
                exception: $e
            );
        }
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
    public function removeParticipant(string $eventId, string $userId)
    {
        try {
            $participant = EventParticipant::where('event_id', $eventId)
                ->where('user_id', $userId)
                ->firstOrFail();

            // Get user details for the response message
            $userData = $this->userService->getUserById($userId);
            $userName = $userData['name'] ?? 'Unknown User';

            $participant->delete();

            return new ApiSuccessResponse(
                statusCode: Response::HTTP_OK,
                result: ['removed' => true],
                metaData: ['message' => "Participant '{$userName}' has been removed from the event successfully"]
            );

        } catch (ModelNotFoundException $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                errorType: 'ERR_PARTICIPANT_NOT_FOUND',
                message: 'Participant not found',
                errors: ['participant' => ['The requested participant does not exist for this event.']]
            );
        } catch (Exception $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_PARTICIPANT_REMOVAL',
                message: 'Participant removal failed due to a server error',
                errors: ['database' => ['Failed to remove participant. Please try again. If problem persists, contact support.']],
                exception: $e
            );
        }
    }
}

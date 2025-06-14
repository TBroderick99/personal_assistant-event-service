---
applyTo: '**'
---

This project is a event service that provides a REST API for managing events in a calendar.
The service allows users to create, read, update, and delete events, as well as retrieve a list of all events.
The project is built in laravel and hosted in a docker container.
To make a new model: "docker exec -it event-service php artisan make:model ModelName" with "--migration" added to create a migration file. additional params "--controller"
Swagger UI is used to document the API endpoints.
Users may not CRUD events or calendars of other users, but can be invited to events of other users (events themselves won't hold event participants, it will be in a separate table).
Authentication is done via issuing a JWT token from a user service to the frontend. All requests from frontend will pass through this gateway and authenticated with X-Gateway_API-Key header, the gateway will validate JWT issued from a user service because it has the public key. So if X-Gateway-API-Key is validated correctly (validated in middleware validateApiKey), we can implicitly trust that JWT token in header is also valid.
Services may communicate with each other with X-Internal-API-Key header.
For responses ensure custom responses apiErrorResponse and apiSuccessResponse are used.
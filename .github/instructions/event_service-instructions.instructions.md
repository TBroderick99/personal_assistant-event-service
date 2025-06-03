---
applyTo: '**'
---

This project is a event service that provides a REST API for managing events in a calendar.
The service allows users to create, read, update, and delete events, as well as retrieve a list of all events.
The project is built in laravel and hosted in a docker container.
To make a new model: "docker exec -it event-service php artisan make:model ModelName" with "--migration" added to create a migration file. additional params "--controller"
Swagger UI is used to document the API endpoints.
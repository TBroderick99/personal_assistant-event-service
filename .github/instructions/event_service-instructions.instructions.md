---
applyTo: '**'
---

### Project Overview
* **Project:** Laravel-based Event REST API running in Docker.
* **Core Responsibility:** Manages CRUD operations for events and their participants.
* **Configuration:** All environment-specific settings (database credentials, API keys, service URLs) are managed in the `.env` file.
* **Service Architecture:**
    * **This Service:** Manages events.
    * **Calendar Service:** A separate service that manages the calendars to which events belong.
    * **User Service:** A separate service for user authentication that issues JWTs.
* **API Documentation:** All endpoints are documented and explorable via Swagger UI.

### Key Workflows & Commands
* **Create MVC Set:** `docker exec -it event-service php artisan make:model ModelName -mc`
    * The `-mc` flag creates a **migration** and a **controller**.
    * **Crucially**, always create a `Policy` file for any new model to handle authorization.
* **Run Tests:** `docker exec -it event-service php artisan test`
* **Seed Database:** `docker exec -it event-service php artisan db:seed`

### Authentication & Authorization
* **Primary Auth Flow (User -> Gateway -> Service):**
    1.  The **User Service** issues a **JWT** to the frontend.
    2.  Requests are routed through an API Gateway.
    3.  The Gateway authenticates to this service using the `X-Gateway-API-Key` header, validated by the `validateApiKey` middleware.
    4.  If the gateway's key is valid, the user JWT in the header is implicitly trusted.
* **Inter-Service Communication:** Services use a shared `X-Internal-API-Key` for direct, secure communication.
* **Permissions & Policies:**
    * Users may only CRUD events they own.
    * Event participation and invitations are managed in a separate pivot table (e.g., `event_user`).
    * Use **Form Request** files for handling incoming requests:
        * **Authorization:** Implement pre-controller permission checks in the `authorize()` method.
        * **Validation:** Define request data rules in the `rules()` method.

### Coding Standards
* **API Responses:** All controller methods **must** return responses using the custom helpers: `apiSuccessResponse()` and `apiErrorResponse()`.
* **Reusable Validation:** For complex or reusable validation logic, create a custom `Rule` file.
* **Error Debugging:** Check the application logs at `storage/logs/laravel.log` for detailed error information.
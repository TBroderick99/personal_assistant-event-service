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
}
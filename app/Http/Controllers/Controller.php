<?php

namespace App\Http\Controllers;
use OpenApi\Attributes as OA;

/** @disregard P1011 Undefined constant */
#[
    OA\Info(version: "1.0.0", description: "API endpoints for managing events", title: "Event-Service Documentation"),
    OA\Server(url: L5_SWAGGER_CONST_HOST, description: "local server"),
    OA\Server(url: 'http://staging.example.com', description: "staging server"),
    OA\Server(url: 'http://example.com', description: "production server"),
    OA\SecurityScheme( securityScheme: 'bearerAuth', type: "http", name: "Authorization", in: "header", scheme: "bearer"),
]

abstract class Controller
{
    //
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EventController extends Controller
{
    #[OA\Get(
        path: "/events",
        summary: "List all events",
        tags: ["Events"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful response",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "List of events")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        // Logic to handle the request and return events
        return response()->json(['message' => 'List of events'], 200);
    }
}

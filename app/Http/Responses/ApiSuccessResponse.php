<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiSuccessResponse implements Responsable
{
    public function __construct(
        private int $statusCode = Response::HTTP_OK,
        private mixed $result,
        private array $metaData,
        private array $headers = [],
        private string $successType = 'SUCCESS',
        private int $options = 0
    ) {
    }

    public function toResponse($request):JsonResponse
    {
        return response()->json(
            [
                'successType' => $this->successType,
                'status' => 'success',
                'result' => $this->result,
                'metaData' => $this->metaData                               
            ],
            $this->statusCode,
            $this->headers,
            $this->options
        );
    }
    
}
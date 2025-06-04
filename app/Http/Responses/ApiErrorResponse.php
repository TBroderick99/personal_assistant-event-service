<?php

namespace App\Http\Responses;

use Exception;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Js;
use Throwable;

class ApiErrorResponse implements Responsable
{
    public function __construct(
        private int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        private ?string $errorType = 'ERR_INTERNAL_SERVER_ERROR',
        private string $message,
        private array $errors = [],//INCLUDE ERRORS BELOW AS WELL
        private ?Throwable $exception = null,
        private array $headers = [],
        private int $options = 0
    ) {
    }

    public function toResponse($request):JsonResponse
    {
        $response = ['errorType' => $this->errorType, 'message' => $this->message];
        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        if (!empty($this->exception) && config('app.debug')) {
            $response['debug'] = [
                'message' => $this->exception->getMessage(),
                'file' => $this->exception->getFile(),
                'line' => $this->exception->getLine(),
                'trace' => $this->exception->getTrace()
            ];
        }

        return response()->json(
            $response,
            $this->statusCode,
            $this->headers,
            $this->options
        );
    }
    
}
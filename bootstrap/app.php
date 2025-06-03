<?php

use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register custom middleware aliases
        $middleware->alias([
            'validate.api.key' => \App\Http\Middleware\ValidateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Optional: If you want to report all Throwables (can be noisy)
        // $exceptions->report(function (Throwable $e) {
        //     // Your custom reporting logic here (e.g., Log::error)
        // });

        // --- START of your custom rendering logic ---

        // Handle NotFoundHttpException (404)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return new ApiErrorResponse(
                    exception: $e,
                    message: 'Resource not found.',
                    statusCode: Response::HTTP_NOT_FOUND, // Use Response::HTTP_NOT_FOUND constant
                    errors: ['request' => 'The requested resource was not found on this server.']
                );
            }
        });

        // Handle MethodNotAllowedHttpException (405)
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                $allowedMethods = $e->getHeaders()['Allow'] ?? '';
                $message = 'Method Not Allowed.';
                if ($allowedMethods) {
                    $message .= ' Allowed methods: ' . $allowedMethods;
                }
                return new ApiErrorResponse(
                    exception: $e,
                    message: $message,
                    statusCode: Response::HTTP_METHOD_NOT_ALLOWED, // Use Response::HTTP_METHOD_NOT_ALLOWED
                    errors: $e->getHeaders() // Consider if you want all headers here or just a simpler message
                );
            }
        });

        // Handle ValidationException (422) - Laravel's default is usually good, but if you use ApiErrorResponse:
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return new ApiErrorResponse(
                    exception: $e,
                    message: $e->getMessage(), // Or a generic "Validation Failed."
                    statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: $e->errors()
                );
            }
        });


        // Generic fallback for other Throwables to ensure JSON response for API requests
        $exceptions->render(function (Throwable $e, Request $request) {
            // Place a dd($e) here for debugging what exceptions are hitting this generic handler
            // dd($e);

            if ($request->expectsJson()) {
                $statusCode = match (true) {
                    // ValidationException is handled above, but kept for completeness if you didn't have the specific handler
                    $e instanceof ValidationException => Response::HTTP_UNPROCESSABLE_ENTITY,
                    $e instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
                    $e instanceof AuthorizationException => Response::HTTP_FORBIDDEN,
                    $e instanceof HttpException => $e->getStatusCode(),
                    default => Response::HTTP_INTERNAL_SERVER_ERROR,
                };

                $message = 'An error occurred.';
                $errors = [];

                if ($statusCode == Response::HTTP_INTERNAL_SERVER_ERROR && !config('app.debug')) {
                    $message = 'Server Error.';
                    $errors = ['server' => 'An unexpected internal server error occurred.'];
                } elseif (config('app.debug')) {
                    $message = $e->getMessage(); // More specific message in debug
                    $errors[get_class($e)] = $e->getMessage();
                    // You might add $errors['trace'] = $e->getTraceAsString(); but be careful
                } elseif ($e instanceof HttpException) {
                    $message = $e->getMessage() ?: $message; // Use exception message if available for HTTP exceptions
                } else {
                    // For other non-HttpExceptions in production
                    $message = 'An unexpected error occurred.';
                     $errors[get_class($e)] = 'An unexpected error occurred.';
                }


                return new ApiErrorResponse(
                    exception: $e,
                    message: $message,
                    statusCode: $statusCode,
                    errors: $errors
                );
            }
        });
    })->create();

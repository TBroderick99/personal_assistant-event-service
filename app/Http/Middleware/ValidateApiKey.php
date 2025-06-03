<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     *
     * Validates API keys for microservice authentication:
     * - X-Gateway-API-Key: For requests coming through Kong Gateway
     * - X-Internal-API-Key: For direct service-to-service communication
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $configKey = 'gateway_api_key';
        $receivedApiKey = $request->header('X-Gateway-API-Key');
        $keyType = 'Gateway';
        
        // If no gateway API key, check for internal service API key
        if (!$receivedApiKey) {
            $receivedApiKey = $request->header('X-Internal-API-Key');
            $configKey = 'internal_api_key';
            $keyType = 'Internal';
        }

        // No API key provided
        if (!$receivedApiKey) {
            Log::warning('Unauthorized access attempt: Missing API Key', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
            
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNAUTHORIZED,
                errorType: 'ERR_UNAUTHORIZED',
                message: 'API key is required',
                errors: ['api_key' => 'Missing API key header (X-Gateway-API-Key or X-Internal-API-Key)'],
                exception: new Exception('Missing API key')
            );
        }

        // Validate API key
        $expectedApiKey = config('auth.' . $configKey);
        
        if (!$expectedApiKey) {
            Log::error('API key configuration missing', [
                'config_key' => $configKey,
                'key_type' => $keyType,
            ]);
            
            return new ApiErrorResponse(
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorType: 'ERR_CONFIGURATION',
                message: 'API key configuration error',
                errors: ['configuration' => 'API key not configured on server'],
                exception: new Exception('API key configuration missing')
            );
        }

        if ($receivedApiKey !== $expectedApiKey) {
            Log::warning('Unauthorized access attempt: Invalid API Key', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'key_type' => $keyType,
                'received_key_prefix' => substr($receivedApiKey, 0, 8) . '...', // Log partial key for debugging
            ]);
            
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNAUTHORIZED,
                errorType: 'ERR_UNAUTHORIZED',
                message: 'Invalid API key',
                errors: ['api_key' => 'The provided API key is invalid'],
                exception: new Exception('Invalid API key')
            );
        }

        // Log successful authentication for audit trail
        Log::info('API request authenticated', [
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'key_type' => $keyType,
            'user_id' => $request->header('X-User-ID'),
        ]);

        return $next($request);
    }
}

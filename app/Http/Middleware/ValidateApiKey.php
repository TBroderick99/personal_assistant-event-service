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
        /* Log::info('Validating API key for request', [
            'request' => $request
        ]); */
        $configKey = 'gateway_api_key';
        $receivedApiKey = $request->header('X-Gateway-API-Key');
        $keyType = 'Gateway';
        $isExternal = true; // Gateway requests are external
        
        // If no gateway API key, check for internal service API key
        if (!$receivedApiKey) {
            $receivedApiKey = $request->header('X-Internal-API-Key');
            $configKey = 'internal_api_key';
            $keyType = 'Internal';
            $isExternal = false; // Internal service requests
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

        // Add header to indicate request type
        $request->headers->set('X-Is-External', $isExternal ? 'true' : 'false');
        // Extract JWT claims if token is present (assuming already validated by gateway)
        $this->extractJwtClaims($request);

        return $next($request);
    }

    /**
     * Extract JWT claims and add them as headers for downstream services
     */
    private function extractJwtClaims(Request $request): void
    {
        $authorizationHeader = $request->header('Authorization');
        
        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            return;
        }

        $token = substr($authorizationHeader, 7); // Remove 'Bearer ' prefix
        
        try {
            // Decode JWT payload (assuming it's already validated by gateway)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return;
            }

            $payload = json_decode(base64_decode($parts[1]), true);
            
            if (!$payload) {
                return;
            }

            if (isset($payload['sub']) && (
                // Condition for Internal requests: X-Is-External is NOT 'true' AND X-User-ID is NOT set (cases where the other service already set the user id).
                ($request->header('X-Is-External') != 'true' && !$request->header('X-User-ID')) ||
                // Condition for External requests: X-Is-External IS 'true'
                ($request->header('X-Is-External') == 'true')
            )) {
                Log::info('Extracting user ID from JWT', [
                    'user_id' => $payload['sub'],
                    'url' => $request->fullUrl(),
                    'is_external' => ($request->header('X-Is-External') == 'true') ? 'true' : 'false', // For better log context
                    'has_x_user_id_before' => (bool)$request->header('X-User-ID'), // For better log context
                ]);
                $request->headers->set('X-User-ID', $payload['sub']);
            }

            // Extract scopes and add as header
            if (isset($payload['scopes'])) {
                $scopes = is_array($payload['scopes']) 
                    ? implode(' ', $payload['scopes']) 
                    : $payload['scopes'];
                $request->headers->set('X-Scopes', $scopes);
            }

        } catch (Exception $e) {
            // Log JWT parsing error but don't fail the request
            Log::warning('Failed to parse JWT claims', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);
        }
    }
}

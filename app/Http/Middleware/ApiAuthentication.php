<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-Key') ?? $request->header('Authorization');
        // Remove 'Bearer ' pr  efix if present
        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (!$token) {
            return $this->unauthorized('API key is required');
        }

        // Validate the API key using the ApiKey model
        $apiKey = ApiKey::validateBearer($token);

        if (!$apiKey) {
            return $this->unauthorized('Invalid API key');
        }

        // Check if user is active
        if (!$apiKey->user || !$apiKey->user->is_active) {
            return $this->unauthorized('Account is inactive');
        }

        // Check IP whitelist
        if (!$apiKey->isIpAllowed($request->ip())) {
            return $this->unauthorized('IP address not allowed');
        }

        // Check rate limiting
        $recentRequests = ApiLog::where('user_id', $apiKey->user_id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        if ($recentRequests >= ($apiKey->rate_limit ?? 100)) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        }

        // Log the API request
        ApiLog::create([
            'user_id' => $apiKey->user_id,
            'branch_id' => $apiKey->branch_id,
            'direction' => 'inbound',
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'request_headers' => ApiLog::headerSummary($request->headers->all()),
            'request_body' => ApiLog::requestSummary($request->except(['api_key', 'branch'])),
            'ip_address' => $request->ip(),
        ]);

        // Mark API key as used
        $apiKey->markAsUsed();

        // Set the authenticated user
        auth()->guard('web')->setUser($apiKey->user);
        $request->merge([
            'api_key' => $apiKey,
            'branch' => $apiKey->branch,
        ]);

        return $next($request);
    }
    
    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'error_code' => 'UNAUTHORIZED',
        ], 401);
    }
}

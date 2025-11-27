<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Models\Customer;
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
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');
        
        // Remove 'Bearer ' prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }
        
        if (!$apiKey) {
            return $this->unauthorized('API key is required');
        }
        
        $customer = Customer::where('api_key', $apiKey)
            ->where('api_enabled', true)
            ->first();
        
        if (!$customer) {
            return $this->unauthorized('Invalid API key');
        }
        
        // Check if user is active
        if (!$customer->user || !$customer->user->is_active) {
            return $this->unauthorized('Account is inactive');
        }
        
        // Check IP whitelist
        if (!$customer->isIpAllowed($request->ip())) {
            return $this->unauthorized('IP address not allowed');
        }
        
        // Check rate limiting (simple implementation)
        $recentRequests = ApiLog::where('user_id', $customer->user_id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subMinute())
            ->count();
            
        if ($recentRequests >= $customer->rate_limit) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        }
        
        // Log the API request
        ApiLog::create([
            'user_id' => $customer->user_id,
            'direction' => 'inbound',
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'request_headers' => $this->sanitizeHeaders($request->headers->all()),
            'request_body' => $request->all(),
            'ip_address' => $request->ip(),
        ]);
        
        // Set the authenticated user
        auth()->setUser($customer->user);
        $request->merge(['api_customer' => $customer]);
        
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
    
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveKeys = ['x-api-key', 'authorization', 'api-key'];
        
        return collect($headers)->map(function ($value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                return ['***REDACTED***'];
            }
            return $value;
        })->toArray();
    }
}


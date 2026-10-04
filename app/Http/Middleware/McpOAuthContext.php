<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class McpOAuthContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $customer = $user?->customer;

        if (! $user || ! $user->is_active || ! $user->isCustomer() || ! $customer?->api_enabled) {
            return response()->json([
                'success' => false,
                'error' => 'This customer account is not permitted to use the MCP connector.',
                'error_code' => 'MCP_ACCESS_DENIED',
            ], 403);
        }

        if (! $user->tokenCan('mcp:use')) {
            return response()->json([
                'success' => false,
                'error' => 'The OAuth token does not grant MCP access.',
                'error_code' => 'INSUFFICIENT_SCOPE',
            ], 401);
        }

        $recentRequests = ApiLog::query()
            ->where('user_id', $user->id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        if ($recentRequests >= max(1, (int) $customer->rate_limit)) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        }

        ApiLog::create([
            'user_id' => $user->id,
            'direction' => 'inbound',
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'request_headers' => ApiLog::headerSummary($request->headers->all()),
            'request_body' => ApiLog::requestSummary($request->all()),
            'ip_address' => $request->ip(),
        ]);

        $request->merge([
            'api_key' => null,
            'branch' => null,
        ]);

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class McpOAuthMetadataController extends Controller
{
    public function protectedResource(?string $path = ''): JsonResponse
    {
        $resource = url('/'.ltrim($path ?? '', '/'));

        return response()->json([
            'resource' => $resource,
            'authorization_servers' => [$resource],
            'scopes_supported' => ['mcp:use'],
            'resource_documentation' => url('/documentation#mcp'),
        ]);
    }

    public function authorizationServer(?string $path = ''): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'.ltrim($path ?? '', '/')),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => route('mcp.oauth.register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp:use'],
        ]);
    }
}

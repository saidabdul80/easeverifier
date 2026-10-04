<?php

use App\Http\Controllers\McpOAuthClientController;
use App\Http\Controllers\McpOAuthMetadataController;
use App\Mcp\Servers\EaseVerifierServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/oauth-protected-resource/{path?}', [McpOAuthMetadataController::class, 'protectedResource'])
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource');

Route::get('/.well-known/oauth-authorization-server/{path?}', [McpOAuthMetadataController::class, 'authorizationServer'])
    ->where('path', '.*')
    ->name('mcp.oauth.authorization-server');

Route::post('/oauth/register', McpOAuthClientController::class)
    ->middleware('throttle:20,1')
    ->name('mcp.oauth.register');

Mcp::web('/api/mcp', EaseVerifierServer::class)
    ->middleware(['api', 'auth:api', 'mcp.oauth.context', 'throttle:100,1'])
    ->name('mcp.easeverifier');

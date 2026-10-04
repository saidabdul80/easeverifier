<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mcp\RegisterOAuthClientRequest;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\ClientRepository;

class McpOAuthClientController extends Controller
{
    public function __invoke(
        RegisterOAuthClientRequest $request,
        ClientRepository $clients,
    ): JsonResponse {
        $validated = $request->validated();
        $client = $clients->createAuthorizationCodeGrantClient(
            name: $validated['client_name'] ?? $validated['name'],
            redirectUris: $validated['redirect_uris'],
            confidential: false,
        );

        return response()->json([
            'client_id' => (string) $client->id,
            'client_id_issued_at' => now()->timestamp,
            'client_name' => $client->name,
            'grant_types' => $client->grant_types,
            'response_types' => ['code'],
            'redirect_uris' => $client->redirect_uris,
            'scope' => 'mcp:use',
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }
}

<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use JsonException;
use Laravel\Mcp\Response;

trait CallsEaseVerifierApi
{
    /**
     * Build an internal request carrying the identity established by API authentication.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function apiRequest(array $payload = [], string $method = 'POST'): HttpRequest
    {
        $currentRequest = request();
        $apiRequest = HttpRequest::create(
            $currentRequest->fullUrl(),
            $method,
            $payload,
            server: $currentRequest->server->all(),
        );

        $apiRequest->headers->replace($currentRequest->headers->all());
        $apiRequest->setUserResolver(fn () => $currentRequest->user());
        $apiRequest->merge([
            'api_key' => $currentRequest->get('api_key'),
            'branch' => $currentRequest->get('branch'),
        ]);

        return $apiRequest;
    }

    /**
     * Preserve the existing API payload while expressing failures as MCP tool errors.
     *
     * @throws JsonException
     */
    protected function apiResponse(JsonResponse $response): Response
    {
        $payload = $response->getData(true);

        if ($response->isSuccessful() && ($payload['success'] ?? true) !== false) {
            return Response::json($payload);
        }

        $payload['http_status'] ??= $response->getStatusCode();

        return Response::error(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}

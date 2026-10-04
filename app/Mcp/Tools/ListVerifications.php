<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\VerificationController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
class ListVerifications extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'list_verifications';

    protected string $title = 'List verification history';

    protected string $description = 'List verification history for the authenticated customer or branch, optionally filtered by service slug and status.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'service' => $schema->string()->description('Optional verification service slug.'),
            'status' => $schema->string()->enum(['pending', 'processing', 'completed', 'failed'])->description('Optional verification status.'),
            'page' => $schema->integer()->min(1)->description('Page number. Defaults to 1.'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Results per page. Defaults to 20.'),
        ];
    }

    public function handle(Request $request, VerificationController $controller): Response
    {
        $payload = $request->validate([
            'service' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,processing,completed,failed'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->apiResponse($controller->history($this->apiRequest($payload, 'GET')));
    }
}

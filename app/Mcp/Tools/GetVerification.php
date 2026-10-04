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
class GetVerification extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'get_verification';

    protected string $title = 'Get verification';

    protected string $description = 'Retrieve one verification owned by the authenticated customer or branch using its EaseVerifier reference.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->max(100)->description('EaseVerifier verification reference.')->required(),
        ];
    }

    public function handle(Request $request, VerificationController $controller): Response
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        return $this->apiResponse($controller->showByReference(
            $this->apiRequest(method: 'GET'),
            $validated['reference'],
        ));
    }
}

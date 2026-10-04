<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ResultVerificationController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsDestructive]
#[IsOpenWorld(false)]
class GetResultRequirements extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'get_result_requirements';

    protected string $title = 'Get result requirements';

    protected string $description = 'Load live form fields and options for an examination board. The existing EaseVerifier API may charge the wallet for this provider-backed operation, so obtain confirmation before calling it.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'board' => $schema->string()->enum(['waec', 'neco', 'nbais', 'nabteb'])->description('Examination board.')->required(),
        ];
    }

    public function handle(Request $request, ResultVerificationController $controller): Response
    {
        $validated = $request->validate([
            'board' => ['required', 'in:waec,neco,nbais,nabteb'],
        ]);

        return $this->apiResponse($controller->form(
            $this->apiRequest(method: 'GET'),
            $validated['board'],
        ));
    }
}

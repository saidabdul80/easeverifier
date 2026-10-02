<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ResultVerificationController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use App\Services\ResultVerify\ResultGates\NbaisResult;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
class ListNbaisSchools extends Tool
{
    use CallsEaseVerifierApi;

    protected string $name = 'list_nbais_schools';

    protected string $title = 'List NBAIS schools';

    protected string $description = 'List NBAIS schools for a parent category when a live NBAIS verification form requires a school selection.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'parent_cat' => $schema->string()->max(10)->description('NBAIS parent category identifier.')->required(),
        ];
    }

    public function handle(
        Request $request,
        ResultVerificationController $controller,
        NbaisResult $nbaisResult,
    ): Response {
        $payload = $request->validate([
            'parent_cat' => ['required', 'string', 'max:10'],
        ]);

        return $this->apiResponse($controller->nbaisSchools(
            $this->apiRequest($payload, 'GET'),
            $nbaisResult,
        ));
    }
}

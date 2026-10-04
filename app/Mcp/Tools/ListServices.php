<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\VerificationController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
class ListServices extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'list_services';

    protected string $title = 'List verification services';

    protected string $description = 'List active EaseVerifier services with the authenticated customer pricing and currency. Use the returned slug and price to confirm a charge before verify_identity or result tools.';

    public function handle(Request $request, VerificationController $controller): Response
    {
        return $this->apiResponse($controller->services($this->apiRequest(method: 'GET')));
    }
}

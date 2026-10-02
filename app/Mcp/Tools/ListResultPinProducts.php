<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ResultPinController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
class ListResultPinProducts extends Tool
{
    use CallsEaseVerifierApi;

    protected string $name = 'list_result_pin_products';

    protected string $title = 'List result PIN products';

    protected string $description = 'List result PIN and token products available to the authenticated customer, including current customer pricing and quantity limits.';

    public function handle(Request $request, ResultPinController $controller): Response
    {
        return $this->apiResponse($controller->products($this->apiRequest(method: 'GET')));
    }
}

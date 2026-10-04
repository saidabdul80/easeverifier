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
class GetWalletBalance extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'get_wallet_balance';

    protected string $title = 'Get wallet balance';

    protected string $description = 'Return the authenticated customer or branch wallet balance and currency. This tool does not charge the wallet.';

    public function handle(Request $request, VerificationController $controller): Response
    {
        return $this->apiResponse($controller->walletBalance($this->apiRequest(method: 'GET')));
    }
}

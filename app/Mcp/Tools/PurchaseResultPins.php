<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ResultPinController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsDestructive]
#[IsOpenWorld(false)]
class PurchaseResultPins extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'purchase_result_pins';

    protected string $title = 'Purchase result PINs';

    protected string $description = 'Purchase result checker PINs or tokens using the authenticated customer wallet. This action charges money and can reveal sensitive PIN values. Invoke exactly once and only after the user confirms the product, quantity, and charge.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->min(1)->description('EaseVerifier result PIN product ID. Use either product_id or card_type_id.'),
            'card_type_id' => $schema->string()->max(50)->description('Provider card type ID. Use either card_type_id or product_id.'),
            'quantity' => $schema->integer()->min(1)->max(100)->description('Number of PINs or tokens to purchase.')->required(),
        ];
    }

    public function handle(Request $request, ResultPinController $controller): Response
    {
        $payload = $request->validate([
            'product_id' => ['nullable', 'integer', 'min:1'],
            'card_type_id' => ['nullable', 'string', 'max:50'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->apiResponse($controller->purchase($this->apiRequest($payload)));
    }
}

<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetResultRequirements;
use App\Mcp\Tools\GetVerification;
use App\Mcp\Tools\GetWalletBalance;
use App\Mcp\Tools\ListNbaisSchools;
use App\Mcp\Tools\ListResultPinProducts;
use App\Mcp\Tools\ListServices;
use App\Mcp\Tools\ListVerifications;
use App\Mcp\Tools\PurchaseResultPins;
use App\Mcp\Tools\VerifyIdentity;
use App\Mcp\Tools\VerifyResult;
use Laravel\Mcp\Server;

class EaseVerifierServer extends Server
{
    protected string $name = 'EaseVerifier';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Use EaseVerifier to discover verification services, check wallet and verification history,
        verify identity records, fetch examination results, and purchase result PINs for the
        authenticated customer account. Verification and purchase tools can charge the customer's
        wallet. Obtain the user's explicit confirmation immediately before invoking a chargeable
        tool. Never repeat a chargeable call merely because a response was slow or ambiguous.
        Never expose API credentials, result PINs, examination tokens, or card serial numbers in
        summaries beyond what the user explicitly needs.
    MARKDOWN;

    /** @var array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected array $tools = [
        ListServices::class,
        GetWalletBalance::class,
        ListVerifications::class,
        GetVerification::class,
        VerifyIdentity::class,
        GetResultRequirements::class,
        VerifyResult::class,
        ListNbaisSchools::class,
        ListResultPinProducts::class,
        PurchaseResultPins::class,
    ];
}

<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\VerificationController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsDestructive]
#[IsOpenWorld(false)]
class VerifyIdentity extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'verify_identity';

    protected string $title = 'Verify identity record';

    protected string $description = 'Run an identity or business verification using an active service slug. This action may charge the authenticated customer wallet. Invoke only after the user confirms the charge and confirms they have lawful authority and consent to verify the identifier.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'service' => $schema->string()->max(100)->description('Active service slug, such as nin, bvn, cac, or drivers-license.')->required(),
            'identifier' => $schema->string()->max(255)->description('Identifier to verify, such as a NIN, BVN, CAC registration number, or driver license number.')->required(),
            'consent' => $schema->boolean()->description('Must be true to confirm lawful authority and subject consent.')->required(),
        ];
    }

    public function handle(Request $request, VerificationController $controller): Response
    {
        $validated = $request->validate([
            'service' => ['required', 'string', 'max:100'],
            'identifier' => ['required', 'string', 'max:255'],
            'consent' => ['required', 'accepted'],
        ]);

        $identifierField = match ($validated['service']) {
            'nin' => 'nin',
            'bvn' => 'bvn',
            'cac' => 'rc_number',
            'drivers-license' => 'license_number',
            default => 'search_parameter',
        };

        return $this->apiResponse($controller->verify(
            $this->apiRequest([
                $identifierField => $validated['identifier'],
                'consent' => true,
            ]),
            $validated['service'],
        ));
    }
}

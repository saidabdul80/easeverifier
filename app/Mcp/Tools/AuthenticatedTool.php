<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tool;

abstract class AuthenticatedTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'securitySchemes' => [
                [
                    'type' => 'oauth2',
                    'scopes' => ['mcp:use'],
                ],
            ],
        ];
    }
}

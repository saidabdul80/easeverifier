<?php

use App\Mcp\Servers\EaseVerifierServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/api/mcp', EaseVerifierServer::class)
    ->middleware(['api', 'api.auth', 'throttle:100,1'])
    ->name('mcp.easeverifier');

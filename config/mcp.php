<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | An "*" may be used to allow all domains.
    |
    */

    'redirect_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('MCP_OAUTH_REDIRECT_DOMAINS', 'https://chatgpt.com')),
    ))),

];

<?php

return [

    /*
     * The address(es) of the reverse proxy (nginx, Cloudflare, a load balancer) that sits in front of the site, comma separated,
     * or "*" when the server can only be reached through that proxy. Without this, Laravel does not believe the forwarded
     * address and protocol: every visitor then has the address of the proxy, and one shared limit for the plate search.
     * Do NOT use "*" when visitors can reach the server directly: they could then make up their own address.
     */
    'proxies' => env('TRUSTED_PROXIES'),

];

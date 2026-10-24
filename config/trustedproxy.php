<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
| Only requests arriving FROM these addresses may tell Laravel the visitor's
| real IP through X-Forwarded-For. Leave TRUSTED_PROXIES empty when Nginx
| talks to PHP-FPM directly (the EC2 setup): the IP is then the real
| connecting address and cannot be faked with a header.
|
| Behind an AWS load balancer or CloudFront, set it to that proxy's range,
| e.g. TRUSTED_PROXIES=172.31.0.0/16 (comma-separated for several).
*/

return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];

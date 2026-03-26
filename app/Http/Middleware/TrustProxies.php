<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * OPTIMIZADO: Especificar proxies explícitamente en lugar de '*' para mejor rendimiento
     * Si usas Cloudflare o similar, especifica aquí: ['*'] o IPs específicas
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*'; // Cambiar a IPs específicas si conoces tus proxies

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}

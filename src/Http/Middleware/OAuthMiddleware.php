<?php

namespace Byteam\Spectre\Http\Middleware;

use Byteam\Spectre\Exceptions\HttpJsonException;
use Closure;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;

class OAuthMiddleware
{
    /** @var ResourceServer $resourceServer */
    protected $resourceServer;

    /**
     * OAuthMiddleware constructor.
     * @param ResourceServer $resourceServer
     */
    public function __construct(ResourceServer $resourceServer)
    {
        $this->resourceServer = $resourceServer;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $psr = (new DiactorosFactory)->createRequest($request);

        try {
            $psr = $this->resourceServer->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            throw new HttpJsonException(
                $e->getHttpStatusCode(),
                [],
                $e->getMessage());
        }

        return $next($request);
    }
}

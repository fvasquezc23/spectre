<?php

namespace Byteam\Spectre\Providers;

use Byteam\Spectre\OAuth\AccessTokenRepository;
use Byteam\Spectre\OAuth\ClientRepository;
use Byteam\Spectre\OAuth\RefreshTokenRepository;
use Byteam\Spectre\OAuth\ScopeRepository;
use Byteam\Spectre\OAuth\UserRepository;
use Byteam\Spectre\User;
use Illuminate\Support\ServiceProvider;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;

class SpectreServiceProvider extends ServiceProvider
{
    protected $userType;

    /**
     *
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutes();

        $this->app->routeMiddleware([
            'oauth' => \Byteam\Spectre\Http\Middleware\OAuthMiddleware::class
        ]);

        $this->app['auth']->viaRequest('api', function ($request) {
            return $this->getUserViaRequest($request);
        });

        $this->app->configure('spectre');
        if (!is_null(config('spectre.user.class'))) {
            $this->userType = config('spectre.user.class');
        }
        else {
            $this->userType = User::class;
        }
    }

    /**
     *
     */
    public function register()
    {
        $this->app->singleton(AuthorizationServer::class, function () {
            return tap($this->makeAuthorizationServer(), function ($server) {
                $server->enableGrantType($this->makePasswordGrant(), new \DateInterval('PT1H'));
            });
        });

        $this->app->singleton(ResourceServer::class, function () {
            return $this->makeResourceServer();
        });
    }

    /**
     *
     */
    private function loadRoutes()
    {
        $this->app->router->group([
            'namespace' => 'Byteam\Spectre\Http\Controllers',
        ], function ($router) {
            require __DIR__.'/../../routes/web.php';
        });
    }

    /**
     * @return AuthorizationServer
     */
    private function makeAuthorizationServer()
    {
        return new AuthorizationServer(
            new ClientRepository,
            new AccessTokenRepository,
            new ScopeRepository,
            new CryptKey(storage_path('oauth-private.key'), null, false),
            app('encrypter')->getKey()
        );
    }

    /**
     * @return PasswordGrant
     * @throws \Exception
     */
    private function makePasswordGrant()
    {
        $passwordGrant =  new PasswordGrant(
            new UserRepository,
            new RefreshTokenRepository()
        );

        $passwordGrant->setRefreshTokenTTL(new \DateInterval('P1M'));

        return $passwordGrant;
    }

    /**
     * @return ResourceServer
     */
    private function makeResourceServer()
    {
        return new ResourceServer(
            new AccessTokenRepository(),
            new CryptKey(storage_path('oauth-public.key'), null, false)
        );
    }


    private function getUserViaRequest($request)
    {
        $psr = (new DiactorosFactory)->createRequest($request);
        try {
            $psr = $this->app->make(ResourceServer::class)
                ->validateAuthenticatedRequest($psr);

            return (new $this->userType)->find($psr->getAttribute('oauth_user_id'));
        } catch (OAuthServerException $e) {
            return null;
        }
    }
}
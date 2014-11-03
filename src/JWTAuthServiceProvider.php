<?php

namespace Tymon\JWTAuth;

use Illuminate\Support\ServiceProvider;
use Tymon\JWTAuth\Commands\JWTGenerateCommand;
use Tymon\JWTAuth\Filters\JWTAuthFilter;
use Tymon\JWTAuth\JWTAuth;

class JWTAuthServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the service provider.
     */
    public function boot()
    {
        $this->package('tymon/jwt-auth', 'jwt');

        $this->bootBindings();

        // register the command
        $this->commands('tymon.jwt.generate');

        // register the filter
        $this->app['router']->filter('jwt-auth', 'tymon.jwt.filter');
    }

    /**
     * Bind some Interfaces and implementations
     */
    protected function bootBindings()
    {
        $this->app['Tymon\JWTAuth\JWTAuth'] = function ($app) {
            return $app['tymon.jwt.auth'];
        };

         $this->app['Tymon\JWTAuth\User\UserInterface'] = function ($app) {
            return $app['tymon.jwt.provider.user'];
        };

        $this->app['Tymon\JWTAuth\JWT\JWTInterface'] = function ($app) {
            return $app['tymon.jwt.provider.jwt'];
        };

        $this->app['Tymon\JWTAuth\Auth\AuthInterface'] = function ($app) {
            return $app['tymon.jwt.provider.auth'];
        };
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // register providers
        $this->registerUserProvider();
        $this->registerJWTProvider();
        $this->registerAuthProvider();
        $this->registerStorageProvider();

        $this->registerJWTAuth();
        $this->registerJWTAuthFilter();
        $this->registerJWTCommand();
    }

    /**
     * Register the bindings for the User provider
     */
    protected function registerUserProvider()
    {
        $this->app['tymon.jwt.provider.user'] = $this->app->share(function ($app) {
            return $app->make($this->config('user'));
        });
    }

    /**
     * Register the bindings for the JSON Web Token provider
     */
    protected function registerJWTProvider()
    {
        $this->app['tymon.jwt.provider'] = $this->app->share(function ($app) {
            $secret = $this->config('secret');
            $ttl = $this->config('ttl');
            $algo = $this->config('algo');
            $provider = $this->config('providers.jwt');

            $instance = $app->make($provider, [ $secret, $app['request'] ]);

            return $instance->setTTL($ttl)->setAlgo($algo);
        });
    }

    /**
     * Register the bindings for the Auth provider
     */
    protected function registerAuthProvider()
    {
        $this->app['tymon.jwt.provider.auth'] = $this->app->share(function ($app) {
            return $app->make($this->config('providers.auth'), [ $app['auth'] ]);
        });
    }

    /**
     * Register the bindings for the Storage provider
     */
    protected function registerStorageProvider()
    {
        $this->app['tymon.jwt.provider.storage'] = $this->app->share(function ($app) {
            return $app->make($this->config('providers.storage'), [ $app['cache'] ]);
        });
    }

    /**
     * Register the bindings for the main JWTAuth class
     */
    protected function registerJWTAuth()
    {
        $this->app['tymon.jwt.provider.auth'] = $this->app->share(function ($app) {
            $identifier = $this->config('identifier');

            $auth = new JWTAuth(
                $app['tymon.jwt.provider.user'],
                $app['tymon.jwt.provider.jwt'],
                $app['tymon.jwt.provider.auth'],
                $app['request']
            );

            return $auth->setIdentifier($identifier);
        });
    }

    /**
     * Register the bindings for the 'jwt-auth' filter
     */
    protected function registerJWTAuthFilter()
    {
        $this->app['tymon.jwt.filter'] = $this->app->share(function ($app) {
            return new JWTAuthFilter($app['events'], $app['tymon.jwt.auth']);
        });
    }

    /**
     * Register the Artisan command
     */
    protected function registerJWTCommand()
    {
        $this->app['tymon.jwt.generate'] = $this->app->share(function ($app) {
            return new JWTGenerateCommand($app['files']);
        });
    }

    /**
     * Helper to get the config values
     * @param string $key
     * @return string
     */
    protected function config($key, $default = null)
    {
        return $this->app['config']->get("jwt::$key", $default);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [
            'tymon.jwt.auth',
            'tymon.jwt.provider.user',
            'tymon.jwt.provider.jwt',
            'tymon.jwt.provider.auth',
            'tymon.jwt.generate',
            'tymon.jwt.filter',
            'Tymon\JWTAuth\JWTAuth',
            'Tymon\JWTAuth\User\UserInterface',
            'Tymon\JWTAuth\JWT\JWTInterface',
            'Tymon\JWTAuth\Auth\AuthInterface'
        ];
    }
}

<?php

namespace Haibian\Restapi;

use Illuminate\Support\ServiceProvider;

class RestapiServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/restapi.php' => config_path('restapi.php')
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/restapi.php', 'restapi'
        );

        // Bind captcha
        $this->app->singleton('restapi', function($app)
        {
            return new Restapi(
                $app['Illuminate\Config\Repository']
            );
        });
    }
}
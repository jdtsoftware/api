<?php

namespace JDT\Api\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler as IlluminateExceptionHandler;
use Illuminate\Support\ServiceProvider;
use JDT\Api\Contracts\ExceptionHandler;
use JDT\Api\Exceptions\Handler;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->make('request')->is('api/*')) {
            $exceptionHandler = app(ExceptionHandler::class);
            $this->app->instance(IlluminateExceptionHandler::class, $exceptionHandler);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/api.php' => config_path('api.php'),
            ], 'config');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/api.php', 'api');

        $this->app->singleton(ExceptionHandler::class, function ($app) {
            $config = $app['config'];

            return new Handler(
                $app[IlluminateExceptionHandler::class],
                $config->get('api.errorFormat'),
                $config->get('api.debug')
            );
        });

        $this->app->alias(ExceptionHandler::class, Handler::class);
    }
}
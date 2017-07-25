<?php

namespace JDT\Api\Traits;

use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use JDT\Api\InternalRequest;
use JDT\Api\Response\Factory;
use Spatie\Fractal\Fractal;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait Helper
{
    /**
     * @return \JDT\Api\Response\Factory
     */
    protected function response():Factory
    {
        return new Factory();
    }

    /**
     * @return \JDT\Api\InternalRequest
     */
    protected function request()
    {
        return app(InternalRequest::class);
    }

    /**
     * @return \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function auth()
    {
        return app(AuthManager::class)->guard('api');
    }

    /**
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function user()
    {
        return $this->auth()->user();
    }
}
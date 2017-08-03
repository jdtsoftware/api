<?php

namespace JDT\Api\Contracts;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler as IlluminateExceptionHandler;
use Illuminate\Http\JsonResponse;

interface ExceptionHandler extends IlluminateExceptionHandler
{
    /**
     * Handle an exception.
     *
     * @param \Exception $exception
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Exception $exception):JsonResponse;
}

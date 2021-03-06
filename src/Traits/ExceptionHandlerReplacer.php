<?php

namespace JDT\Api\Traits;

use JDT\Api\Http\InternalApiRequest;
use JDT\Api\Contracts\ExceptionHandler;
use Illuminate\Contracts\Debug\ExceptionHandler as IlluminateExceptionHandler;

trait ExceptionHandlerReplacer
{
    protected function replaceExceptionHandler(callable $callable)
    {
        if (app('request') instanceof InternalApiRequest) {
            return call_user_func($callable);
        }

        $laravelExceptionHandler = app(IlluminateExceptionHandler::class);
        $exceptionHandler = app(ExceptionHandler::class);
        app()->instance(IlluminateExceptionHandler::class, $exceptionHandler);

        try {
            return call_user_func($callable);
        } catch (\Exception $ex) {
            $exceptionHandler->report($ex);

            return $exceptionHandler->render(null, $ex);
        } finally {
            app()->instance(IlluminateExceptionHandler::class, $laravelExceptionHandler);
        }
    }
}

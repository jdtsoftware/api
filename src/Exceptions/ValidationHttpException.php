<?php

declare(strict_types=1);

namespace JDT\Api\Exceptions;

use Dingo\Api\Contract\Debug\MessageBagErrors;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ValidationHttpException extends ValidationException implements MessageBagErrors, HttpExceptionInterface
{
    public function getStatusCode()
    {
        return 422;
    }

    public function getHeaders()
    {
        return [];
    }

    /**
     * Get the errors message bag.
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function getErrors()
    {
        return $this->validator->errors();
    }

    /**
     * Determine if message bag has any errors.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return !$this->validator->errors()->isEmpty();
    }
}
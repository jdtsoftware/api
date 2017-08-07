<?php

declare(strict_types=1);

namespace JDT\Api\Exceptions;

use Illuminate\Support\MessageBag;
use JDT\Api\Contracts\MessageBagErrors;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ValidationHttpException extends ValidationException implements MessageBagErrors, HttpExceptionInterface
{
    /**
     * @return int
     */
    public function getStatusCode()
    {
        return 422;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return [];
    }

    /**
     * Get the errors message bag.
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function getErrors():MessageBag
    {
        return $this->validator->errors();
    }

    /**
     * Determine if message bag has any errors.
     *
     * @return bool
     */
    public function hasErrors():bool
    {
        return !$this->validator->errors()->isEmpty();
    }
}

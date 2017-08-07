<?php

namespace JDT\Api\Contracts;

use Illuminate\Support\MessageBag;

interface MessageBagErrors
{
    /**
     * Get the errors message bag.
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function getErrors():MessageBag;

    /**
     * Determine if message bag has any errors.
     *
     * @return bool
     */
    public function hasErrors():bool;
}

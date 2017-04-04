<?php

declare(strict_types=1);

namespace JDT\Api\Contracts;

use JDT\Api\Payload;

interface ModifyPayloadPostValidation
{
    /**
     * @param \JDT\Api\Payload $payload
     * @return \JDT\Api\Payload
     */
    public function modifyPayloadPostValidation(Payload $payload):Payload;
}
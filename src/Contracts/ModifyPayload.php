<?php

declare(strict_types=1);

namespace JDT\Api\Contracts;

use JDT\Api\Payload;

interface ModifyPayload
{
    /**
     * @param \JDT\Api\Payload $payload
     * @return \JDT\Api\Payload
     */
    public function modifyPayload(Payload $payload):Payload;
}
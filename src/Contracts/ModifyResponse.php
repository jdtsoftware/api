<?php

namespace JDT\Api\Contracts;

use Dingo\Api\Http\Response;

interface ModifyResponse
{
    /**
     * @param \Dingo\Api\Http\Response $response
     * @return mixed
     */
    public function modifyResponse(Response $response);
}

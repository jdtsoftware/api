<?php

namespace JDT\Api\Contracts;

use Illuminate\Http\JsonResponse;

interface ModifyResponse
{
    /**
     * @param \Illuminate\Http\JsonResponse $response
     * @return mixed
     */
    public function modifyResponse(JsonResponse $response);
}

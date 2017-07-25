<?php

namespace JDT\Api\Http\Controllers;

use JDT\Api\Payload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JDT\Api\Contracts\ApiEndpoint;
use Illuminate\Routing\Controller as BaseController;

class ApiController extends BaseController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function actionExecute(Request $request):JsonResponse
    {
        $action = $request->route()->getAction();

        if (!isset($action['api'])) {
            throw new \Exception('Please specify a api in your routes.');
        }

        $apiClass = $action['api'];
        $api = app($apiClass);

        if (!($api instanceof ApiEndpoint)) {
            throw new \Exception('The defined service must be an instance of "JDT\Api\Contracts\ApiEndpoint"');
        }

        $payload = array_merge($request->all(), $request->route()->parameters());
        $payload = new Payload($payload);

        return $api->execute($payload);
    }
}

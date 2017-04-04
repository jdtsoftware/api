<?php

declare(strict_types=1);

namespace JDT\Api\Traits;

use JDT\Api\Payload;
use Dingo\Api\Http\Response;
use Dingo\Api\Routing\Helpers;
use JDT\Api\Contracts\ApiEndpoint;
use Illuminate\Validation\Validator;
use JDT\Api\Contracts\ModifyPayload;
use JDT\Api\Contracts\ModifyResponse;
use JDT\Api\Exceptions\ValidationHttpException;
use JDT\Api\Contracts\ModifyPayloadPostValidation;

trait MultipleEndpoint
{
    use Helpers;

    protected $apiList = [];
    protected $builtApiList;
    protected $callbackList = [];

    /**
     * @param string $identifier
     * @param string $endpointClass
     * @param callable $callback
     * @return \JDT\Api\Traits\MultipleEndpoint
     */
    public function addApi(string $identifier, string $endpointClass, callable $callback):self
    {
        $this->apiList[$identifier] = $endpointClass;
        $this->callbackList[$identifier] = $callback;

        return $this;
    }

    /**
     * Get the bulk identifier key.
     * @return string
     */
    public function getBulkIdentifier():string
    {
        return '';
    }

    /**
     * @param \JDT\Api\Payload $payload
     * @return array
     */
    public function buildRules(Payload $payload):array
    {
        $rules = [];

        foreach ($this->builtApiList() as $key => $api) {
            $internalPayload = $payload->pluck($key);
            $internalRules = $api->buildRules($internalPayload);

            $rules[$key] = 'array';
            foreach ($internalRules as $internalKey => $internalValue) {
                $rules[$key . '.' . $internalKey] = $internalValue;
            }
        }

        return $rules;
    }

    /**
     * Execute the api endpoint.
     * @param \JDT\Api\Payload $payload
     * @return \Dingo\Api\Http\Response
     */
    public function execute(Payload $payload):Response
    {
        \DB::transaction(function () use ($payload) {
            if ($this instanceof ModifyPayload) {
                $payload = $this->modifyPayload($payload);
            }

            $validation = $this->getValidation($payload);

            if ($validation->passes()) {
                if ($this instanceof ModifyPayloadPostValidation) {
                    $payload = $this->modifyPayloadPostValidation($payload);
                }

                $return = [];
                foreach ($this->buildApiList() as $key => $api) {
                    $internalPayload = $payload->pluck($key);
                    $result = $api->execute($internalPayload);

                    if (isset($this->callbackList[$key])) {
                        $callback = $this->callbackList[$key];
                        $callback($result, $payload);
                    }

                    $return[$key] = $result->getOriginalContent();
                }

                $response = $this->response()->array(['data' => $return]);

                if ($this instanceof ModifyResponse) {
                    $this->modifyResponse($response);
                }

                return $response;
            } else {
                throw new ValidationHttpException($validation);
            }
        });
    }

    /**
     * @return \JDT\Api\Contracts\ApiEndpoint[]
     */
    protected function buildApiList():array
    {
        return tap($this->builtApiList, function (&$value) {
            if ($value === null) {
                $builtApiList = [];

                foreach ($this->apiList as $key => $apiClass) {
                    if (is_a($apiClass, ApiEndpoint::class) === false) {
                        throw new \Exception('The defined service must be an instance of "App\Services\Api\Contracts\ApiEndpoint"');
                    }

                    $builtApiList[$key] = app($apiClass);
                }

                $this->builtApiList = $value = $builtApiList;
            }
        });
    }

    /**
     * @param \JDT\Api\Payload $payload
     * @return \Illuminate\Validation\Validator
     */
    protected function getValidation(Payload $payload):Validator
    {
        $rules = $this->buildRules($payload);

        return \Validator::make($payload->getPayload(), $rules);
    }
}

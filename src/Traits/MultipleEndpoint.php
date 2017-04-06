<?php

declare(strict_types=1);

namespace JDT\Api\Traits;

use Illuminate\Database\Eloquent\Model;
use JDT\Api\Contracts\TransformerAwareModel;
use JDT\Api\Payload;
use Dingo\Api\Http\Response;
use Dingo\Api\Routing\Helpers;
use JDT\Api\Contracts\ApiEndpoint;
use Illuminate\Validation\Validator;
use JDT\Api\Contracts\ModifyPayload;
use JDT\Api\Contracts\ModifyResponse;
use JDT\Api\Exceptions\ValidationHttpException;
use JDT\Api\Contracts\ModifyPayloadPostValidation;
use JDT\Api\Transformers\AbstractTransformer;
use JDT\Api\Transformers\DefaultModelTransformer;

trait MultipleEndpoint
{
    use Helpers;

    protected $apiList = [];
    protected $builtApiList;
    protected $callbackList = [];
    protected $excludeValidationRulesList = [];

    /**
     * @param string $identifier
     * @param string $endpointClass
     * @param callable $callback
     * @param array $excludeValidationRules
     * @return \JDT\Api\Traits\MultipleEndpoint
     */
    public function addApi(string $identifier, string $endpointClass, callable $callback = null, array $excludeValidationRules = []):self
    {
        $this->apiList[$identifier] = $endpointClass;
        if ($callback !== null) {
            $this->callbackList[$identifier] = $callback;
        }
        $this->excludeValidationRulesList[$identifier] = $excludeValidationRules;

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

        foreach ($this->buildApiList() as $key => $api) {
            $internalPayload = $payload->pluck($key);
            $internalRules = $api->buildRules($internalPayload);

            $rules[$key] = 'array';
            foreach ($internalRules as $internalKey => $internalValue) {
                if (
                    !empty($this->excludeValidationRulesList[$key]) &&
                    in_array($internalKey, $this->excludeValidationRulesList[$key])
                ) {
                    continue;
                }

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
        return \DB::transaction(function () use ($payload) {
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
                    $originalContent = $result->getOriginalContent();

                    if (isset($this->callbackList[$key])) {
                        $callback = $this->callbackList[$key];
                        $callback($originalContent, $payload);
                    }

                    $return[$key] = $this->transform($api, $originalContent);
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
     * @param \JDT\Api\Contracts\ApiEndpoint $api
     * @param mixed $originalContent
     * @return array
     */
    protected function transform(ApiEndpoint $api, $originalContent):array
    {
        if ($api instanceof AbstractTransformer) {
            return $api->transform($originalContent);
        } elseif (is_object($originalContent) && $originalContent instanceof Model) {
            if ($originalContent instanceof TransformerAwareModel) {
                return $originalContent->getTransformer()->transform($originalContent);
            } else {
                return (new DefaultModelTransformer())->transform($originalContent);
            }
        } else {
            return (array) $originalContent;
        }
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
                    $class = app($apiClass);

                    if (!($class instanceof ApiEndpoint)) {
                        throw new \Exception($apiClass . ' must be an instance of ' . ApiEndpoint::class);
                    }

                    $builtApiList[$key] = $class;
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

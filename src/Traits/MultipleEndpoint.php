<?php

declare(strict_types=1);

namespace JDT\Api\Traits;

use JDT\Api\Payload;
use Illuminate\Http\JsonResponse;
use JDT\Api\Contracts\ApiEndpoint;
use Illuminate\Validation\Validator;
use JDT\Api\Contracts\ModifyFactory;
use JDT\Api\Contracts\ModifyPayload;
use JDT\Api\Contracts\ModifyResponse;
use Illuminate\Database\Eloquent\Model;
use JDT\Api\Contracts\TransformerAwareModel;
use JDT\Api\Transformers\AbstractTransformer;
use JDT\Api\Exceptions\ValidationHttpException;
use JDT\Api\Transformers\DefaultModelTransformer;
use JDT\Api\Contracts\ModifyPayloadPostValidation;

trait MultipleEndpoint
{
    use Helper, ExceptionHandlerReplacer;

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Payload $payload):JsonResponse
    {
        return $this->replaceExceptionHandler(function () use ($payload) {
            return \DB::transaction(function () use ($payload) {
                if ($this instanceof ModifyPayload) {
                    $payload = $this->modifyPayload($payload);
                }

                $validation = $this->getValidation($payload);

                if ($validation->passes()) {
                    if ($this instanceof ModifyPayloadPostValidation) {
                        $payload = $this->modifyPayloadPostValidation($payload);
                    }

                    $return = new \stdClass();
                    foreach ($this->buildApiList() as $key => $api) {
                        $internalPayload = $payload->pluck($key);
                        $result = $api->execute($internalPayload);
                        $originalContent = $result->getOriginalContent();

                        if (isset($this->callbackList[$key])) {
                            $callback = $this->callbackList[$key];
                            $callback($originalContent, $payload);
                        }

                        if ($result->getContent() === null) {
                            $content = [];
                        } else {
                            $content = json_decode($result->getContent(), true);
                        }

                        $return->{$key} = $content['data'];
                    }

                    $factory = $this->response()->item($return, function($data) {
                        return (array) $data;
                    });

                    if ($this instanceof ModifyFactory) {
                        $this->modifyFactory($factory);
                    }

                    $response = $factory->transform();

                    if ($this instanceof ModifyResponse) {
                        $this->modifyResponse($response);
                    }

                    return $response;
                } else {
                    throw new ValidationHttpException($validation);
                }
            });
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

<?php

declare(strict_types=1);

namespace JDT\Api\Traits;

use JDT\Api\Payload;
use Illuminate\Support\Str;
use JDT\Api\Field\FieldApi;
use JDT\Api\Field\FieldList;
use JDT\Api\Response\Factory;
use JDT\Api\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Validator;
use JDT\Api\Contracts\ModifyFactory;
use JDT\Api\Contracts\ModifyPayload;
use JDT\Api\Contracts\ModifyResponse;
use Illuminate\Database\Eloquent\Builder;
use JDT\Api\Exceptions\ValidationHttpException;
use JDT\Api\Contracts\ModifyPayloadPostValidation;

trait ApiEndpoint
{
    use Helper, ExceptionHandlerReplacer;

    /**
     * @var \JDT\Api\Payload
     */
    private $payload;

    /**
     * @var array|null
     */
    protected $fields;

    /**
     * @var array
     */
    protected $commonFields = ['fields', 'filter', 'sort', 'page', 'include'];

    /**
     * @var int
     */
    protected $defaultPageSize = 25;

    /**
     * @var int
     */
    protected $bulkLimit = 100;

    /**
     * Get the available fields.
     * @param \JDT\Api\Payload $payload
     * @return \JDT\Api\Field\FieldList
     */
    abstract protected function getFields(Payload $payload):FieldList;

    /**
     * Run the endpoint code.
     * @return \JDT\Api\Response\Factory
     */
    abstract protected function run():Factory;

    /**
     * Get the bulk identifier key.
     * @return string|null
     */
    public function getBulkIdentifier():string
    {
        return '';
    }

    /**
     * Execute the api endpoint.
     * @param \JDT\Api\Payload $payload
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Payload $payload):JsonResponse
    {
        return $this->replaceExceptionHandler(function () use ($payload) {
            if (
                !empty($this->getBulkIdentifier()) &&
                $payload->has($this->getBulkIdentifier()) &&
                is_array($payload->get($this->getBulkIdentifier()))
            ) {
                return $this->executeBulk($payload);
            } else {
                return $this->executeSingle($payload);
            }
        });
    }

    /**
     * @param \JDT\Api\Payload $payload
     * @param bool $ignoreApiFields
     * @return array
     */
    public function buildRules(Payload $payload, $ignoreApiFields = false):array
    {
        $rules = [];

        if ($this->getBuiltFieldList($payload, $ignoreApiFields)->hasPayloadValidations()) {
            $rules = array_merge($rules, $this->getBuiltFieldList($payload, $ignoreApiFields)->getPayloadValidations());
        }

        $rules = array_merge($rules, [
            'fields.*' => 'in:' . implode(',', $this->getBuiltFieldList($payload, $ignoreApiFields)->getFieldKeys()),
            'page' => 'array',
            'page.number' => 'numeric|min:1',
            'page.size' => 'numeric|min:1|max:100',
        ]);

        if ($this->getBuiltFieldList($payload)->hasFilters()) {
            $rules = array_merge($rules, [
                'filter.*.field' => 'required|in:' . implode(',', $this->getBuiltFieldList($payload, $ignoreApiFields)->getFilterKeys()),
                'filter.*.type' => 'in:eq,neq,lt,lte,gt,gte,like,between,not_between,is_null,is_not_null,in,not_in',
                'filter.*.value' => 'required_unless:filter.*.type,in,not_in,is_null,is_not_null,between',
                'filter.*.values' => 'required_if:filter.*.type,in,not_in|array',
                'filter.*.from' => 'required_if:filter.*.type,between,not_between',
                'filter.*.to' => 'required_if:filter.*.type,between,not_between',
            ]);
        }

        if ($this->getBuiltFieldList($payload)->hasSort()) {
            $sortKeys = $this->getBuiltFieldList($payload)->getSortKeys();
            array_walk($sortKeys, function (&$field) {
                $field = preg_quote($field);
            });

            $rules = array_merge($rules, [
                'sort' => [
                    'regex:#^(-?(?:' . implode('|', $sortKeys) . '),?)+$#',
                ],
            ]);
        }

        return $rules;
    }

    /**
     * Execute a single endpoint.
     * @param \JDT\Api\Payload $payload
     * @return \Illuminate\Http\JsonResponse
     * @throws \JDT\Api\Exceptions\ValidationHttpException
     */
    protected function executeSingle(Payload $payload):JsonResponse
    {
        $fieldKeys = array_merge($this->getBuiltFieldList($payload)->getFieldKeys(), $this->commonFields);

        if ($this instanceof ModifyPayload) {
            $payload = $this->modifyPayload($payload);
        }

        $validation = $this->getValidation($payload);

        if ($validation->passes()) {
            if ($this instanceof ModifyPayloadPostValidation) {
                $payload = $this->modifyPayloadPostValidation($payload);
            }

            $this->payload = $payload->only($fieldKeys);

            $factory = $this->run();

            if ($this instanceof ModifyFactory) {
                $this->modifyFactory($factory);
            }

            $response = $factory->transform(
                $payload->get('include', ''),
                $payload->get('exclude', ''),
                $payload->get('fieldset', [])
            );

            if ($this instanceof ModifyResponse) {
                $this->modifyResponse($response);
            }

            return $response;
        } else {
            throw new ValidationHttpException($validation);
        }
    }

    /**
     * Execute a bulk api endpoint.
     * @param \JDT\Api\Payload $payload
     * @return \Illuminate\Http\JsonResponse
     */
    protected function executeBulk(Payload $payload):JsonResponse
    {
        $bulk = $payload->get($this->getBulkIdentifier());
        $bulkCount = count($bulk);

        if ($bulkCount > $this->bulkLimit) {
            return $this->response()->errorBadRequest('You can only process ' . $this->bulkLimit . ' at a time. Given ' . $bulkCount);
        }

        $return = [];
        foreach ($bulk as $key => $data) {
            $bulkPayload = $payload->pluck($this->getBulkIdentifier() . '.' . $key);

            try {
                $response = $this->executeSingle($bulkPayload)->getOriginalContent();
            } catch (\Exception $ex) {
                $response = app(Handler::class)->handle($ex)->getOriginalContent()['data'];
            }

            $return[$key] = $response;
        }

        return $this->response()->array(['data' => $return]);
    }

    /**
     * @return string
     */
    protected function getRunType():string
    {
        if (defined('static::RUN_TYPE')) {
            return static::RUN_TYPE;
        }

        return \JDT\Api\Contracts\ApiEndpoint::TYPE_READ_ALL;
    }

    /**
     * Get the payload.
     * @return \JDT\Api\Payload
     */
    protected function getPayload():Payload
    {
        return $this->payload;
    }

    /**
     * Modify the payload if needed before execution.
     * @param \JDT\Api\Payload $payload
     * @return \JDT\Api\Payload
     */
    protected function modifyPayload(Payload $payload):Payload
    {
        return $payload;
    }

    /**
     * Get the default page size.
     * @return int
     */
    protected function getDefaultPageSize():int
    {
        return $this->defaultPageSize;
    }

    /**
     * Get the built field list.
     * @param \JDT\Api\Payload $payload
     * @param bool $ignoreApiFields
     * @return \JDT\Api\Field\FieldList
     */
    protected function getBuiltFieldList(Payload $payload, $ignoreApiFields = false):FieldList
    {
        if ($this->fields === null) {
            $this->fields = $this->getFields($payload);
        }

        if ($ignoreApiFields) {
            $fields = new FieldList(
                array_filter(
                    $this->fields->getFields(),
                    function ($field) {
                        return ($field instanceof FieldApi) === false;
                    }
                )
            );

            return $fields;
        }

        return $this->fields;
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

    /**
     * Build the where clause based upon the payload.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildWhere(Builder $query):Builder
    {
        if ($this->payload->has('filter')) {
            foreach ($this->payload->get('filter') as $filter) {
                switch ($filter['type']) {
                    case 'eq':
                        $query->where($filter['field'], '=', $filter['value']);
                        break;

                    case 'neq':
                        $query->where($filter['field'], '<>', $filter['value']);
                        break;

                    case 'lt':
                        $query->where($filter['field'], '<', $filter['value']);
                        break;

                    case 'lte':
                        $query->where($filter['field'], '<=', $filter['value']);
                        break;

                    case 'gt':
                        $query->where($filter['field'], '>', $filter['value']);
                        break;

                    case 'gte':
                        $query->where($filter['field'], '>=', $filter['value']);
                        break;

                    case 'like':
                        $value = Str::contains($filter['value'], '%') ? $filter['value'] : '%' . $filter['value'] . '%';
                        $query->where($filter['field'], 'like', $value);
                        break;

                    case 'is_null':
                        $query->whereNull($filter['field']);
                        break;

                    case 'is_not_null':
                        $query->whereNotNull($filter['field']);
                        break;

                    case 'between':
                        $query->whereBetween($filter['field'], [$filter['from'], $filter['to']]);
                        break;

                    case 'not_between':
                        $query->whereNotBetween($filter['field'], [$filter['from'], $filter['to']]);
                        break;

                    case 'in':
                        $query->whereIn($filter['field'], $filter['values']);
                        break;

                    case 'not_in':
                        $query->whereNotIn($filter['field'], $filter['values']);
                        break;
                }
            }
        }

        return $query;
    }

    /**
     * Build the order by based upon the payload.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildSort(Builder $query):Builder
    {
        if ($this->payload->has('sort')) {
            $sort = explode(',', rtrim($this->payload->get('sort'), ','));

            foreach ($sort as $field) {
                $dir = 'asc';

                if (Str::startsWith($field, '-')) {
                    $dir = 'desc';
                    $field = ltrim($field, '-');
                }

                $query->orderBy($field, $dir);
            }
        }

        return $query;
    }

    /**
     * Build the include for loading relationships defined in a model.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildInclude(Builder $query):Builder
    {
        if ($this->payload->has('include')) {
            $include = explode(',', rtrim($this->payload->get('include'), ','));
            $query->with($include);
        }

        return $query;
    }

    /**
     * Build the offset and limit based upon the payload.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildOffset(Builder $query):Builder
    {
        $page = $this->payload->get('page.number', 1);
        $size = $this->payload->get('page.size', $this->getDefaultPageSize());
        $offset = ($page * $size) - $size;

        $query->skip($offset)->take($size);

        return $query;
    }
}

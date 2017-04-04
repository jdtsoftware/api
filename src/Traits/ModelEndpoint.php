<?php

declare(strict_types=1);

namespace JDT\Api\Traits;

use JDT\Api\Payload;
use Dingo\Api\Http\Response;
use JDT\Api\Contracts\ApiEndpoint;
use Illuminate\Database\Eloquent\Model;
use JDT\Api\Contracts\TransformerAwareModel;
use JDT\Api\Transformers\AbstractTransformer;
use JDT\Api\Transformers\DefaultModelTransformer;

trait ModelEndpoint
{
    use \JDT\Api\Traits\ApiEndpoint;

    /**
     * Get the model you want to query against.
     * @return \Illuminate\Database\Eloquent\Model
     */
    abstract protected function getModel():Model;

    /**
     * If this function returns false the action will not be triggered.
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    public function preEventAction(Model $model):bool
    {
        return true;
    }

    /**
     * Triggered once the action has been complete.
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function postEventAction(Model $model):Model
    {
        return $model;
    }

    /**
     * Return the key name to find on.
     * @return string
     */
    protected function getKeyName():string
    {
        return $this->getModel()->getKeyName();
    }

    /**
     * Run the endpoint code.
     * @return \Dingo\Api\Http\Response
     */
    protected function run():Response
    {
        switch ($this->getRunType()) {
            case ApiEndpoint::TYPE_READ_ALL:
                return $this->modelReadAll();

            case ApiEndpoint::TYPE_CREATE:
                return $this->modelCreate();

            case ApiEndpoint::TYPE_READ:
                return $this->modelRead();

            case ApiEndpoint::TYPE_UPDATE:
                return $this->modelUpdate();

            case ApiEndpoint::TYPE_DELETE:
                return $this->modelDelete();
        }

        return $this->response()->noContent();
    }

    /**
     * @return \JDT\Api\Transformers\AbstractTransformer
     */
    protected function getTransformer():AbstractTransformer
    {
        $model = $this->getModel();

        if ($this instanceof AbstractTransformer) {
            return $this;
        } elseif ($model instanceof TransformerAwareModel) {
            return $model->getTransformer();
        } else {
            return new DefaultModelTransformer();
        }
    }

    /**
     * Check the identifier exists and is present inside the payload.
     * @param \Illuminate\Database\Eloquent\Model $model
     * @throws \Exception
     */
    protected function checkIdentifierAndPayload(Model $model)
    {
        if ($this->getPayload()->has($this->getKeyName()) === false) {
            throw new \Exception('You must provide a model identifier that exists inside the field list.');
        }
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    protected function modelReadAll():Response
    {
        $model = $this->getModel();
        $payload = $this->getPayload();

        $query = $model->newQuery();

        // Including is now handled by the correct collection
        $this->buildWhere($query);
        $this->buildSort($query);

        $page = $payload->get('page.number', 1);
        $size = $payload->get('page.size', $this->getDefaultPageSize());

        $result = $query->paginate($size, $payload->get('fields', ['*']), 'page[number]', $page)
            ->appends([
                'filter' => $payload->get('filter'),
                'page' => [
                    'size' => $payload->get('page.size'),
                ],
                'sort' => $payload->get('sort'),
                'fields' => $payload->get('fields'),
            ]);

        return $this->response()->paginator($result, $this->getTransformer());
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    protected function modelRead():Response
    {
        $model = $this->getModel();

        return $this->actionRequest($model, function (Model $model, Payload $payload) {
            if (defined('static::INCLUDE_DELETED') && static::INCLUDE_DELETED === true) {
                $model->withTrashed();
            }

            return $model = $model->where($this->getKeyName(), '=', $payload->get($this->getKeyName()))->first();
        }, true);
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    protected function modelCreate():Response
    {
        $model = $this->getModel();

        return $this->actionRequest($model, function (Model $model, Payload $payload) {
            $payload = $payload->only($this->getBuiltFieldList($payload)->getPayloadValidationKeys());

            $model->fill($payload->getPayload())->save();

            return $model;
        });
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    protected function modelUpdate():Response
    {
        $model = $this->getModel();

        return $this->actionRequest($model, function (Model $model, Payload $payload) {
            $payload = $payload->only($this->getBuiltFieldList($payload)->getPayloadValidationKeys());
            $model = $model->where($this->getKeyName(), '=', $payload->get($this->getKeyName()))->first();

            $model->update($payload->getPayload());

            return $model;
        }, true);
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    protected function modelDelete():Response
    {
        $model = $this->getModel();

        $this->actionRequest($model, function (Model $model, Payload $payload) {
            $model = $model->where($this->getKeyName(), '=', $payload->get($this->getKeyName()))->first();

            $model->delete();

            return $model;
        }, true);

        $result = [
            'data' => [
                'acknowledged' => !$model->exists,
            ],
        ];

        return $this->response()->array($result);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param callable $callable
     * @param bool $identifierCheck
     * @return \Dingo\Api\Http\Response
     */
    protected function actionRequest(Model &$model, callable $callable, bool $identifierCheck = false):Response
    {
        if ($identifierCheck === true) {
            $this->checkIdentifierAndPayload($model);
        }

        if ($this->preEventAction($model) === true) {
            $model = $callable($model, $this->getPayload());
            $model = $this->postEventAction($model);

            return $this->response()->item($model, $this->getTransformer());
        }

        return $this->response()->errorBadRequest();
    }
}

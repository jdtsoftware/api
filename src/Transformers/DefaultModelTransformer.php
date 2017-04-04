<?php

namespace JDT\Api\Transformers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DefaultModelTransformer extends AbstractTransformer
{
    public function transformData(Model $model):array
    {
        $data = $model->toArray();

        foreach ($model->getDates() as $key) {
            if (!isset($data[$key])) {
                continue;
            }

            $date = $model->$key;

            if ($date instanceof Carbon) {
                $data[$key] = $this->transformDate($date);
            }
        }

        return $data;
    }
}

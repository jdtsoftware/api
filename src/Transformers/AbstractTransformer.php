<?php

namespace JDT\Api\Transformers;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;

/**
 * Class AbstractTransformer
 * @package App\Transformers
 */
abstract class AbstractTransformer extends TransformerAbstract
{
    protected $links = [];

    /**
     * @param $data
     * @return array
     */
    public function transform($data):array
    {
        if($data === null) {
            return [];
        }

        if (method_exists($this, 'transformData') === false) {
            throw new \RuntimeException('Your parent class must have the function transformData');
        }

        $transformed = $this->transformData($data);

        return $transformed;
    }

    /**
     * @param \Carbon\Carbon $date
     * @return array
     */
    protected function transformDate(Carbon $date):array
    {
        return $date->toRfc3339String();
    }
}
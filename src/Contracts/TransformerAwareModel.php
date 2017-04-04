<?php

namespace JDT\Api\Contracts;

use JDT\Api\Transformers\AbstractTransformer;

interface TransformerAwareModel
{
    /**
     * @return AbstractTransformer
     */
    public function getTransformer():AbstractTransformer;
}
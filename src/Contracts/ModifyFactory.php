<?php

namespace JDT\Api\Contracts;

use JDT\Api\Response\Factory;

interface ModifyFactory
{
    /**
     * @param \JDT\Api\Response\Factory $factory
     * @return mixed
     */
    public function modifyFactory(Factory $factory);
}

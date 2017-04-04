<?php

declare(strict_types=1);

namespace JDT\Api\Contracts;

use JDT\Api\Payload;
use Dingo\Api\Http\Response;

interface ApiEndpoint
{
    const TYPE_READ = 'READ';
    const TYPE_READ_ALL = 'READ_ALL';
    const TYPE_CREATE = 'CREATE';
    const TYPE_UPDATE = 'UPDATE';
    const TYPE_DELETE = 'DELETE';

    const INCLUDE_DELETED = false;

    /**
     * Get the bulk identifier key
     * @return string
     */
    public function getBulkIdentifier():string;

    /**
     * @param \JDT\Api\Payload $payload
     * @return array
     */
    public function buildRules(Payload $payload):array;

    /**
     * Execute the api endpoint
     * @param \JDT\Api\Payload $payload
     * @return \Dingo\Api\Http\Response
     */
    public function execute(Payload $payload):Response;
}
<?php

declare(strict_types=1);

namespace JDT\Api;

class Response
{
    public static function accepted($location = null, $content = null)
    {
        $response = new self($content);
        $response->setStatusCode(202);

        if (!is_null($location)) {
            $response->header('Location', $location);
        }

        return $response;
    }
}

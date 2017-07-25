<?php

declare(strict_types=1);

namespace JDT\Api;

class Response
{
    public static function accepted($location = null, $content = null)
    {
        $response = new Response($content);
        $response->setStatusCode(202);

        if (! is_null($location)) {
            $response->header('Location', $location);
        }

        return $response;

        fractal(null)
            ->
    }
}
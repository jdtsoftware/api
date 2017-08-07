<?php

declare(strict_types=1);

namespace JDT\Api\Response;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Factory
{
    protected $cookies;
    protected $data;
    protected $headers;
    protected $meta;
    protected $statusCode;
    protected $transformer;

    /**
     * Factory constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * @return \JDT\Api\Response\Factory
     */
    public function reset():self
    {
        $this->cookies = [];
        $this->data = null;
        $this->headers = [];
        $this->meta = [];
        $this->statusCode = 200;
        $this->transformer = null;

        return $this;
    }

    /**
     * @param string $includes
     * @param string $excludes
     * @param array $fieldsets
     * @return \Illuminate\Http\JsonResponse
     */
    public function transform(string $includes = '', string $excludes = '', array $fieldsets = []):JsonResponse
    {
        $response = fractal($this->data)
            ->parseIncludes($includes)
            ->parseExcludes($excludes)
            ->parseFieldsets($fieldsets)
            ->transformWith($this->transformer)
            ->addMeta($this->meta)
            ->respond($this->statusCode, $this->headers);

        foreach ($this->cookies as $cookie) {
            $response->cookie($cookie);
        }

        $response->original = $this->data;

        return $response;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Cookie|null $cookie
     * @return \JDT\Api\Response\Factory
     */
    public function cookies(Cookie $cookie = null):self
    {
        if ($cookie === null) {
            $this->cookies = [];
        } else {
            $this->cookies[] = $cookie;
        }

        return $this;
    }

    /**
     * @param $data
     * @return \JDT\Api\Response\Factory
     */
    public function data($data):self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param string|array|callable $key
     * @param mixed|null $value
     * @return \JDT\Api\Response\Factory
     */
    public function header($key, $value = null):self
    {
        if (is_array($key) || is_callable($key)) {
            $this->headers = $key;
        } else {
            $this->headers[$key] = $value;
        }

        return $this;
    }

    /**
     * @param $key
     * @param mixed|null $value
     * @return \JDT\Api\Response\Factory
     */
    public function meta($key, $value = null):self
    {
        if (is_array($key)) {
            $this->meta = $key;
        } else {
            $this->meta[$key] = $value;
        }

        return $this;
    }

    /**
     * @param int $statusCode
     * @return \JDT\Api\Response\Factory
     */
    public function statusCode(int $statusCode):self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * @param null|callable|\League\Fractal\TransformerAbstract $transformer
     * @return \JDT\Api\Response\Factory
     */
    public function transformer($transformer):self
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * @param string|null $location
     * @param null $content
     * @return \JDT\Api\Response\Factory
     */
    public function created(string $location = null, $content = null):self
    {
        return (new static())
            ->header('Location', $location)
            ->statusCode(201)
            ->transformer($this->blankTrandformer());
    }

    /**
     * @param string|null $location
     * @param null $content
     * @return \JDT\Api\Response\Factory
     */
    public function accepted(string $location = null, $content = null):self
    {
        return (new static())
            ->header('Location', $location)
            ->statusCode(202)
            ->transformer($this->blankTrandformer());
    }

    /**
     * @return \JDT\Api\Response\Factory
     */
    public function noContent():self
    {
        return (new static())
            ->statusCode(204)
            ->transformer($this->blankTrandformer());
    }

    /**
     * @param array $collection
     * @return \JDT\Api\Response\Factory
     */
    public function array(array $collection):self
    {
        return (new static())
            ->data($collection)
            ->transformer(function (array $data) {
                return $data;
            });
    }

    /**
     * @param $collection
     * @param $transformer
     * @return \JDT\Api\Response\Factory
     */
    public function collection($collection, $transformer):self
    {
        return (new static())
            ->data($collection)
            ->transformer($transformer);
    }

    /**
     * @param $item
     * @param $transformer
     * @return \JDT\Api\Response\Factory
     */
    public function item($item, $transformer):self
    {
        return (new static())
            ->data($item)
            ->transformer($transformer);
    }

    /**
     * Return an error response.
     * @param string $message
     * @param int    $statusCode
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public function error($message, $statusCode)
    {
        throw new HttpException($statusCode, $message);
    }

    /**
     * Return a 404 not found error.
     * @param string $message
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public function errorNotFound($message = 'Not Found')
    {
        $this->error($message, 404);
    }

    /**
     * Return a 400 bad request error.
     * @param string $message
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public function errorBadRequest($message = 'Bad Request')
    {
        $this->error($message, 400);
    }

    /**
     * Return a 403 forbidden error.
     * @param string $message
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public function errorForbidden($message = 'Forbidden')
    {
        $this->error($message, 403);
    }

    /**
     * Return a 500 internal server error.
     * @param string $message
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public function errorInternal($message = 'Internal Error')
    {
        $this->error($message, 500);
    }

    /**
     * Return a 401 unauthorized error.
     * @param string $message
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public function errorUnauthorized($message = 'Unauthorized')
    {
        $this->error($message, 401);
    }

    /**
     * Return a 405 method not allowed error.
     * @param string $message
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public function errorMethodNotAllowed($message = 'Method Not Allowed')
    {
        $this->error($message, 405);
    }

    /**
     * @return \Closure
     */
    protected function blankTrandformer():\Closure
    {
        return function () {
        };
    }
}

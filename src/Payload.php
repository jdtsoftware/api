<?php

declare(strict_types=1);

namespace JDT\Api;

use Illuminate\Support\Arr;

/**
 * Class Payload
 * @package JDT\Api
 */
class Payload
{
    protected $payload;

    /**
     * Payload constructor.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Get payload
     * @return array
     */
    public function getPayload():array
    {
        return $this->payload;
    }

    /**
     * Remove all unwanted columns from payload
     * @param array $keys
     * @return \JDT\Api\Payload
     */
    public function only(array $keys):self
    {
        $this->payload = Arr::only($this->payload, $keys);
        return $this;
    }

    /**
     * Return a new payload with the given keys
     * @param string $key
     * @return \JDT\Api\Payload
     */
    public function pluck(string $key):self
    {
        return new static(Arr::get($this->payload, $key, []));
    }

    /**
     * Has the payload got the correct key
     * @param string $key
     * @return bool
     */
    public function has(string $key):bool
    {
        return Arr::has($this->payload, $key);
    }

    /**
     * Get the data from the payload
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->payload, $key, $default);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return \JDT\Api\Payload
     */
    public function set(string $key, $value):self
    {
        Arr::set($this->payload, $key, $value);
        return $this;
    }
}
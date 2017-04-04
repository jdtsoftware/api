<?php

namespace JDT\Api;

use Dingo\Api\Routing\Helpers;
use Dingo\Api\Routing\UrlGenerator;
use Illuminate\Contracts\Auth\Authenticatable;

class InternalRequest
{
    use Helpers;

    protected $attach;
    protected $be;
    protected $json;
    protected $on;
    protected $once;
    protected $urlGenerator;
    protected $version;
    protected $with;

    /**
     * InternalRequest constructor.
     * @param \Dingo\Api\Routing\UrlGenerator $urlGenerator
     */
    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
        $this->reset();
    }

    public function reset():self
    {
        $this->attach = null;
        $this->json = null;
        $this->on = null;
        $this->version = config('api.version');
        $this->with = null;

        if ($this->once === true) {
            $this->be = null;
            $this->once = false;
        }

        return $this;
    }

    /**
     * @param array $attach
     * @return \JDT\Api\InternalRequest
     */
    public function attach(array $attach):self
    {
        $this->attach = $attach;

        return $this;
    }

    /**
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @return \JDT\Api\InternalRequest
     */
    public function be(Authenticatable $user):self
    {
        $this->be = $user;

        return $this;
    }

    /**
     * @param array $json
     * @return \JDT\Api\InternalRequest
     */
    public function json(array $json):self
    {
        $this->json = $json;

        return $this;
    }

    /**
     * @param string $on
     * @return \JDT\Api\InternalRequest
     */
    public function on(string $on):self
    {
        $this->on = $on;

        return $this;
    }

    /**
     * @return \JDT\Api\InternalRequest
     */
    public function once():self
    {
        $this->once = true;

        return $this;
    }

    /**
     * @param string $version
     * @return \JDT\Api\InternalRequest
     */
    public function version(string $version):self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @param array $with
     * @return \JDT\Api\InternalRequest
     */
    public function with(array $with):self
    {
        $this->with = $with;

        return $this;
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function delete(string $routeName, array $params = []):InternalResult
    {
        return $this->go('delete', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function get(string $routeName, array $params = []):InternalResult
    {
        return $this->go('get', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function patch(string $routeName, array $params = []):InternalResult
    {
        return $this->go('patch', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function post(string $routeName, array $params = []):InternalResult
    {
        return $this->go('post', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function put(string $routeName, array $params = []):InternalResult
    {
        return $this->go('put', $routeName, $params);
    }

    /**
     * @param string $method
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    protected function go(string $method, string $routeName, array $params = []):InternalResult
    {
        $allowedMethods = [
            'delete',
            'get',
            'patch',
            'post',
            'put',
        ];

        if (!in_array($method, $allowedMethods)) {
            throw new \BadMethodCallException('Unknown method "' . $method . '"');
        }

        $route = $this->urlGenerator->version($this->version)->route($routeName, $params, false);

        $api = $this->api()->version($this->version)->raw();

        if ($this->attach !== null) {
            $api->attach($this->attach);
        }

        if ($this->be !== null) {
            $api->be($this->be);
        }

        if ($this->json !== null) {
            $api->json($this->json);
        }

        if ($this->on !== null) {
            $api->on($this->on);
        }

        if ($this->once === true) {
            $api->once();
        }

        if ($this->with !== null) {
            $api->with($this->with);
        }

        $result = $api->{$method}($route);

        $this->reset();

        return new InternalResult(json_decode($result->getContent(), true), $result->getOriginalContent());
    }
}
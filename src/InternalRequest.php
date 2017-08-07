<?php

namespace JDT\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Laravel\Passport\Passport;
use Illuminate\Http\UploadedFile;
use JDT\Api\Http\InternalApiRequest;
use Illuminate\Filesystem\Filesystem;
use Laravel\Passport\ApiTokenCookieFactory;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Contracts\Container\Container;
use JDT\Api\Exceptions\InternalHttpException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Request as RequestFacade;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InternalRequest
{
    /**
     * Setup Params.
     */
    protected $container;
    protected $cookieFactory;
    protected $fileSystem;
    protected $requestStack;
    protected $routeStack;

    /**
     * Request Params.
     */
    protected $attach;
    protected $be;
    protected $cookies;
    protected $headers;
    protected $content;
    protected $on;
    protected $once = false;
    protected $with;

    /**
     * InternalRequest constructor.
     * @param \Illuminate\Contracts\Container\Container $container
     * @param \Illuminate\Filesystem\Filesystem $fileSystem
     * @param \Illuminate\Routing\Router $router
     * @param \Laravel\Passport\ApiTokenCookieFactory $cookieFactory
     */
    public function __construct(Container $container, Filesystem $fileSystem, Router $router, ApiTokenCookieFactory $cookieFactory)
    {
        $this->container = $container;
        $this->fileSystem = $fileSystem;
        $this->router = $router;
        $this->cookieFactory = $cookieFactory;

        $this->reset();
        $this->setupRequestStack();
    }

    /**
     * @return \JDT\Api\InternalRequest
     */
    public function reset():self
    {
        $this->attach = [];
        $this->cookies = [];
        $this->headers = [];
        $this->content = null;
        $this->on = null;
        $this->with = [];

        if ($this->once === true) {
            $this->be = null;
            $this->once = false;
        }

        return $this;
    }

    /**
     * Setup the request stack by grabbing the initial request.
     */
    protected function setupRequestStack()
    {
        $this->requestStack[] = $this->container['request'];
    }

    /**
     * @param array $attach
     * @return \JDT\Api\InternalRequest
     */
    public function attach(array $files)
    {
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $file = new UploadedFile($file['path'], basename($file['path']), $file['mime'], $file['size']);
            } elseif (is_string($file)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);

                $file = new UploadedFile($file, basename($file), finfo_file($finfo, $file), $this->fileSystem->size($file));
            } elseif (!$file instanceof UploadedFile) {
                continue;
            }

            $this->attach[$key] = $file;
        }

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
     * @param \Symfony\Component\HttpFoundation\Cookie $cookie
     * @return \JDT\Api\InternalRequest
     */
    public function cookie(Cookie $cookie):self
    {
        $value = $cookie->getValue();

        if ($cookie->getName() === Passport::cookie()) {
            $value = encrypt($value);
        }

        $this->cookies[$cookie->getName()] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @return \JDT\Api\InternalRequest
     */
    public function header(string $key, string $value):self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * @param array|string $json
     * @return \JDT\Api\InternalRequest
     */
    public function json($json):self
    {
        if (is_array($json)) {
            $json = json_encode($json);
        }

        $this->content = $content;

        return $this->header('Content-Type', 'application/json');
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
     * @param string|array $with
     * @return \JDT\Api\InternalRequest
     */
    public function with($with):self
    {
        $this->with = array_merge($this->with, is_array($with) ? $with : func_get_args());

        return $this;
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function delete(string $routeName, array $params = []):InternalResult
    {
        return $this->queueRequest('delete', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function get(string $routeName, array $params = []):InternalResult
    {
        return $this->queueRequest('get', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function patch(string $routeName, array $params = []):InternalResult
    {
        return $this->queueRequest('patch', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function post(string $routeName, array $params = []):InternalResult
    {
        return $this->queueRequest('post', $routeName, $params);
    }

    /**
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    public function put(string $routeName, array $params = []):InternalResult
    {
        return $this->queueRequest('put', $routeName, $params);
    }

    /**
     * @param string $method
     * @param string $routeName
     * @param array $params
     * @return \JDT\Api\InternalResult
     */
    protected function queueRequest(string $method, string $routeName, array $params = []):InternalResult
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

        $uri = route($routeName, $params, false);

        // Sometimes after setting the initial request another request might be made prior to
        // internally dispatching an API request. We need to capture this request as well
        // and add it to the request stack as it has become the new parent request to
        // this internal request. This will generally occur during tests when
        // using the crawler to navigate pages that also make internal
        // requests.
        if (end($this->requestStack) != $this->container['request']) {
            $this->requestStack[] = $this->container['request'];
        }

        $this->requestStack[] = $request = $this->createRequest($method, $uri, $params);
        $result = $this->dispatch($request);

        if (empty($result->getContent())) {
            $content = [];
        } else {
            $content = json_decode($result->getContent(), true);
        }

        return new InternalResult($content, $result->getOriginalContent(), $result->getStatusCode());
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $params
     * @return InternalApiRequest
     */
    protected function createRequest(string $method, string $uri, array $params = [])
    {
        $parameters = array_merge($this->with, $params);

        // If the URI does not have a scheme then we can assume that there it is not an
        // absolute URI, in this case we'll prefix the root requests path to the URI.
        $rootUrl = $this->getRootRequest()->root();
        if ((!parse_url($uri, PHP_URL_SCHEME)) && parse_url($rootUrl) !== false) {
            $uri = rtrim($rootUrl, '/') . '/' . ltrim($uri, '/');
        }

        if ($this->be !== null) {
            $token = uniqid();
            $this->cookie($this->cookieFactory->make($this->be->getKey(), $token));
            $this->header('X-CSRF-TOKEN', $token);
        }

        $request = InternalApiRequest::create(
            $uri,
            $method,
            $parameters,
            $this->cookies,
            $this->attach,
            $this->container['request']->server->all(),
            $this->content
        );

        $request->headers->set('host', $this->on);

        foreach ($this->headers as $header => $value) {
            $request->headers->set($header, $value);
        }

        $request->headers->set('accept', $this->getAcceptHeader());

        return $request;
    }

    /**
     * Build the "Accept" header.
     *
     * @return string
     */
    protected function getAcceptHeader():string
    {
        return 'application/vnd.jb.v1+json';
    }

    /**
     * @param \JDT\Api\Http\InternalApiRequest $request
     * @return \Illuminate\Http\Response|mixed
     * @throws InternalHttpException|HttpExceptionInterface
     */
    protected function dispatch(InternalApiRequest $request)
    {
        $this->routeStack[] = $this->router->getCurrentRoute();
        $this->clearCachedFacadeInstance();

        $exceptionHandler = $this->container->make(ExceptionHandler::class);
        $this->container->offsetUnset(ExceptionHandler::class);

        try {
            $this->container->instance('request', $request);
            $response = $this->router->dispatch($request);

            if (!$response->isSuccessful() && !$response->isRedirection()) {
                throw new InternalHttpException($response);
            }
        } finally {
            $this->container->instance(ExceptionHandler::class, $exceptionHandler);
            $this->refreshRequestStack();
        }

        return $response;
    }

    /**
     * Refresh the request stack.
     *
     * This is done by resetting the authentication, popping
     * the last request from the stack, replacing the input,
     * and resetting the version and parameters.
     *
     * @return void
     */
    protected function refreshRequestStack()
    {
        if ($route = array_pop($this->routeStack)) {
            $this->router->setCurrentRoute($route);
        }

        $this->replaceRequestInstance();
        $this->clearCachedFacadeInstance();
        $this->reset();
    }

    /**
     * Replace the request instance with the previous request instance.
     *
     * @return void
     */
    protected function replaceRequestInstance()
    {
        array_pop($this->requestStack);

        $request = end($this->requestStack);
        $this->container->instance('request', $request);
        $this->router->setCurrentRequest($request);
    }

    /**
     * Clear the cached facade instance.
     *
     * @return void
     */
    protected function clearCachedFacadeInstance()
    {
        // Facades cache the resolved instance so we need to clear out the
        // request instance that may have been cached. Otherwise we'll
        // may get unexpected results.
        RequestFacade::clearResolvedInstance('request');
    }

    /**
     * Get the root request instance.
     *
     * @return \Illuminate\Http\Request
     */
    protected function getRootRequest():Request
    {
        return reset($this->requestStack);
    }
}

<?php

namespace Swilen\Http;

use Swilen\Http\Common\SupportRequest;
use Swilen\Http\Component\FileHunt;
use Swilen\Http\Component\HeaderHunt;
use Swilen\Http\Component\InputHunt;
use Swilen\Http\Component\ServerHunt;
use Swilen\Http\Exception\HttpNotOverridableMethodException;
use Swilen\Shared\Support\Json;
use Swilen\Shared\Support\Str;

class Request extends SupportRequest implements \ArrayAccess
{
    /**
     * Http request headers collections.
     *
     * @var \Swilen\Http\Component\HeaderHunt
     */
    public $headers;

    /**
     * Http server variables collections.
     *
     * @var \Swilen\Http\Component\ServerHunt
     */
    public $server;

    /**
     * Http files collections.
     *
     * @var \Swilen\Http\Component\FileHunt
     */
    public $files;

    /**
     * Http params collections via $_POST.
     *
     * @var \Swilen\Http\Component\InputHunt
     */
    public $request;

    /**
     * Http params collections via $_GET.
     *
     * @var \Swilen\Http\Component\InputHunt
     */
    public $query;

    /**
     * The content of request body decoded as json.
     *
     * @var \Swilen\Http\Component\InputHunt|null
     */
    public $json;

    /**
     * The binary data from the request.
     *
     * @var string|resource
     */
    protected $body;

    /**
     * Http current request method.
     *
     * @var string
     */
    protected $method;

    /**
     * Current http request uri.
     *
     * @var string
     */
    protected $uri;

    /**
     * Current http request path info.
     *
     * @var string
     */
    protected $pathInfo;

    /**
     * Http body params accepted for override.
     *
     * @var string[]
     */
    protected $acceptMethodOverrides = ['DELETE', 'PUT'];

    /**
     * Http current user logged prvided by token.
     *
     * @var mixed
     */
    protected $user;

    /**
     * Create new request instance from incoming request.
     *
     * @param array                $server  The server variables collection
     * @param array                $headers The request headers collection
     * @param array                $files   The request files collection
     * @param array                $request The request variables sending from client collection
     * @param array                $query   The request query params or send from client into form
     * @param string|resource|null $body    The raw body data
     *
     * @return void
     */
    public function __construct(array $server = [], array $files = [], array $request = [], array $query = [], $body = null)
    {
        $this->server  = new ServerHunt($server);
        $this->headers = new HeaderHunt($this->server->headers());
        $this->files   = new FileHunt($files);
        $this->request = new InputHunt($request);
        $this->query   = new InputHunt($query);

        $this->body = $body;
    }

    /**
     * Creates a new request with values from PHP's super globals.
     *
     * @return static
     */
    public static function create()
    {
        return static::createFromGlobals();
    }

    /**
     * Creates a new request with values from PHP's super globals.
     *
     * @return static
     */
    public static function createFromGlobals()
    {
        $request = new static($_SERVER, $_FILES, $_POST, $_GET);

        if (
            mb_strpos($request->headers->get('Content-Type', ''), 'application/x-www-form-urlencoded') === 0 &&
            in_array($request->getRealMethod(), ['PUT', 'DELETE', 'PATCH'])
        ) {
            parse_str($request->getBody(), $data);
            $request->request = new InputHunt($data);
        }

        return $request;
    }

    /**
     * Set method for the request.
     *
     * @return $this
     */
    public function withMethod(string $method)
    {
        $this->method = strtoupper($method);
        $this->server->set('REQUEST_METHOD', $this->method);

        return $this;
    }

    /**
     * Get request method.
     *
     * @return string
     *
     * @see getRealMethod()
     */
    public function getMethod()
    {
        if ($this->method != null) {
            return $this->method;
        }

        $this->method = strtoupper($this->server->filter('REQUEST_METHOD', 'GET'));

        if ($this->method !== 'POST') {
            return $this->method;
        }

        $method = $this->server->filter('HTTP_X_METHOD_OVERRIDE')
            ?: $this->server->filter('HTTP_X_HTTP_METHOD_OVERRIDE');

        if (empty($method) || !is_string($method)) {
            return $this->method;
        }

        $method = strtoupper(trim($method));

        if (!preg_match('/^[A-Z]+$/', $method)) {
            return $this->method;
        }

        if (in_array($method, $this->acceptMethodOverrides, true)) {
            return $this->method = $method;
        }

        throw new HttpNotOverridableMethodException(sprintf('%s non-overwritable method', $method), 400);
    }

    /**
     * Gets the "real" request method.
     *
     * @return string
     *
     * @see getMethod()
     */
    public function getRealMethod()
    {
        return strtoupper($this->server->filter('REQUEST_METHOD', 'GET'));
    }

    /**
     * Checks if the request method is of specified type.
     *
     * @return bool
     */
    public function isMethod(string $method)
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Return current request path info.
     *
     * @return string
     */
    public function getPathInfo()
    {
        if ($this->pathInfo !== null) {
            return $this->pathInfo;
        }

        return $this->pathInfo = Str::trimPath(preg_replace('/\\?.*/', '', $this->filteredRequestUri()));
    }

    /**
     * Returns REQUEST_URI replaced with app base uri.
     *
     * @return string
     */
    private function filteredRequestUri()
    {
        if (isset($_ENV['APP_BASE_URI']) && !empty($base = $_ENV['APP_BASE_URI'])) {
            return $this->uri = preg_replace('#^'.$base.'#', '', $this->server->get('REQUEST_URI'));
        }

        return $this->uri = $this->server->get('REQUEST_URI');
    }

    /**
     * Check if uri contains query string.
     *
     * @return bool
     */
    public function hasQueryString()
    {
        return Str::contains($this->server->get('REQUEST_URI'), '?');
    }

    /**
     * Determine and decode content type request is json, return request content type is not json.
     *
     * @return \Swilen\Http\Component\InputHunt
     */
    public function getInputSource()
    {
        if ($this->isJsonRequest()) {
            return $this->morphInputSource();
        }

        return in_array($this->getRealMethod(), ['GET', 'HEAD']) ? $this->query : $this->request;
    }

    /**
     * Morph the request body.
     *
     * @return \Swilen\Http\Component\InputHunt
     */
    public function morphInputSource()
    {
        if ($this->json === null) {
            $content = Json::from($this->getBody())->decode(true);

            return $this->json = new InputHunt($content);
        }

        return $this->json;
    }

    /**
     * Set user to current request.
     *
     * @param object|array $user
     *
     * @return $this
     */
    public function withUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user from request.
     *
     * @return array|object
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Retrieve Bearer Token from 'Authorization' header.
     *
     * @return string|null
     */
    public function bearerToken()
    {
        if (preg_match('/Bearer\s(\S+)/', $this->headers->get('Authorization', ''), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get current body content request.
     *
     * @return resource|string|false|null
     */
    public function getBody()
    {
        if (\is_resource($this->body)) {
            rewind($this->body);

            return stream_get_contents($this->body);
        }

        if ($this->body === null || $this->body === false) {
            $this->body = file_get_contents('php://input');
        }

        return $this->body;
    }

    /**
     * Checks whether or not the method is safe.
     *
     * @return bool
     */
    final public function isMethodSafe()
    {
        return \in_array($this->getMethod(), ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true);
    }

    /**
     * Determine if request content type is json.
     *
     * @return bool
     */
    public function isJsonRequest()
    {
        return Str::contains($this->headers->get('Content-Type', ''), ['/json', '+json']);
    }

    /**
     * Determine if request content type is form.
     *
     * @return bool
     */
    public function isFormRequest()
    {
        return in_array($this->headers->get('Content-Type'), $this->requestMimeTypes['form']);
    }

    /**
     * Get all of the input and files for the request.
     *
     * @return array
     */
    public function all()
    {
        return array_replace_recursive(
            $this->getInputSource()->all() + $this->query->all(), $this->files->all(),
        );
    }

    /**
     * Get input value from input ($_POST or php://input) source.
     *
     * @param string|int $key
     * @param mixed      $default
     *
     * @return mixed
     */
    public function input($key, $default = null)
    {
        return $this->getInputSource()->get($key, $default);
    }

    /**
     * Get query value from query ($_GET) collection.
     *
     * @param string|int $key
     * @param mixed      $default
     *
     * @return mixed
     */
    public function query($key, $default = null)
    {
        return $this->query->get($key, $default);
    }

    /**
     * Get server value from server ($_SERVER) collection.
     *
     * @param string|int $key
     * @param mixed      $default
     *
     * @return mixed
     */
    public function server($key, $default = null)
    {
        return $this->server->get($key, $default);
    }

    /**
     * Get file(s) from UploadedFiles collection.
     *
     * @param string $filename The filename
     *
     * @return \Swilen\Http\Component\File\UploadedFile|\Swilen\Http\Component\File\UploadedFile[]|null
     */
    public function file(string $filename)
    {
        return $this->files->get($filename);
    }

    /**
     * Verify given filename is exists in collection.
     *
     * @param string $filename
     *
     * @return bool
     */
    public function hasFile(string $filename)
    {
        return $this->files->has($filename);
    }

    /**
     * Verify given filename is exists in collection.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasHeader(string $name)
    {
        return $this->headers->has($name);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->getInputSource()->has($offset);
    }

    /**
     * Get the value at the given offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->all()[$offset];
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->getInputSource()->set($offset, $value);
    }

    /**
     * Remove the value at the given offset.
     *
     * @param string $offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->getInputSource()->remove($offset);
    }

    /**
     * Check if an input element is set on the request.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Remove the value at the given offset.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Set value to input source.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    /**
     * Get an input element from the request.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->offsetGet($key);
    }
}

<?php

namespace Swilen\Arthropod;

use Swilen\Arthropod\Contract\ExceptionHandler;
use Swilen\Container\Container;
use Swilen\Http\Request;
use Swilen\Petiole\Facade;
use Swilen\Pipeline\Pipeline;
use Swilen\Routing\RoutingServiceProvider;
use Swilen\Shared\Arthropod\Application as ArthropodApplication;
use Swilen\Shared\Arthropod\HttpApplication;

class Application extends Container implements ArthropodApplication, HttpApplication
{
    /**
     * The Swilen current version.
     *
     * @var string
     */
    public const VERSION = '0.7.2-alpha';

    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    protected $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has booted.
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The application middlewares.
     *
     * @var string[]
     */
    protected $middleware = [
        \Swilen\Arthropod\Middleware\CorsMiddleware::class,
    ];

    /**
     * The bootable services collection.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        \Swilen\Arthropod\Bootable\EnvironmentVars::class,
        \Swilen\Arthropod\Bootable\Configuration::class,
        \Swilen\Arthropod\Bootable\ExceptionsHandler::class,
        \Swilen\Arthropod\Bootable\Facades::class,
        \Swilen\Arthropod\Bootable\Providers::class,
    ];

    /**
     * Resolved service provider collection for boot.
     *
     * @var \Swilen\Petiole\ServiceProvider[]
     */
    protected $serviceProviders = [];

    /**
     * Collection of service providers as registered.
     *
     * @var array<string, bool>
     */
    protected $serviceProvidersRegistered = [];

    /**
     * The application base path.
     *
     * @var string
     */
    protected $basePath;

    /**
     * The application app path.
     *
     * @var string
     */
    protected $appPath = 'app';

    /**
     * The application config path.
     *
     * @var string
     */
    protected $configPath;

    /**
     * The application base uri.
     *
     * @var string
     */
    protected $appUri;

    /**
     * The application environment path.
     *
     * @var string
     */
    protected $environmentPath;

    /**
     * The application environment file.
     *
     * @var string
     */
    protected $environmentFile;

    /**
     * Create http aplication instance.
     *
     * @param string $path define base path for your application
     *
     * @return void
     */
    public function __construct(string $path = '')
    {
        $this->defineBasePath($path);
        $this->registerBaseBindings();
        $this->registerServiceProviders();
        $this->registerCoreContainerAliases();
    }

    /**
     * Return version of Swilen.
     *
     * @return string
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * Register base container bindings.
     *
     * @return void
     */
    private function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
    }

    /**
     * Register base service providers.
     *
     * @return void
     */
    private function registerServiceProviders()
    {
        $this->register(new RoutingServiceProvider($this));
    }

    /**
     * Create and define Application base path.
     *
     * @param string $basePath
     *
     * @return $this
     */
    private function defineBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->registerApplicationPaths();

        return $this;
    }

    /**
     * Register application paths.
     *
     * @return void
     */
    private function registerApplicationPaths()
    {
        $this->instance('path', $this->basePath());
        $this->instance('path.app', $this->appPath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.config', $this->configPath());
    }

    /**
     * Register application path parts.
     *
     * @param string $path
     *
     * @return string
     */
    public function basePath(string $path = '')
    {
        return $this->basePath.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Use application path part.
     *
     * @param string $path
     *
     * @return $this
     */
    public function useBasePath(string $path = '')
    {
        $this->basePath = $path;

        $this->instance('path', $path);

        return $this;
    }

    /**
     * Register application path parts.
     *
     * @param string $path
     *
     * @return string
     */
    public function appPath(string $path = '')
    {
        return $this->basePath($this->appPath).($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Use application path part.
     *
     * @param string $path
     *
     * @return $this
     */
    public function useAppPath(string $path = '')
    {
        $this->appPath = $path;

        $this->instance('path.app', $path);

        return $this;
    }

    /**
     * Register application path parts.
     *
     * @param string $path
     *
     * @return string
     */
    public function configPath(string $path = '')
    {
        return $this->appPath($path ?: 'app.config.php');
    }

    /**
     * Use application path part.
     *
     * @param string $path
     *
     * @return $this
     */
    public function useConfigPath(string $path = '')
    {
        $this->configPath = $path;

        $this->instance('path.config', $path);

        return $this;
    }

    /**
     * Return application base uri.
     *
     * @param string $path
     *
     * @return string
     */
    public function appUri(string $path = '')
    {
        return $this->appUri.($path ? '/'.$path : '');
    }

    /**
     * Replace application uri provided from param.
     *
     * @param string $uri
     *
     * @return $this
     */
    public function useAppUri(string $path = '')
    {
        $this->appUri = $path;

        return $this;
    }

    /**
     * Return application base uri.
     *
     * @param string $path
     *
     * @return string
     */
    public function storagePath(string $path = '')
    {
        return $this->appPath('storage').($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Retrive environment file path.
     *
     * @return string
     */
    public function environmentPath()
    {
        return $this->environmentPath ?? $this->basePath();
    }

    /**
     * Use user defined environment file path.
     *
     * @param string $path
     *
     * @return $this
     */
    public function useEnvironmentPath(string $path)
    {
        $this->environmentPath = $path;

        return $this;
    }

    /**
     * Retrive environment filename.
     *
     * @return string
     */
    public function environmentFile()
    {
        return $this->environmentFile ?? '.env';
    }

    /**
     * Use user defined environment filename.
     *
     * @param string $filename
     *
     * @return $this
     */
    public function useEnvironmentFile(string $filename)
    {
        $this->environmentFile = $filename;

        return $this;
    }

    /**
     * Set application environment.
     *
     * @param string $env The Environment valid `production|development|test`
     *
     * @return $this
     */
    public function useEnvironment(string $env)
    {
        $this->instance('env', $env);
        
        return $this;
    }

    /**
     * Register all of the configured providers.
     *
     * @return void
     */
    public function registerProviders()
    {
        $repository = new ProviderRepository($this);

        $repository->load($this->make('config')->get('providers', []));
    }

    /**
     * Initial register service providers.
     *
     * @param \Swilen\Petiole\ServiceProvider $provider
     *
     * @return \Swilen\Petiole\ServiceProvider
     */
    public function register($provider)
    {
        if ($this->hasProviderBeenRegistered($provider)) {
            return;
        }

        $provider = $this->createServiceProvider($provider);

        $provider->register();

        $this->markProviderAsRegistered($provider);

        return $provider;
    }

    /**
     * Normalize if provider is string for create new instance.
     *
     * @param \Swilen\Petiole\ServiceProvider|string $provider
     *
     * @return \Swilen\Petiole\ServiceProvider
     */
    protected function createServiceProvider($provider)
    {
        return is_string($provider) ? new $provider($this) : $provider;
    }

    /**
     * Mark service provider as registered.
     *
     * @param \Swilen\Petiole\ServiceProvider $provider
     *
     * @return void
     */
    protected function markProviderAsRegistered($provider)
    {
        $this->serviceProviders[] = $provider;

        $this->serviceProvidersRegistered[get_class($provider)] = true;
    }

    /**
     * Determine if service provider as been registered.
     *
     * @param \Swilen\Petiole\ServiceProvider|string $provider
     *
     * @return bool
     */
    protected function hasProviderBeenRegistered($provider)
    {
        $provider = is_object($provider) ? get_class($provider) : $provider;

        return isset($this->serviceProvidersRegistered[$provider])
            && $this->serviceProvidersRegistered[$provider] === true;
    }

    /**
     * Boot the application with packages that implement the bootstrap contract.
     *
     * @return void
     */
    protected function bootstrap()
    {
        if ($this->hasBeenBootstrapped()) {
            return;
        }

        foreach ($this->bootstrappers as $bootstrap) {
            $this->make($bootstrap)->bootstrap($this);
        }

        $this->hasBeenBootstrapped = true;
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped()
    {
        return $this->hasBeenBootstrapped === true;
    }

    /**
     * Boot application with boot method into service containers.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isBooted()) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $this->call([$provider, 'boot']);
            }
        }

        $this->booted = true;
    }

    /**
     * Verify if the application is booted.
     *
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted === true;
    }

    /**
     * Indicates the application is development mode.
     *
     * @return bool
     */
    public function isDevelopmentMode()
    {
        return (bool) $this->has('env') && $this->make('env') === 'development';
    }

    /**
     * Indicates the application is debug mode.
     *
     * @return bool
     */
    public function isDebugMode()
    {
        return (bool) $this->make('config')->get('app.debug', true);
    }

    /**
     * Handle the incoming request and send it to the router.
     *
     * @param \Swilen\Http\Request $request
     *
     * @return \Swilen\Http\Response
     */
    public function handle(Request $request)
    {
        try {
            $response = $this->dispatchRequestThroughRouter($request);
        } catch (\Throwable $e) {
            $this->reportException($e);

            $response = $this->renderException($e);
        }

        return $response;
    }

    /**
     * Handle for dispatch request for route.
     *
     * @param \Swilen\Http\Request $request
     *
     * @return \Swilen\Http\Response
     */
    protected function dispatchRequestThroughRouter(Request $request)
    {
        $this->instance('request', $request);

        Facade::flushFacadeInstance('request');

        $this->bootstrap();

        return (new Pipeline($this))
            ->from($request)
            ->through($this->middleware)
            ->then(function ($request) {
                return $this['router']->dispatch($request);
            });
    }

    /**
     * Render given Exception to response.
     *
     * @param \Throwable $e
     *
     * @return \Swilen\Http\Response
     */
    protected function renderException(\Throwable $e)
    {
        return $this->make(ExceptionHandler::class)->render($e);
    }

    /**
     * Report given Exception and write log.
     *
     * @param \Throwable $e
     *
     * @return void
     */
    protected function reportException(\Throwable $e)
    {
        $this->make(ExceptionHandler::class)->report($e);
    }

    /**
     * Register core aliases into container.
     *
     * @return void
     */
    protected function registerCoreContainerAliases()
    {
        foreach ([
            'app' => \Swilen\Arthropod\Application::class,
            'request' => \Swilen\Http\Request::class,
            'response' => \Swilen\Http\Response::class,
        ] as $key => $value) {
            $this->alias($key, $value);
        }
    }

    /**
     * Flush the application of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush()
    {
        $this->serviceProviders           = [];
        $this->serviceProvidersRegistered = [];

        parent::flush();
    }
}

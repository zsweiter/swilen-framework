<?php

namespace Swilen\Arthropod\Bootable;

use Swilen\Arthropod\Contract\BootableService;
use Swilen\Arthropod\Env;
use Swilen\Shared\Arthropod\Application;

class EnvironmentVars implements BootableService
{
    /**
     * The application instance.
     *
     * @var \Swilen\Shared\Arthropod\Application
     */
    protected $app;

    /**
     * Env instance if defined or null by default.
     *
     * @var \Swilen\Arthropod\Env|object|null
     */
    protected static $instance;

    /**
     * Bootstrap application environments variables.
     *
     * @param \Swilen\Shared\Arthropod\Application $app
     *
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $this->app = $app;

        $this->loadEnvironment();
    }

    /**
     * Create enviroment instance from factory.
     *
     * @return void
     */
    protected function loadEnvironment()
    {
        if (!is_object(static::$instance)) {
            Env::createFrom($this->app->environmentPath())->config([
                'file' => $this->app->environmentFile(),
            ])->load();
        }
    }

    /**
     * Use custom enviromment instance from factory function.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public static function use(\Closure $callback)
    {
        if (!$instance = $callback()) {
            throw new \TypeError('The callback expect a env object instance. Use env library, see https://github.com/vlucas/phpdotenv');
        }

        static::$instance = $instance;
    }
}

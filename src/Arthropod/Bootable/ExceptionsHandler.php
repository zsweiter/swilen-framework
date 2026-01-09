<?php

namespace Swilen\Arthropod\Bootable;

use Swilen\Arthropod\Contract\BootableService;
use Swilen\Arthropod\Contract\ExceptionHandler;
use Swilen\Arthropod\TypeErrors\FatalError;
use Swilen\Shared\Arthropod\Application;

class ExceptionsHandler implements BootableService
{
    /**
     * The application instance.
     *
     * @var \Swilen\Shared\Arthropod\Application
     */
    protected $app;

    /**
     * Bootstrap exception handler and log manager.
     *
     * @param \Swilen\Shared\Arthropod\Application $app
     *
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $this->app = $app;

        if (!$this->app->isDevelopmentMode() || !$this->app->isDebugMode()) {
            @ini_set('display_errors', 'Off');
        }

        \error_reporting(E_ALL);

        \set_error_handler(function ($level, $message, $file = '', $line = 0) {
            $this->handelError($message, 0, $level, $file, $line);
        });

        \set_exception_handler(function (\Throwable $exception) {
            $this->handleException($exception);
        });

        \register_shutdown_function(function () {
            $this->handleShutdown();
        });
    }

    /**
     * Handle php errors with normalized exception.
     *
     * @param string $message
     * @param int    $code
     * @param int    $level
     * @param string $file
     * @param int    $line
     *
     * @return void
     *
     * @throws \ErrorException
     */
    public function handelError($message, $code, $level, $file, $line)
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, $code, $level, $file, $line);
        }
    }

    /**
     * Handle php shutdown script with normalized exception if is fatal.
     *
     * @return void
     */
    protected function handleShutdown()
    {
        if (($error = error_get_last()) !== null && $this->isFatal($error['type'])) {
            $this->handleException($this->toFatalError($error));
        }
    }

    /**
     * Report and render exceptions.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function handleException(\Throwable $e)
    {
        try {
            $this->getExceptionHandler()->report($e);
        } catch (\Throwable $e) {
        }

        $this->renderHttpResponse($e);
    }

    /**
     * Determine if the error level is a deprecation.
     *
     * @param int $level
     *
     * @return bool
     */
    protected function isDeprecation($level)
    {
        return in_array($level, [E_DEPRECATED, E_USER_DEPRECATED]);
    }

    /**
     * Determine if the error type is fatal.
     *
     * @param int $type
     *
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }

    /**
     * Create a new fatal error instance from an error array.
     *
     * @param array    $error
     * @param int|null $traceOffset
     *
     * @return \Swilen\Arthropod\TypeErrors\FatalError
     */
    protected function toFatalError(array $error)
    {
        return new FatalError($error['message'], 0, $error['type'], $error['file'], $error['line']);
    }

    /**
     * Get an instance of the exception handler.
     *
     * @return \Swilen\Arthropod\Contract\ExceptionHandler
     */
    protected function getExceptionHandler()
    {
        return $this->app->make(ExceptionHandler::class);
    }

    /**
     * Render exception to http client.
     *
     * @param \Throwable $e
     */
    protected function renderHttpResponse(\Throwable $e)
    {
        return $this->getExceptionHandler()->render($e)->terminate();
    }
}

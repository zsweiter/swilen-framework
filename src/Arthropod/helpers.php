<?php

use Swilen\Arthropod\Env;
use Swilen\Container\Container;
use Swilen\Routing\Contract\ResponseFactory;

if (!function_exists('app')) {
	/**
	 * Get the available container instance.
	 *
	 * @param string|null $abstract
	 * @param array       $parameters
	 *
	 * @return \Swilen\Shared\Arthropod\Application|\Swilen\Container\Container
	 */
	function app($abstract = null, array $parameters = [])
	{
		if ($abstract === null) {
			return Container::getInstance();
		}

		return Container::getInstance()->make($abstract, $parameters);
	}
}

if (!function_exists('response')) {
	/**
	 * Helper function for manage response factory.
	 *
	 * @return \Swilen\Routing\Contract\ResponseFactory
	 */
	function response()
	{
		return app()->make(ResponseFactory::class);
	}
}

if (!function_exists('request')) {
	/**
	 * Retrieve current request instance from container.
	 *
	 * @return \Swilen\Http\Request
	 */
	function request()
	{
		return app()->make('request');
	}
}

if (!function_exists('base_path')) {
	/**
	 * Get the application base path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	function base_path($path = '')
	{
		return app()->basePath($path);
	}
}

if (!function_exists('app_path')) {
	/**
	 * Get the application path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	function app_path($path = '')
	{
		return app()->appPath($path);
	}
}

if (!function_exists('storage_path')) {
	/**
	 * Get application storage path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	function storage_path($path = '')
	{
		return app()->storagePath($path);
	}
}

if (!function_exists('config')) {
	/**
	 * Get the specified configuration value.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed|\Swilen\Config\Contract\ConfigRepository
	 */
	function config(string $key = null, $default = null)
	{
		/**
		 * @var \Swilen\Config\Contract\ConfigRepository
		 * */
		$config = app()->make('config');

		if ($key === null) {
			return $config;
		}

		return $config->get($key, $default);
	}
}

if (!function_exists('env')) {
	/**
	 * Retrieve environment varaiable from env file.
	 *
	 * @param string|int      $key
	 * @param string|int|bool $default
	 *
	 * @return string|int|null
	 */
	function env($key, $default = null)
	{
		return Env::get($key, $default);
	}
}

if (!function_exists('tap')) {
	/**
	 * Call the given Closure with the given value then return the value.
	 *
	 * @param mixed    $target
	 * @param callable $callback
	 *
	 * @return mixed
	 */
	function tap($target, $callback)
	{
		$callback($target);

		return $target;
	}
}

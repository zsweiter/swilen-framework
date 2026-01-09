<?php

namespace Swilen\Arthropod;

use Swilen\Shared\Support\Str;

final class Env
{
	/**
	 * The directory for load .env file.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * The file name.
	 *
	 * @var string
	 */
	private $filename = '.env';

	/**
	 * List of env saved.
	 *
	 * @var string[]
	 */
	private static $envs = [];

	/**
	 * List of all env saved.
	 *
	 * @var string[]
	 */
	private static $store = [];

	/**
	 * @var bool
	 */
	private $isInmutable = true;

	/**
	 * The stack variables resolved.
	 *
	 * @var array
	 */
	private static $stack = [];

	/**
	 * The env instance as singleton.
	 *
	 * @var static
	 */
	private static $instance;

	/**
	 * Create new env instance.
	 *
	 * @param string $path
	 * @param bool   $isInmutable
	 *
	 * @return void
	 */
	public function __construct(string $path = null, bool $isInmutable = true)
	{
		$this->path = $path;
		$this->isInmutable = $isInmutable;
	}

	/**
	 * Create environment instance from given path.
	 *
	 * @param string $path
	 * @param bool   $isInmutable
	 *
	 * @return $this
	 */
	public static function createFrom(string $path, bool $isInmutable = true)
	{
		return new static($path, $isInmutable);
	}

	/**
	 * Return full file path of env.
	 *
	 * @return string
	 */
	public function environmentFilePath()
	{
		return $this->path . DIRECTORY_SEPARATOR . $this->filename;
	}

	/**
	 * Return path of en file.
	 *
	 * @return string
	 */
	public function path()
	{
		return $this->path;
	}

	/**
	 * Return the name of env file.
	 *
	 * @return string
	 */
	public function filename()
	{
		return $this->filename;
	}

	/**
	 * Check if enviroment is inmutable.
	 *
	 * @return bool
	 */
	public function isInmutable()
	{
		return (bool) $this->isInmutable;
	}

	/**
	 * Config the enviroment needed configuation.
	 *
	 * @param array $config
	 *
	 * @return $this
	 */
	public function config(array $config)
	{
		$this->filename = $config['file'];

		if (isset($config['path'])) {
			$this->path = $config['path'];
		}

		if (isset($config['inmutable'])) {
			$this->isInmutable = (bool) $config['inmutable'];
		}

		return $this;
	}

	/**
	 * Load variables from defined path.
	 *
	 * @return $this
	 */
	public function load()
	{
		$path = $this->environmentFilePath();

		$realPath = realpath(dirname($path));
		if ($realPath === false || !Str::startsWith($realPath, realpath($this->path))) {
			throw new \RuntimeException('Invalid environment file path');
		}

		if (!is_readable($path)) {
			throw new \RuntimeException('Env file is not readable ' . $path);
		}

		static::$instance = $this;

		$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			if (strpos(trim($line), '#') === 0) {
				continue;
			}

			$varline = explode('=', $line, 2);

			$this->compile($varline[0], $varline[1] ?? null);
		}

		return $this;
	}

	/**
	 * Compile variables with variables replaced.
	 *
	 * @param string               $key
	 * @param string|int|bool|null $value
	 * @param bool                 $replace
	 *
	 * @return void
	 */
	public function compile(string $key, $value, bool $replace = false)
	{
		$key = $this->formatKey($key);
		$value = $this->formatValue($value);

		self::$stack[$key] = $value;

		if (preg_match_all('/\$?\{[A-Z0-9\_]+\}/', $value, $matches)) {
			foreach ($matches[0] as $match) {
				$name = $this->formatKey($match, '${\}');
				$value = str_replace($match, $this->wrapStack($name, $match), $value);
				self::$stack[$key] = $value;
			}
		}

		$this->write($key, $value, $replace);
	}

	/**
	 * Format key and replace special characters.
	 *
	 * @param string      $key
	 * @param string|null $replace
	 *
	 * @return string
	 */
	private function formatKey(string $key, string $replace = null)
	{
		$key = $replace ? trim($key, $replace) : trim($key);

		return str_replace('-', '_', strtoupper($key));
	}

	/**
	 * Format value and remove comments.
	 *
	 * @param int|string|bool $value
	 *
	 * @return bool|int|string
	 */
	private function formatValue($value)
	{
		if (is_null($value)) {
			return null;
		}

		if (($startComment = strpos($value, '#')) !== false) {
			$value = trim(substr($value, 0, $startComment));
		}

		return $this->parseToPrimitive($value);
	}

	/**
	 * Parse values to php primitives.
	 *
	 * @param string|int|bool $value
	 *
	 * @return bool|int|string
	 */
	private function parseToPrimitive($value)
	{
		if (in_array($value, [true, false, 1, 0], true)) {
			return (bool) $value;
		}

		$primitive = str_replace(['"', '\''], '', $value);

		if (in_array($value, [null, 'null', ''], true) || is_null($primitive)) {
			return null;
		}

		if (in_array($primitive, ['true', '(true)', 'on', '1'], true)) {
			return true;
		}

		if (in_array($primitive, ['false', '(false)', 'off', '0'], true)) {
			return false;
		}

		if (is_numeric($primitive) && !Str::contains($value, ['+', '-', '"', '\''])) {
			return (int) $primitive;
		}

		if (Str::startsWith($primitive, 'swilen:')) {
			return (string) base64_decode(substr($primitive, 7) . '=');
		}

		if (Str::startsWith($primitive, 'base64:')) {
			return (string) base64_decode(substr($primitive, 7));
		}

		return $primitive;
	}

	/**
	 * Find key into env stack and return empty string if value not exists.
	 *
	 * @param string|int $key
	 *
	 * @return string
	 */
	private function wrapStack($key, $match)
	{
		return static::$stack[$key] ?? $match;
	}

	/**
	 * Write value to env collection with mutability checked.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param bool   $replace
	 *
	 * @return void
	 */
	private function write(string $key, $value, bool $replace = false)
	{
		if (!$this->isInmutable() || $replace) {
			return $this->writeMutableOrInmutable($key, $value);
		}

		if (!$this->exists($key)) {
			$this->writeMutableOrInmutable($key, $value);
		}
	}

	/**
	 * Write value to env collection, $_ENV and $_SERVER.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	private function writeMutableOrInmutable(string $key, $value)
	{
		static::$envs[$key] = $value;
		$_ENV[$key] = $value;
		$_SERVER[$key] = $value;
	}

	/**
	 * Check key exists into enn collection.
	 *
	 * @param string|int $key
	 *
	 * @return bool
	 */
	private function exists($key)
	{
		return key_exists($key, $_SERVER) && key_exists($key, $_ENV) && key_exists($key, static::$envs);
	}

	/**
	 * Get value with keyed from stored env variables.
	 *
	 * @param string     $key
	 * @param mixed|null $default
	 *
	 * @return mixed|null
	 */
	public static function get($key, $default = null)
	{
		$collection = static::all();

		return key_exists($key, $collection)
			? $collection[$key]
			: $default;
	}

	/**
	 * Return all env variables.
	 *
	 * @return array
	 */
	public static function all()
	{
		if (!empty(static::$store)) {
			return static::$store;
		}

		return static::$store = array_merge($_ENV, $_SERVER, static::$envs);
	}

	/**
	 * Force refilling store collection.
	 *
	 * @return void
	 */
	private function refillingStore()
	{
		static::$store = array_merge($_ENV, $_SERVER, static::$envs);
	}

	/**
	 * Return instance for manipule content has singleton.
	 *
	 * @return static|null
	 */
	public static function getInstance()
	{
		return static::$instance;
	}

	/**
	 * Set enviroment in runtime.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public static function set($key, $value)
	{
		$instance = static::getInstance();

		$instance->compile($key, $value);

		$instance->refillingStore();
	}

	/**
	 * Set enviroment in runtime.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public static function replace($key, $value)
	{
		$instance = static::getInstance();

		$instance->compile($key, $value, true);

		$instance->refillingStore();
	}

	/**
	 * Return array of variables values registered.
	 *
	 * @return array<string, mixed>
	 */
	public static function registered()
	{
		return static::$envs;
	}

	/**
	 * Return stack with variables resolved.
	 *
	 * @return array<string, mixed>
	 */
	public static function stack()
	{
		return static::$stack;
	}

	/**
	 * Forget environement instances and variables stored.
	 *
	 * @return void
	 */
	public static function forget()
	{
		static::$instance = null;

		foreach (static::$envs as $key => $value) {
			unset($_ENV[$key]);
			unset($_SERVER[$key]);
		}

		static::$envs = [];
		static::$store = [];
		static::$stack = [];
	}
}

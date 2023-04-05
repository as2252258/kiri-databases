<?php

namespace Database\Base;

use Database\ModelInterface;

class Setter
{
	
	private array $setter = [];
	
	
	/**
	 * @param string $name
	 * @param string $className
	 * @param string $method
	 * @return void
	 */
	public function write(string $name, string $className, string $method): void
	{
		if (!isset($this->setter[$className])) {
			$this->setter[$className] = [];
		}
		$this->setter[$className][$name] = $method;
	}
	
	
	/**
	 * @param string $className
	 * @param string $name
	 * @return bool
	 */
	public function has(string $className, string $name): bool
	{
		return isset($this->setter[$className]) && isset($this->setter[$className][$name]);
	}
	
	
	/**
	 * @param string $className
	 * @return array|null
	 */
	public function getAll(string $className): ?array
	{
		return $this->setter[$className] ?? null;
	}


	/**
	 * @param ModelInterface $class
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	public function override(ModelInterface $class, string $key, mixed $value): mixed
	{
		$method = $this->setter[$class::class][$key] ?? null;
		if ($method !== null) {
			return $class->{$method}($value);
		}
		return $value;
	}



	/**
	 * @param string $className
	 * @param string $name
	 * @return string|null
	 */
	public function get(string $className, string $name): ?string
	{
		if (!$this->has($className, $name)) {
			return null;
		}
		return $this->setter[$className][$name];
	}
	
	
}

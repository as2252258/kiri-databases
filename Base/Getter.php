<?php

namespace Database\Base;

use Database\ModelInterface;

class Getter
{
	
	private array $getter = [];
	
	
	/**
	 * @param string $name
	 * @param string $className
	 * @param string $method
	 * @return void
	 */
	public function write(string $name, string $className, string $method): void
	{
		if (!isset($this->getter[$className])) {
			$this->getter[$className] = [];
		}
		$this->getter[$className][$name] = $method;
	}
	
	/**
	 * @param string $className
	 * @return array
	 */
	public function getAll(string $className): array
	{
		return $this->getter[$className] ?? [];
	}
	
	/**
	 * @param string $className
	 * @param string $name
	 * @return bool
	 */
	public function has(string $className, string $name): bool
	{
		return isset($this->getter[$className]) && isset($this->getter[$className][$name]);
	}
	
	
	public function get(string $className, string $name): ?string
	{
		if (!$this->has($className,$name)) {
			return null;
		}
		return $this->getter[$className][$name];
	}
	
}

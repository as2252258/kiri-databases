<?php

namespace Database\Base;

class Getter
{

	private array $_classMapping = [];


	/**
	 * @param $name
	 * @param $class
	 * @param $method
	 */
	public function addGetter($name, $class, $method): void
	{
		$this->_classMapping[$class][$name] = $method;
	}


	/**
	 * @param $class
	 * @param null $name
	 * @return array|string|null
	 */
	public function getGetter($class, $name = null): null|array|string
	{
		if (!empty($name)) {
			return $this->_classMapping[$class][$name] ?? null;
		}
		return $this->_classMapping[$class] ?? [];
	}

}

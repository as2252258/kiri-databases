<?php

namespace Database\Base;

class Setter
{

	private array $_classMapping = [];


	/**
	 * @param $name
	 * @param $class
	 * @param $method
	 */
	public function addSetter($name, $class, $method)
	{
		$this->_classMapping[$class][$name] = $method;
	}


	/**
	 * @param $class
	 * @param $name
	 * @return mixed|null
	 */
	public function getSetter($class, $name): ?string
	{
		return $this->_classMapping[$class][$name] ?? null;
	}

}

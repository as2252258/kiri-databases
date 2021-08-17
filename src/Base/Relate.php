<?php

namespace Database\Base;

class Relate
{

	private array $_classMapping = [];


	/**
	 * @param $name
	 * @param $class
	 * @param $method
	 */
	public function addRelate($name, $class, $method)
	{
		$this->_classMapping[$class][$name] = $method;
	}


	/**
	 * @param $class
	 * @param $name
	 * @return mixed|null
	 */
	public function getRelate($class, $name = null): ?string
	{
		if (!empty($name)) {
			return $this->_classMapping[$class][$name] ?? null;
		}
		return $this->_classMapping[$class] ?? [];
	}


}

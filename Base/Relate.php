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
	 * @return null|array|string
	 */
	public function getRelate($class, $name = null): null|array|string
	{
		if (!empty($name)) {
			return $this->_classMapping[$class][$name] ?? null;
		}
		return $this->_classMapping[$class] ?? [];
	}


}

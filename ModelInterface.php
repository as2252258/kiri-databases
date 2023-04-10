<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:39
 */
declare(strict_types=1);

namespace Database;


/**
 * Interface ModelInterface
 * @package Database
 */
interface ModelInterface
{

	/**
	 * @param array|string $param
	 * @param null $db
	 * @return ModelInterface
	 */
	public static function findOne(array|string $param, $db = NULL): mixed;


	/**
	 * @param array $data
	 * @return static
	 */
	public static function populate(array $data): static;


	/**
	 * @return ActiveQuery
	 * return a sql queryBuilder
	 */
	public static function query(): ActiveQuery;


	/**
	 * @return ?string
	 */
	public function getPrimary(): ?string;


	/**
	 * @return string
	 */
	public function getTable(): string;


	/**
	 * @return Connection
	 */
	public function getConnection(): Connection;


}

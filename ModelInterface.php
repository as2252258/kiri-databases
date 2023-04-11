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
	 * @param array|string|int $param
	 * @param null $db
	 * @return ModelInterface|null
	 */
	public static function findOne(array|string|int $param, $db = NULL): ?static;


	/**
	 * @param int $param
	 * @param null $db
	 * @return ModelInterface|null
	 */
	public static function primary(int $param, $db = NULL): ?static;


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

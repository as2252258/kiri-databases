<?php
declare(strict_types=1);

namespace Database;

use Database\Traits\HasBase;
use Exception;
use Kiri;

/**
 * Class HasCount
 * @package Database
 */
class HasCount extends HasBase
{

	/**
	 * @return array|null|ModelInterface
	 * @throws Exception
	 */
	public function get(): array|ModelInterface|null
	{
		$relation = Kiri::getDi()->get(Relation::class);
		return $relation->get($this->name);
	}

}

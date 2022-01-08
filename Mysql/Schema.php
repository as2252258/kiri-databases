<?php
declare(strict_types=1);
namespace Database\Mysql;


use Exception;
use Kiri\Abstracts\Component;
use Database\Connection;

/**
 * Class Schema
 * @package Database\Mysql
 */
class Schema extends Component
{

	/** @var ?Connection */
	public ?Connection $db = null;

	/** @var ?Columns $_column*/
	private ?Columns $_column = null;


	/**
	 * @return Columns|null
	 * @throws Exception
	 */
	public function getColumns(): ?Columns
	{
		if ($this->_column === null) {
			$this->_column = new Columns(['db' => $this->db]);
		}

		return $this->_column;
	}
}

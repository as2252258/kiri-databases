<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/9 0009
 * Time: 9:44
 */
declare(strict_types=1);

namespace Database\Base;


use ArrayIterator;
use Database\ActiveQuery;
use Database\ModelInterface;
use Kiri\ToArray;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Component;
use ReturnTypeWillChange;
use Traversable;

/**
 * Class AbstractCollection
 * @package Database\Base
 */
abstract class AbstractCollection extends Component implements \IteratorAggregate, \ArrayAccess, ToArray
{

    /**
     * @var ModelInterface[]
     */
    protected array $_item = [];

    protected ModelInterface|string|null $model;

    protected ActiveQuery $query;


    public function clean()
    {
        unset($this->query, $this->model, $this->_item);
    }


	/**
	 * Collection constructor.
	 *
	 * @param $query
	 * @param array $array
	 * @param ModelInterface|null $model
	 * @throws Exception
	 */
    public function __construct($query, array $array = [], ModelInterface $model = null)
    {
        $this->_item = $array;
        $this->query = $query;
        $this->model = $model;

        parent::__construct([]);
    }


    /**
     * @return int
     */
    #[Pure] public function getLength(): int
    {
        return count($this->_item);
    }


    /**
     * @param $item
     */
    public function setItems($item)
    {
        $this->_item = $item;
    }


    /**
     * @param $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * @param $item
     */
    public function addItem($item)
    {
        $this->_item[] = $item;
    }

    /**
     * @return Traversable|CollectionIterator|ArrayIterator
     * @throws Exception
     */
    public function getIterator(): Traversable|CollectionIterator|ArrayIterator
    {
        return new CollectionIterator($this->model, $this->_item);
    }


    /**
     * @return mixed
     * @throws Exception
     */
    public function getModel(): ModelInterface
    {
        return $this->model;
    }


    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return !empty($this->_item) && isset($this->_item[$offset]);
    }

    /**
     * @param mixed $offset
     * @return ModelInterface|null
     * @throws Exception
     */
    public function offsetGet(mixed $offset): ?ModelInterface
    {
        if (!$this->offsetExists($offset)) {
            return NULL;
        }
        if (!($this->_item[$offset] instanceof ModelInterface)) {
            return $this->model->populates($this->_item[$offset]);
        }
        return $this->_item[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    #[ReturnTypeWillChange] public function offsetSet(mixed $offset, mixed $value)
    {
        $this->_item[$offset] = $value;
    }


    /**
     * @param mixed $offset
     */
    #[ReturnTypeWillChange] public function offsetUnset(mixed $offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->_item[$offset]);
        }
    }
}

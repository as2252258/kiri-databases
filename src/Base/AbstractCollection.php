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
use Database\ActiveRecord;
use Database\ObjectToArray;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Component;
use Kiri\Kiri;
use Traversable;

/**
 * Class AbstractCollection
 * @package Database\Base
 */
abstract class AbstractCollection extends Component implements \IteratorAggregate, \ArrayAccess, ObjectToArray
{

    /**
     * @var ActiveRecord[]
     */
    protected array $_item = [];

    protected ActiveRecord|string|null $model;

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
     * @param string|ActiveRecord|null $model
     * @throws Exception
     */
    public function __construct($query, array $array = [], string|ActiveRecord $model = null)
    {
        $this->_item = $array;
        $this->query = $query;
        $this->model = duplicate($model);

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
        array_push($this->_item, $item);
    }

    /**
     * @return Traversable|CollectionIterator|ArrayIterator
     * @throws Exception
     */
    public function getIterator(): Traversable|CollectionIterator|ArrayIterator
    {
        return new CollectionIterator($this->model, $this->query, $this->_item);
    }


    /**
     * @return mixed
     * @throws Exception
     */
    public function getModel(): ActiveRecord
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
     * @return ActiveRecord|null
     * @throws Exception
     */
    public function offsetGet(mixed $offset): ?ActiveRecord
    {
        if (!$this->offsetExists($offset)) {
            return NULL;
        }
        if (!($this->_item[$offset] instanceof ActiveRecord)) {
            return $this->model->setAttributes($this->_item[$offset]);
        }
        return $this->_item[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value)
    {
        $this->_item[$offset] = $value;
    }


    /**
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->_item[$offset]);
        }
    }
}

<?php /** @noinspection ALL */

declare(strict_types=1);

namespace Database;


use JetBrains\PhpStorm\Pure;
use Kiri;
use Kiri\Abstracts\Component;


/**
 * Class SqlBuilder
 * @package Database
 */
class SqlBuilder extends Component
{

    /**
     * @var ActiveQuery|Query|ISqlBuilder|null
     */
    public ActiveQuery|Query|ISqlBuilder|null $query;


    /**
     * @param ActiveQuery|Query|ISqlBuilder|null $config
     */
    public function __construct(ActiveQuery|Query|null|ISqlBuilder $config)
    {
        parent::__construct();

        $this->query = $config;
    }


    /**
     * @param ISqlBuilder|null $query
     * @return $this
     * @throws
     */
    public static function builder(ISqlBuilder|null $query): static
    {
        return new static($query);
    }


    /**
     * @return string
     * @throws
     */
    public function getCondition(): string
    {
        return $this->where($this->query->where);
    }


    /**
     * @param array $compiler
     * @return string
     * @throws
     */
    public function hashCompiler(array $compiler): string
    {
        return $this->where($compiler);
    }


    /**
     * @param array $attributes
     * @return bool|array
     * @throws
     */
    public function update(array $attributes): bool|string
    {
        $conditions          = $this->query->params;
        $this->query->params = [];
        $data                = $this->__updateBuilder($this->makeParams($attributes));
        foreach ($conditions as $condition) {
            $this->query->pushParam($condition);
        }
        return $data;
    }


    /**
     * @param array $attributes
     * @param string $opera
     * @return bool|array
     * @throws
     */
    public function mathematics(array $attributes, string $opera = '+'): bool|string
    {
        $string = [];
        foreach ($attributes as $attribute => $value) {
            $string[] = $attribute . '=' . $attribute . $opera . $value;
        }
        return $this->__updateBuilder($string);
    }


    /**
     * @param array $string
     * @return string|bool
     * @throws
     */
    private function __updateBuilder(array $string): string|bool
    {
        if (empty($string)) {
            return Kiri::getLogger()->failure('None data update.');
        }
        return 'UPDATE ' . $this->query->from . ' SET ' . implode(',', $string) . $this->make();
    }


    /**
     * @param array $attributes
     * @param false $isBatch
     * @return array
     * @throws
     */
    public function insert(array $attributes, bool $isBatch = false): string
    {
        $update = 'INSERT INTO ' . $this->query->from;
        if ($isBatch === false) {
            $attributes = [$attributes];
        }
        $update .= '(' . implode(',', $this->getFields($attributes)) . ') VALUES ';

        $keys = [];
        foreach ($attributes as $attribute) {
            $_keys = $this->makeParams($attribute, true);

            $keys[] = implode(',', $_keys);
        }
        return $update . '(' . implode('),(', $keys) . ')';
    }


    /**
     * @return string
     * @throws
     */
    public function delete(): string
    {
        return 'DELETE FROM ' . $this->query->from . $this->make();
    }


    /**
     * @param $attributes
     * @return array
     */
    #[Pure] private function getFields($attributes): array
    {
        return array_keys(current($attributes));
    }


    /**
     * @param array $attributes
     * @param bool $isInsert
     * @return array[]
     * a=:b,
     */
    private function makeParams(array $attributes, bool $isInsert = false): array
    {
        $keys = [];
        foreach ($attributes as $key => $value) {
            if ($isInsert === true) {
                $keys[] = '?';
                $this->query->pushParam($value);
            } else {
                $keys = $this->resolveParams($key, $value, $keys);
            }
        }
        return $keys;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @param array $keys
     * @return array
     */
    private function resolveParams(string $key, mixed $value, array $keys): array
    {
        if (is_null($value)) {
            return $keys;
        }
        if (is_string($value) && $this->isMath($value)) {
            $keys[] = $key . '=' . $key . ' ' . $value;
        } else {
            $this->query->pushParam($value);
            $keys[] = $key . '= ?';
        }
        return $keys;
    }


    /**
     * @param string $value
     * @return bool
     */
    private function isMath(string $value): bool
    {
        return str_starts_with($value, '+ ') || str_starts_with($value, '- ');
    }


    /**
     * @return string
     * @throws
     */
    public function one(): string
    {
        return $this->makeSelect($this->query->select) . $this->make() . $this->makeLimit($this->query->limit(1));
    }


    /**
     * @return string
     * @throws
     */
    public function all(): string
    {
        return $this->makeSelect($this->query->select) . $this->make() . $this->makeLimit($this->query);
    }


    /**
     * @return string
     * @throws
     */
    public function count(): string
    {
        return $this->makeSelect() . $this->make();
    }


    /**
     * @param $table
     * @return string
     */
    public function columns($table): string
    {
        return 'SHOW FULL FIELDS FROM ' . $table;
    }


    /**
     * @return string
     * @throws
     */
    private function make(): string
    {
        $select = $this->makeCondition();
        $select .= $this->makeGroup();
        $select .= $this->makeOrder();
        return $select;
    }

    /**
     * @param array $select
     * @return string
     */
    private function makeSelect(array $select = ['*']): string
    {
        $select = "SELECT " . implode(',', $select) . " FROM " . $this->query->from;
        if ($this->query->alias != "") {
            $select .= " AS " . $this->query->alias;
        }
        if (count($this->query->join) > 0) {
            $select .= ' ' . implode(' ', $this->query->join);
        }
        return $select;
    }


    /**
     * @return string
     */
    private function makeGroup(): string
    {
        if ($this->query->group != "") {
            return ' GROUP BY ' . $this->query->group;
        }
        return '';
    }


    /**
     * @return string
     */
    private function makeOrder(): string
    {
        if (count($this->query->order) > 0) {
            return ' ORDER BY ' . implode(',', $this->query->order);
        }
        return '';
    }


    /**
     * @return string
     */
    private function makeCondition(): string
    {
        $condition = $this->where($this->query->where);
        if (empty($condition)) {
            return '';
        }
        return ' WHERE ' . $condition;
    }


    private function makeLimit(): string
    {
        if ($this->query->offset >= 0 && $this->query->limit >= 1) {
            return ' LIMIT ' . $this->query->offset . ',' . $this->query->limit;
        }
        return '';
    }


    /**
     * @param false $isCount
     * @return string
     * @throws
     */
    public function get(bool $isCount = false): string
    {
        if ($isCount === false) {
            return $this->all();
        }
        return $this->count();
    }


    /**
     * @return string
     * @throws
     */
    public function truncate(): string
    {
        return sprintf('TRUNCATE %s', $this->query->from);
    }


    /**
     * @param array $where
     * @return string
     */
    private function where(array $where): string
    {
        if (count($where) < 1) {
            return '';
        }
        $_tmp = [];
        foreach ($where as $key => $value) {
            $_tmp[] = $this->resolveCondition($key, $value);
        }
        return implode(' AND ', $_tmp);
    }


    /**
     * @param $field
     * @param $condition
     * @return string
     */
    private function resolveCondition($field, $condition): string
    {
        if (is_string($field)) {
            $this->query->pushParam($condition);
            return $field . ' = ?';
        } else if (is_string($condition)) {
            return $condition;
        } else {
            return implode(' AND ', $this->_hashMap($condition));
        }
    }


    /**
     * @param $condition
     * @return array
     */
    private function _hashMap($condition): array
    {
        $_array = [];
        foreach ($condition as $key => $value) {
            $this->query->pushParam($value);
            $_array[] = $key . '= ?';
        }
        return $_array;
    }


}

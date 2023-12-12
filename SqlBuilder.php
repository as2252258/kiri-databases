<?php

declare(strict_types=1);

namespace Database;


use Database\Traits\Builder;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Component;


/**
 * Class SqlBuilder
 * @package Database
 */
class SqlBuilder extends Component
{

    use Builder;


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
        return $this->conditionToString();
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
        $conditions              = $this->query->attributes;
        $this->query->attributes = [];
        $data                    = $this->__updateBuilder($this->builderParams($attributes));
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
            return \Kiri::getLogger()->failure('None data update.');
        }
        return 'UPDATE ' . $this->query->from . ' SET ' . implode(',', $string) . $this->_prefix();
    }


    /**
     * @param array $attributes
     * @param false $isBatch
     * @return array
     * @throws
     */
    public function insert(array $attributes, bool $isBatch = false): array
    {
        $update = 'INSERT INTO ' . $this->query->from;
        if ($isBatch === false) {
            $attributes = [$attributes];
        }
        $update .= '(' . implode(',', $this->getFields($attributes)) . ') VALUES ';

        $order = 0;
        $keys  = [];
        foreach ($attributes as $attribute) {
            $_keys = $this->builderParams($attribute, true, $order);

            $keys[] = implode(',', $_keys);
            $order++;
        }
        return [$update . '(' . implode('),(', $keys) . ')', $this->query->attributes];
    }


    /**
     * @return string
     * @throws
     */
    public function delete(): string
    {
        return 'DELETE FROM ' . $this->query->from . $this->_prefix();
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
     * @param int $order
     * @return array[]
     * a=:b,
     */
    private function builderParams(array $attributes, bool $isInsert = false, int $order = 0): array
    {
        $keys = [];
        foreach ($attributes as $key => $value) {
            if ($isInsert === true) {
                $keys[] = '?';
                $this->query->pushParam($value);
            } else {
                $keys = $this->resolveParams($key, $value, $order, $keys);
            }
        }
        return $keys;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @param int $order
     * @param array $keys
     * @return array
     */
    private function resolveParams(string $key, mixed $value, int $order, array $keys): array
    {
        if (is_null($value)) {
            return $keys;
        }
        if (is_string($value) && (str_starts_with($value, '+ ') || str_starts_with($value, '- '))) {
            $keys[] = $key . '=' . $key . ' ' . $value;
        } else {
            $this->query->pushParam($value);
            $keys[] = $key . '= ?';
        }
        return $keys;
    }


    /**
     * @return string
     * @throws
     */
    public function one(): string
    {
        if (count($this->query->select) < 1) {
            $this->query->select = ['*'];
        }
        return $this->_selectPrefix($this->query->select) . $this->_prefix() . $this->builderLimit($this->query);
    }


    /**
     * @return string
     * @throws
     */
    public function all(): string
    {
        if (count($this->query->select) < 1) {
            $this->query->select = ['*'];
        }
        return $this->_selectPrefix($this->query->select) . $this->_prefix() . $this->builderLimit($this->query);
    }


    /**
     * @return string
     * @throws
     */
    public function count(): string
    {
        return $this->_selectPrefix(['COUNT(*) as row_count']) . $this->_prefix();
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
    private function _prefix(): string
    {
        $select = '';
        if (($condition = $this->conditionToString()) != '') {
            $select .= " WHERE $condition";
        }
        if ($this->query->group != "") {
            $select .= ' GROUP BY ' . $this->query->group;
        }
        if (count($this->query->order) > 0) {
            $select .= ' ORDER BY ' . implode(',', $this->query->order);
        }
        return $select;
    }

    /**
     * @param array $select
     * @return string
     */
    private function _selectPrefix(array $select = ['*']): string
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
     * @return string
     * @throws
     */
    private function conditionToString(): string
    {
        return $this->where($this->query->where);
    }

}

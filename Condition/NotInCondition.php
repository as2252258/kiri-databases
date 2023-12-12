<?php
declare(strict_types=1);

namespace Database\Condition;

use JetBrains\PhpStorm\Pure;

/**
 * Class NotInCondition
 * @package Database\Condition
 */
class NotInCondition extends Condition
{


    /**
     * @return string|null
     * @throws
     */
    #[Pure] public function builder(): ?string
    {
        if (!is_array($this->value)) {
            throw new \Exception('Builder data by a empty string. need array');
        }
        $value = '\'' . implode('\',\'', $this->value) . '\'';
        return '`' . $this->column . '` not in(' . $value . ')';
    }

}

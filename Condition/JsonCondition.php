<?php


namespace Database\Condition;


/**
 * Class JsonCondition
 * @package Database\Condition
 */
class JsonCondition extends Condition
{


    /**
     * @return bool
     */
	public function builder(): bool
    {
		// TODO: Implement builder() method.
        return \json_validate($this->value);
	}

}

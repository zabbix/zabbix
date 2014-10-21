<?php

class CUpdateFixture extends CFixture {

	/**
	 * Supported parameters:
	 * - table	- table to update
	 * - values	- values to update
	 * - where	- where condition
	 */
	public function load(array $params) {
		$this->checkMissingParams($params, array('table', 'values', 'where'));

		try {
			DB::update($params['table'], array(
				'values' => $params['values'],
				'where' => $params['where']
			));
		}
		catch (Exception $e) {
			global $ZBX_MESSAGES;
			$lastMessage = array_pop($ZBX_MESSAGES);

			// treat all DB errors as invalid argument exceptions
			throw new InvalidArgumentException($lastMessage['message'], $e->getCode(), $e);
		}
	}

}

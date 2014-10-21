<?php


/**
 * A class for loading fixtures using the database.
 */
class CDataFixture extends CFixture {

	/**
	 * Load a fixture that inserts data directly into the database.
	 *
	 * Supported parameters:
	 * - table			- table to insert the data in
	 * - values			- array of rows to insert
	 * - generateIds	- whether to automatically generate IDs for the inserted rows; defaults to true
	 */
	public function load(array $params) {
		$this->checkMissingParams($params, array('table', 'values'));

		$generateIds = isset($params['generateIds']) ? $params['generateIds'] : true;

		try {
			DBstart();

			$ids = DB::insert($params['table'], $params['values'], $generateIds);

			DBend();
		}
		catch (Exception $e) {
			DBend(false);

			global $ZBX_MESSAGES;

			if ($ZBX_MESSAGES) {
				$lastMessage = array_pop($ZBX_MESSAGES);
				$message = $lastMessage['message'];
			}
			else {
				$message = $e->getMessage();
			}

			// treat all DB errors as invalid argument exceptions
			throw new InvalidArgumentException($message, $e->getCode(), $e);
		}

		return $ids;
	}

}

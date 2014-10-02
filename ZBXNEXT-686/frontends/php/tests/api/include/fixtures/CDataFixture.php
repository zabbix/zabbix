<?php


class CDataFixture extends CFixture {

	public function load(array $params) {
		// TODO: automatically handle cases when IDs shouldn't be incremented
		$generateIds = isset($params['generateIds']) ? $params['generateIds'] : true;

		try {
			DBstart();

			// TODO: enable ID generation
			$ids = \DB::insert($params['table'], $params['values'], $generateIds);

			DBend();
		}
		catch (Exception $e) {
			DBend(false);

			global $ZBX_MESSAGES;
			$lastMessage = array_pop($ZBX_MESSAGES);
			throw new Exception($lastMessage['message']);
		}

		return $ids;
	}

}

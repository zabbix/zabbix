<?php

namespace Zabbix\Test\Fixtures;


class DataFixture extends Fixture {

	public function load(array $params) {
		try {
			DBstart();

			// TODO: enable ID generation
			\DB::insert($params['table'], $params['values'], false);

			DBend();
		}
		catch (\Exception $e) {
			DBend(false);

			global $ZBX_MESSAGES;
			$lastMessage = array_pop($ZBX_MESSAGES);
			throw new \Exception($lastMessage['message']);
		}
	}

}

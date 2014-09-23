<?php

namespace Zabbix\Test\Fixtures;


class UpdateFixture extends Fixture {

	public function load(array $params) {
		try {
			\DB::update($params['table'], array(
				'values' => $params['values'],
				'where' => $params['where']
			));
		}
		catch (\Exception $e) {
			global $ZBX_MESSAGES;
			$lastMessage = array_pop($ZBX_MESSAGES);
			throw new \Exception($lastMessage['message']);
		}
	}

}

<?php

namespace Zabbix\Test;

class TestDatabase {

	public function clear() {
		DBstart();

		// TODO: clear the database in the correct order
		foreach ($this->getTablesToClear() as $tableName) {
			$rs = DBexecute('DELETE FROM '.$tableName);
			if (!$rs) {
				DBend(false);

				global $ZBX_MESSAGES;
				$lastMessage = array_pop($ZBX_MESSAGES);
				throw new \Exception($lastMessage['message']);
			}
		}

		DBend();
	}

	protected function getTablesToClear() {
		// TODO: implement a better ordering algorithm
		$tables = array();
		foreach (\DB::getSchema() as $tableName => $tableData) {
			if (in_array($tableName, array('dbversion', 'items', 'httptest'))) {
				continue;
			}

			$tables[] = $tableName;
		}

		// clear the items table first to avoid integrity check errors
		array_unshift($tables, 'items');
		array_unshift($tables, 'httptest');

		return $tables;
	}

}

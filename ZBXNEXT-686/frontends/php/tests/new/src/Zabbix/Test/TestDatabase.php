<?php

namespace Zabbix\Test;

class TestDatabase {

	public function clear() {
		try {
			DBstart();

			// TODO: clear the database in the correct order
			foreach ($this->getTablesToClear() as $tableName) {
				DBexecute('DELETE FROM '.$tableName);
			}

			DBend();
		}
		catch (\Exception $e) {
			DBend(false);

			throw $e;
		}
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

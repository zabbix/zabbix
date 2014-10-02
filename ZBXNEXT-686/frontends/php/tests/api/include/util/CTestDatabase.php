<?php

class CTestDatabase {

	public function clear() {
		DBstart();

		// TODO: clear the database in the correct order
		foreach ($this->getTablesToClear() as $tableName) {
			$rs = DBexecute('DELETE FROM '.$tableName);
			if (!$rs) {
				DBend(false);

				global $ZBX_MESSAGES;
				$lastMessage = array_pop($ZBX_MESSAGES);
				throw new Exception($lastMessage['message']);
			}
		}

		DBend();
	}

	protected function getTablesToClear() {
		$bumpTables = array(
			'usrgrp',
			'scripts',
			'opgroup',
			'opmessage_grp',
			'optemplate',
			'items',
			'graphs',
			'httptest',
			'config',
			'trigger_discovery',
			'graph_discovery',
			'item_discovery',
			'host_discovery',
		);

		// TODO: implement a better ordering algorithm
		$tables = array();
		foreach (DB::getSchema() as $tableName => $tableData) {
			if (in_array($tableName, $bumpTables) || $tableName == 'dbversion') {
				continue;
			}

			$tables[] = $tableName;
		}

		// clear the items table first to avoid integrity check errors
		foreach ($bumpTables as $table) {
			array_unshift($tables, $table);
		}

		return $tables;
	}

}

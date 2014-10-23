<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * A utility class for working with the test database.
 */
class CTestDatabase {

	/**
	 * Truncates all of the tables except for "DB" version.
	 *
	 * @param array $skipTables		tables to skip
	 *
	 * @throws Exception	if a db error occurs
	 */
	public function clear(array $skipTables = array()) {
		DBstart();

		foreach ($this->getTablesToClear() as $tableName) {
			if (in_array($tableName, $skipTables)) {
				continue;
			}

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

	/**
	 * Returns tables in the order they can be truncated taking into account the foreign key relations.
	 *
	 * @return array
	 */
	protected function getTablesToClear() {
		$schema = DB::getSchema();

		$tables = array();
		foreach ($this->getTopTables($schema) as $topTable) {
			$this->addChildTables($schema, $topTable, $tables);
		}

		return array_reverse($tables);
	}

	/**
	 * Get a list of tables that don't reference other tables.
	 *
	 * @param array $schema
	 *
	 * @return array
	 */
	protected function getTopTables(array $schema) {
		$rs = array();
		foreach ($schema as $name => $table) {
			foreach ($table['fields'] as $field) {
				if (isset($field['ref_table']) && ($field['ref_table'] !== $name)) {
					continue 2;
				}
			}

			$rs[] = $name;
		}

		return $rs;
	}

	/**
	 * Add table that are related to the start table to the $tables array.
	 *
	 * @param array 	$schema
	 * @param string	$startTableName
	 * @param array 	$tables
	 */
	protected function addChildTables(array $schema, $startTableName, array &$tables) {
		if (in_array($startTableName, $tables)) {
			return;
		}

		$startTable = $schema[$startTableName];

		// handle tables that the top table refers to
		foreach ($startTable['fields'] as $field) {
			if (isset($field['ref_table'])) {
				if ($field['ref_table'] != $startTableName) {
					$this->addChildTables($schema, $field['ref_table'], $tables);
				}
			}
		}

		if (!in_array($startTableName, $tables)) {
			$tables[] = $startTableName;
		}

		// handle tables that refer to the top table
		foreach ($schema as $tableName => $table) {
			foreach ($table['fields'] as $field) {
				if (isset($field['ref_table'])) {
					if ($field['ref_table'] == $startTableName && $startTableName != $tableName) {
						$this->addChildTables($schema, $tableName, $tables);
					}
				}
			}
		}
	}

}

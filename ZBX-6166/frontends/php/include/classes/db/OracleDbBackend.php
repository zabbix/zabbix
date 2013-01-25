<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Database backend class for Oracle.
 */
class OracleDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return boolean
	 */
	protected function checkDbVersionTable() {
		$tableExists = DBfetch(DBselect("SELECT table_name FROM user_tables WHERE table_name='DBVERSION'"));

		if (!$tableExists) {
			$this->setError(_('The frontend does not match Zabbix database.'));
			return false;
		}

		return true;
	}

	/**
	 * Generate INSERT SQL query. Generation example:
	 * Generation example:
	 *	INSERT ALL
	 *		INTO usrgrp (usrgrpid, name, gui_access, users_status, debug_mode)
	 *		VALUES ('20', '8d3d71fff0eb9e2108093d0526f55784', '1', '0', '1')
	 *		INTO usrgrp (usrgrpid, name, gui_access, users_status, debug_mode)
	 *		VALUES ('21', '9c382b26b8dd11dc9dc2911dce5ab32b', '0', '0', '0')
	 *	SELECT * FROM dual
	 *
	 * @param string $table
	 * @param array $fields
	 * @param array $values
	 *
	 * @return string
	 */
	public function insertGeneration($table, $fields, $values) {
		$sql = 'INSERT ALL';
		$tableAndFields = ' INTO '.$table.' ('.implode(',', $fields).') VALUES';

		foreach ($values as $row) {
			$sql .= $tableAndFields.' ('.implode(',', array_values($row)).')';
		}

		$sql .= ' SELECT * FROM dual';

		return $sql;
	}
}

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

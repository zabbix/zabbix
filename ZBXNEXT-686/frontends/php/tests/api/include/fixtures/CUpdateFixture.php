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


class CUpdateFixture extends CFixture {

	/**
	 * Supported parameters:
	 * - table	- table to update
	 * - values	- values to update
	 * - where	- where condition
	 */
	public function load(array $params) {
		$this->checkMissingParams($params, array('table', 'values', 'where'));

		try {
			DB::update($params['table'], array(
				'values' => $params['values'],
				'where' => $params['where']
			));
		}
		catch (Exception $e) {
			global $ZBX_MESSAGES;
			$lastMessage = array_pop($ZBX_MESSAGES);

			// treat all DB errors as invalid argument exceptions
			throw new InvalidArgumentException($lastMessage['message'], $e->getCode(), $e);
		}
	}

}

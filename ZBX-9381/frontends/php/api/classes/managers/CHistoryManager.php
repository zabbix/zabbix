<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * Class to perform low level history related actions.
 */
class CHistoryManager {

	/**
	 * Returns the last $limit history objects for the given items.
	 *
	 * @param array $items      an array of items with the 'itemid' and 'value_type' properties
	 * @param int   $limit
	 * @param int   $period     the maximum period to retrieve data for
	 *
	 * @return array    an array with items IDs as keys and arrays of history objects as values
	 */
	public function getLast(array $items, $limit = 1, $period = null) {
		$rs = array();
		foreach ($items as $item) {
			$values = DBfetchArray(DBselect(
				'SELECT *'.
				' FROM '.self::getTableName($item['value_type']).' h'.
				' WHERE h.itemid='.zbx_dbstr($item['itemid']).
					($period ? ' AND h.clock>'.(time() - $period) : '').
				' ORDER BY h.clock DESC',
				$limit
			));

			if ($values) {
				$rs[$item['itemid']] = $values;
			}
		}

		return $rs;
	}

	/**
	 * Return the name of the table where the data for the given value type is stored.
	 *
	 * @param int $valueType
	 *
	 * @return string
	 */
	public static function getTableName($valueType) {
		$tables = array(
			ITEM_VALUE_TYPE_LOG => 'history_log',
			ITEM_VALUE_TYPE_TEXT => 'history_text',
			ITEM_VALUE_TYPE_STR => 'history_str',
			ITEM_VALUE_TYPE_FLOAT => 'history',
			ITEM_VALUE_TYPE_UINT64 => 'history_uint'
		);

		return $tables[$valueType];
	}

}

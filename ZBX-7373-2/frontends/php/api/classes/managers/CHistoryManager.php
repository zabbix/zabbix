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
 * Class to perform low level history related actions.
 */
class CHistoryManager {

	/**
	 * Returns the last $limit history objects for the given items.
	 *
	 * @param array $items  an array of items with the 'itemid' and 'value_type' properties
	 * @param int $limit
	 *
	 * @return array    an array with items IDs as keys and arrays of history objects as values
	 */
	public function getLast(array $items, $limit = 1) {
		$rs = array();
		foreach ($items as $item) {
			$values = $this->getLastForItem($item['itemid'], $item['value_type'], $limit);
			if ($values) {
				$rs[$item['itemid']] = $values;
			}
		}

		return $rs;
	}

	protected function getLastForItem($itemId, $valueType, $limit = 1) {
		$periods = array(
			SEC_PER_HOUR,
			SEC_PER_DAY,
			SEC_PER_WEEK,
			SEC_PER_MONTH,
			0,
		);

		$values = array();
		$startTime = time();
		foreach ($periods as $period) {
			$endTime = $startTime - $period;

			$table = self::getTableName($valueType);
			$query = DBselect(
				'SELECT *'.
					' FROM '.$table.' h'.
					' WHERE h.itemid='.zbx_dbstr($itemId).
					' AND h.clock<='.$startTime.
					($period ? ' AND h.clock>'.$endTime : '').
					' ORDER BY h.clock DESC',
				$limit
			);
			while ($history = DBfetch($query)) {
				$values[] = $history;

				if (count($values) == $limit) {
					break 2;
				}
			}

			$startTime -= $period;
		}

		return $values;
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

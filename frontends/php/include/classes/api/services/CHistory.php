<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * Class containing methods for operations with histories.
 */
class CHistory extends CApiService {

	protected $tableName = 'history';
	protected $tableAlias = 'h';
	protected $sortColumns = ['itemid', 'clock'];

	public function __construct() {
		// considering the quirky nature of the history API,
		// the parent::__construct() method should not be called.
	}

	/**
	 * Get history data.
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param boolean $options['editable']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['history' => 'h.itemid'],
			'from'		=> [],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'history'					=> ITEM_VALUE_TYPE_UINT64,
			'hostids'					=> null,
			'itemids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			'time_from'					=> null,
			'time_till'					=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		if (!$tableName = CHistoryManager::getTableName($options['history'])) {
			$tableName = 'history';
		}
		$sqlParts['from']['history'] = $tableName.' h';

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == self::$userData['type'] || $options['nopermissions']) {
		}
		else {
			$items = API::Item()->get([
				'itemids' => ($options['itemids'] === null) ? null : $options['itemids'],
				'output' => ['itemid'],
				'editable' => $options['editable'],
				'preservekeys' => true,
				'webitems' => true
			]);
			$options['itemids'] = array_keys($items);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);
			$sqlParts['where']['itemid'] = dbConditionInt('h.itemid', $options['itemids']);
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['hi'] = 'h.itemid=i.itemid';
		}

		// time_from
		if (!is_null($options['time_from'])) {
			$sqlParts['where']['clock_from'] = 'h.clock>='.zbx_dbstr($options['time_from']);
		}

		// time_till
		if (!is_null($options['time_till'])) {
			$sqlParts['where']['clock_till'] = 'h.clock<='.zbx_dbstr($options['time_till']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter($sqlParts['from']['history'], $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search($sqlParts['from']['history'], $options, $sqlParts);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			unset($sqlParts['select']['clock']);
			$sqlParts['select']['history'] = 'h.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = ['count(DISTINCT h.hostid) as rowscount'];

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		$sqlParts = $this->applyQuerySortOptions($tableName, $this->tableAlias(), $options, $sqlParts);

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		$sqlWhere = !empty($sqlParts['where']) ? ' WHERE '.implode(' AND ', $sqlParts['where']) : '';
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.$sqlSelect.
				' FROM '.$sqlFrom.
				$sqlWhere.
				$sqlOrder;
		$dbRes = DBselect($sql, $sqlLimit);
		while ($data = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				$result = $data;
			}
			else {
				$result[] = $data;
			}
		}

		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}
}

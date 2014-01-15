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
 * Class containing methods for operations with histories.
 *
 * @package API
 */
class CHistory extends CZBXAPI {

	protected $tableName = 'history';
	protected $tableAlias = 'h';
	protected $sortColumns = array('itemid', 'clock');

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
	public function get($options = array()) {
		$result = array();
		$nodeCheck = false;

		$sqlParts = array(
			'select'	=> array('history' => 'h.itemid'),
			'from'		=> array(),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'history'					=> ITEM_VALUE_TYPE_UINT64,
			'nodeids'					=> null,
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
		);
		$options = zbx_array_merge($defOptions, $options);

		if (!$tableName = CHistoryManager::getTableName($options['history'])) {
			$tableName = 'history';
		}
		$sqlParts['from']['history'] = $tableName.' h';

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == self::$userData['type'] || $options['nopermissions']) {
		}
		else {
			$items = API::Item()->get(array(
				'itemids' => ($options['itemids'] === null) ? null : $options['itemids'],
				'output' => array('itemid'),
				'editable' => $options['editable'],
				'preservekeys' => true,
				'webitems' => true
			));
			$options['itemids'] = array_keys($items);
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);
			$sqlParts['where']['itemid'] = dbConditionInt('h.itemid', $options['itemids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'h.itemid', $nodeids);
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['hi'] = 'h.itemid=i.itemid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'i.hostid', $nodeids);
			}
		}

		// should be last, after all ****IDS checks
		if (!$nodeCheck) {
			$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'h.itemid', $nodeids);
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
			$sqlParts['select'] = array('count(DISTINCT h.hostid) as rowscount');

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

	protected function applyQuerySortOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$isIdFieldUsed = false;

		if ($options['history'] == ITEM_VALUE_TYPE_LOG || $options['history'] == ITEM_VALUE_TYPE_TEXT) {
			$this->sortColumns['id'] = 'id';
			$isIdFieldUsed = true;
		}

		$sqlParts = parent::applyQuerySortOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($isIdFieldUsed) {
			unset($this->sortColumns['id']);
		}

		return $sqlParts;
	}
}

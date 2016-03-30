<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
 * File containing CHistory class for API.
 * @package API
 */
/**
 * Class containing methods for operations with History of Items
 *
 */
class CHistory extends CZBXAPI {

	public function __construct() {
		// considering the quirky nature of the history API,
		// the parent::__construct() method should not be called.
	}

	/**
	 * Get history data
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param boolean $options['editable']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 * @return array|int item data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$nodeCheck = false;

		// allowed columns for sorting
		$sortColumns = array('itemid', 'clock');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

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
			'triggerids'				=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'groupOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		switch ($options['history']) {
			case ITEM_VALUE_TYPE_LOG:
				$sqlParts['from']['history'] = 'history_log h';
				$sortColumns[] = 'id';
				break;
			case ITEM_VALUE_TYPE_TEXT:
				$sqlParts['from']['history'] = 'history_text h';
				$sortColumns[] = 'id';
				break;
			case ITEM_VALUE_TYPE_STR:
				$sqlParts['from']['history'] = 'history_str h';
				break;
			case ITEM_VALUE_TYPE_UINT64:
				$sqlParts['from']['history'] = 'history_uint h';
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			default:
				$sqlParts['from']['history'] = 'history h';
		}

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == self::$userData['type'] || $options['nopermissions']) {
		}
		else {
			$itemOptions = array(
				'editable' => $options['editable'],
				'preservekeys' => true,
				'webitems' => true
			);
			if (!is_null($options['itemids'])) {
				$itemOptions['itemids'] = $options['itemids'];
			}
			$items = API::Item()->get($itemOptions);
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
				$sqlParts['where'][] = DBin_node('h.itemid', $nodeids);
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['hostid'] = 'i.hostid';
			}
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['hi'] = 'h.itemid=i.itemid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('i.hostid', $nodeids);
			}
		}

		// should be last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('h.itemid', $nodeids);
		}

		// time_from
		if (!is_null($options['time_from'])) {
			$sqlParts['select']['clock'] = 'h.clock';
			$sqlParts['where']['clock_from'] = 'h.clock>='.zbx_dbstr($options['time_from']);
		}

		// time_till
		if (!is_null($options['time_till'])) {
			$sqlParts['select']['clock'] = 'h.clock';
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

		// groupOutput
		$groupOutput = false;
		if (!is_null($options['groupOutput'])) {
			if (str_in_array('h.'.$options['groupOutput'], $sqlParts['select']) || str_in_array('h.*', $sqlParts['select'])) {
				$groupOutput = true;
			}
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'h');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$itemids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.
					$sqlWhere.
					$sqlOrder;
		$dbRes = DBselect($sql, $sqlLimit);
		$count = 0;
		$group = array();
		while ($data = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				$result = $data;
			}
			else {
				$itemids[$data['itemid']] = $data['itemid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$count] = array('itemid' => $data['itemid']);
				}
				else {
					$result[$count] = array();

					// hostids
					if (isset($data['hostid'])) {
						if (!isset($result[$count]['hosts'])) {
							$result[$count]['hosts'] = array();
						}
						$result[$count]['hosts'][] = array('hostid' => $data['hostid']);
						unset($data['hostid']);
					}

					// triggerids
					if (isset($data['triggerid'])) {
						if (!isset($result[$count]['triggers'])) {
							$result[$count]['triggers'] = array();
						}
						$result[$count]['triggers'][] = array('triggerid' => $data['triggerid']);
						unset($data['triggerid']);
					}
					$result[$count] += $data;

					// grouping
					if ($groupOutput) {
						$dataid = $data[$options['groupOutput']];
						if (!isset($group[$dataid])) {
							$group[$dataid] = array();
						}
						$group[$dataid][] = $result[$count];
					}
					$count++;
				}
			}
		}

		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	public function create($items = array()) {
	}

	public function delete($itemids = array()) {
	}
}

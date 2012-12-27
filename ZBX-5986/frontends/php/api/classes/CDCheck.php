<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
/**
 * File containing CDCheck class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Discovery checks for discovery rules
 */
class CDCheck extends CZBXAPI {

	protected $tableName = 'dchecks';

	protected $tableAlias = 'dc';

	public function get($options) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];

		// allowed columns for sorting
		$sortColumns = array('dcheckid', 'druleid');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sqlParts = array(
			'select'	=> array('dchecks' => 'dc.dcheckid'),
			'from'		=> array('dchecks' => 'dchecks dc'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'dcheckids'					=> null,
			'druleids'					=> null,
			'dhostids'					=> null,
			'dserviceids'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectDRules'				=> null,
			'selectDHosts'				=> null,
			'selectHosts'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['dchecks']);

			$dbTable = DB::getSchema('dchecks');
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'dc.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType) {
		}
		elseif (is_null($options['editable']) && (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN)) {
		}
		elseif (!is_null($options['editable']) && (self::$userData['type']!=USER_TYPE_SUPER_ADMIN)) {
			return array();
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// dcheckids
		if (!is_null($options['dcheckids'])) {
			zbx_value2array($options['dcheckids']);
			$sqlParts['where']['dcheckid'] = dbConditionInt('dc.dcheckid', $options['dcheckids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dc.dcheckid', $nodeids);
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['druleid'] = 'dc.druleid';
			}

			$sqlParts['where'][] = dbConditionInt('dc.druleid', $options['druleids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['druleid'] = 'dc.druleid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dc.druleid', $nodeids);
			}
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['dservices'] = 'dh.dhostid';
			}

			$sqlParts['from']['dhosts'] = 'dhosts dh';

			$sqlParts['where']['dh'] = dbConditionInt('dh.dhostid', $options['dhostids']);
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dhostid'] = 'dh.dhostid';
			}
		}


// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['dserviceid'] = 'ds.dserviceid';
			}

			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dservices'] = 'dservices ds';

			$sqlParts['where']['ds'] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';
			$sqlParts['where']['dhds'] = 'dh.hostid=ds.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}

// node check !!!!!
// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('dc.dcheckid', $nodeids);
		}


// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['dchecks'] = 'dc.*';
		}

// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT dc.dcheckid) as rowscount');

//groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('dchecks dc', $options, $sqlParts);
		}

// search
		if (is_array($options['search'])) {
			zbx_db_search('dchecks dc', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'dc');

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------


		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['group'] = array_unique($sqlParts['group']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlGroup = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select']))	$sqlSelect.= implode(',', $sqlParts['select']);
		if (!empty($sqlParts['from']))		$sqlFrom.= implode(',', $sqlParts['from']);
		if (!empty($sqlParts['where']))		$sqlWhere.= implode(' AND ', $sqlParts['where']);
		if (!empty($sqlParts['group']))		$sqlWhere.= ' GROUP BY '.implode(',', $sqlParts['group']);
		if (!empty($sqlParts['order']))		$sqlOrder.= ' ORDER BY '.implode(',', $sqlParts['order']);
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.$sqlWhere.
				$sqlGroup.
				$sqlOrder;
 //SDI($sql);
		$res = DBselect($sql, $sqlLimit);
		while ($dcheck = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dcheck;
				else
					$result = $dcheck['rowscount'];
			}
			else{
				$dcheckids[$dcheck['dcheckid']] = $dcheck['dcheckid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$dcheck['dcheckid']] = array('dcheckid' => $dcheck['dcheckid']);
				}
				else{
					if (!isset($result[$dcheck['dcheckid']])) $result[$dcheck['dcheckid']]= array();

					if (!is_null($options['selectDRules']) && !isset($result[$dcheck['dcheckid']]['drules'])) {
						$result[$dcheck['dcheckid']]['drules'] = array();
					}

					if (!is_null($options['selectDHosts']) && !isset($result[$dcheck['dcheckid']]['dhosts'])) {
						$result[$dcheck['dcheckid']]['dhosts'] = array();
					}

					if (!is_null($options['selectHosts']) && !isset($result[$dcheck['dcheckid']]['hosts'])) {
						$result[$dcheck['dcheckid']]['hosts'] = array();
					}
// druleids
					if (isset($dcheck['druleid']) && is_null($options['selectDRules'])) {
						if (!isset($result[$dcheck['dcheckid']]['drules']))
							$result[$dcheck['dcheckid']]['drules'] = array();

						$result[$dcheck['dcheckid']]['drules'][] = array('druleid' => $dcheck['druleid']);
					}
// dhostids
					if (isset($dcheck['dhostid']) && is_null($options['selectDHosts'])) {
						if (!isset($result[$dcheck['dcheckid']]['dhosts']))
							$result[$dcheck['dcheckid']]['dhosts'] = array();

						$result[$dcheck['dcheckid']]['dhosts'][] = array('dhostid' => $dcheck['dhostid']);
					}

					$result[$dcheck['dcheckid']] += $dcheck;
				}
			}
		}


		if (!is_null($options['countOutput'])) {
			return $result;
		}

// Adding Objects
// select_drules
		if (!is_null($options['selectDRules'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'dcheckids' => $dcheckids,
				'preservekeys' => 1
			);

			if (is_array($options['selectDRules']) || str_in_array($options['selectDRules'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectDRules'];
				$drules = API::DRule()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($drules, 'name');
				foreach ($drules as $druleid => $drule) {
					unset($drules[$druleid]['dchecks']);
					$count = array();
					foreach ($drule['dchecks'] as $dnum => $dcheck) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dcheck['dcheckid']])) $count[$dcheck['dcheckid']] = 0;
							$count[$dcheck['dcheckid']]++;

							if ($count[$dcheck['dcheckid']] > $options['limitSelects']) continue;
						}

						$result[$dcheck['dcheckid']]['drules'][] = &$drules[$druleid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectDRules']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$drules = API::DRule()->get($objParams);
				$drules = zbx_toHash($drules, 'dcheckid');
				foreach ($result as $dcheckid => $dcheck) {
					if (isset($drules[$dcheckid]))
						$result[$dcheckid]['drules'] = $drules[$dcheckid]['rowscount'];
					else
						$result[$dcheckid]['drules'] = 0;
				}
			}
		}

// selectDHosts
		if (!is_null($options['selectDHosts'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'dcheckids' => $dcheckids,
				'preservekeys' => 1
			);

			if (is_array($options['selectDHosts']) || str_in_array($options['selectDHosts'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectDHosts'];
				$dhosts = API::DHost()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($dhosts, 'dhostid');
				foreach ($dhosts as $dhostid => $dhost) {
					unset($dhosts[$dhostid]['dchecks']);
					foreach ($dhost['dchecks'] as $snum => $dcheck) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dcheck['dcheckid']])) $count[$dcheck['dcheckid']] = 0;
							$count[$dcheck['dcheckid']]++;

							if ($count[$dcheck['dcheckid']] > $options['limitSelects']) continue;
						}

						$result[$dcheck['dcheckid']]['dhosts'][] = &$dhosts[$dhostid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectDHosts']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$dhosts = API::DHost()->get($objParams);
				$dhosts = zbx_toHash($dhosts, 'dhostid');
				foreach ($result as $dcheckid => $dcheck) {
					if (isset($dhosts[$dcheckid]))
						$result[$dcheckid]['dhosts'] = $dhosts[$dcheckid]['rowscount'];
					else
						$result[$dcheckid]['dhosts'] = 0;
				}
			}
		}

// selectHosts
		if (!is_null($options['selectHosts'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'dcheckids' => $dcheckids,
				'preservekeys' => 1,
				'sortfield' => 'status'
			);

			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectHosts'];
				$hosts = API::Host()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($hosts, 'hostid');

				foreach ($hosts as $hostid => $host) {
					unset($hosts[$hostid]['dchecks']);
					foreach ($host['dchecks'] as $dnum => $dcheck) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dcheck['dcheckid']])) $count[$dcheck['dcheckid']] = 0;
							$count[$dcheck['dcheckid']]++;

							if ($count[$dcheck['dcheckid']] > $options['limitSelects']) continue;
						}

						$result[$dcheck['dcheckid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectHosts']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$hosts = API::Host()->get($objParams);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach ($result as $dcheckid => $dcheck) {
					if (isset($hosts[$dcheckid]))
						$result[$dcheckid]['hosts'] = $hosts[$dcheckid]['rowscount'];
					else
						$result[$dcheckid]['hosts'] = 0;
				}
			}
		}


// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	/**
	 * Check if user has read permissions for discovery checks.
	 *
	 * @param array $ids
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'dcheckids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Check if user has write permissions for discovery checks.
	 *
	 * @param array $ids
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'dcheckids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}
}
?>

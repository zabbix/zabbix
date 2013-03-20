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
 * File containing CCService class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Discovered Services
 */
class CDService extends CZBXAPI{

	protected $tableName = 'dservices';

	protected $tableAlias = 'ds';

/**
 * Get Service data
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['groupids'] ServiceGroup IDs
 * @param array $options['hostids'] Service IDs
 * @param boolean $options['monitored_hosts'] only monitored Services
 * @param boolean $options['templated_hosts'] include templates in result
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['with_historical_items'] only with historical items
 * @param boolean $options['with_triggers'] only with triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param boolean $options['selectGroups'] select ServiceGroups
 * @param boolean $options['selectTemplates'] select Templates
 * @param boolean $options['selectItems'] select Items
 * @param boolean $options['selectTriggers'] select Triggers
 * @param boolean $options['selectGraphs'] select Graphs
 * @param boolean $options['selectApplications'] select Applications
 * @param boolean $options['selectMacros'] select Macros
 * @param int $options['count'] count Services, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in Service name
 * @param string $options['extendPattern'] search hosts by pattern in Service name, ip and DNS
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Service data as array or false if error
 */
	public function get($options = array()) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];

		// allowed columns for sorting
		$sortColumns = array('dserviceid', 'dhostid', 'ip');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sqlParts = array(
			'select'	=> array('dservices' => 'ds.dserviceid'),
			'from'		=> array('dservices' => 'dservices ds'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'dserviceids'				=> null,
			'dhostids'					=> null,
			'dcheckids'					=> null,
			'druleids'					=> null,
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
			'selectDChecks'				=> null,
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
			unset($sqlParts['select']['dservices']);

			$dbTable = DB::getSchema('dservices');
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 's.'.$field;
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

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);
			$sqlParts['where']['dserviceid'] = dbConditionInt('ds.dserviceid', $options['dserviceids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('ds.dserviceid', $nodeids);
			}
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['dhostid'] = 'ds.dhostid';
			}

			$sqlParts['where'][] = dbConditionInt('ds.dhostid', $options['dhostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dhostid'] = 'ds.dhostid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('ds.dhostid', $nodeids);
			}
		}


// dcheckids
		if (!is_null($options['dcheckids'])) {
			zbx_value2array($options['dcheckids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['dcheckid'] = 'dc.dcheckid';
			}

			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dchecks'] = 'dchecks dc';

			$sqlParts['where'][] = dbConditionInt('dc.dcheckid', $options['dcheckids']);
			$sqlParts['where']['dhds'] = 'dh.hostid=ds.hostid';
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dcheckid'] = 'dc.dcheckid';
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['druleid'] = 'dh.druleid';
			}

			$sqlParts['from']['dhosts'] = 'dhosts dh';

			$sqlParts['where']['druleid'] = dbConditionInt('dh.druleid', $options['druleids']);
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['druleid'] = 'dh.druleid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dh.druleid', $nodeids);
			}
		}

// node check !!!!!
// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('ds.dserviceid', $nodeids);
		}


// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['dservices'] = 'ds.*';
		}

// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT ds.dserviceid) as rowscount');

//groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('dservices ds', $options, $sqlParts);
		}

// search
		if (is_array($options['search'])) {
			zbx_db_search('dservices ds', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'ds');

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------


		$dserviceids = array();

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
		while ($dservice = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dservice;
				else
					$result = $dservice['rowscount'];
			}
			else{
				$dserviceids[$dservice['dserviceid']] = $dservice['dserviceid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$dservice['dserviceid']] = array('dserviceid' => $dservice['dserviceid']);
				}
				else{
					if (!isset($result[$dservice['dserviceid']])) $result[$dservice['dserviceid']]= array();

					if (!is_null($options['selectDRules']) && !isset($result[$dservice['dserviceid']]['drules'])) {
						$result[$dservice['dserviceid']]['drules'] = array();
					}

					if (!is_null($options['selectDHosts']) && !isset($result[$dservice['dserviceid']]['dhosts'])) {
						$result[$dservice['dserviceid']]['dhosts'] = array();
					}

					if (!is_null($options['selectDChecks']) && !isset($result[$dservice['dserviceid']]['dchecks'])) {
						$result[$dservice['dserviceid']]['dchecks'] = array();
					}

					if (!is_null($options['selectHosts']) && !isset($result[$dservice['dserviceid']]['hosts'])) {
						$result[$dservice['dserviceid']]['hosts'] = array();
					}
// druleids
					if (isset($dservice['druleid']) && is_null($options['selectDRules'])) {
						if (!isset($result[$dservice['dserviceid']]['drules']))
							$result[$dservice['dserviceid']]['drules'] = array();

						$result[$dservice['dserviceid']]['drules'][] = array('druleid' => $dservice['druleid']);
					}
// dhostids
					if (isset($dservice['dhostid']) && is_null($options['selectDHosts'])) {
						if (!isset($result[$dservice['dserviceid']]['dhosts']))
							$result[$dservice['dserviceid']]['dhosts'] = array();

						$result[$dservice['dserviceid']]['dhosts'][] = array('dhostid' => $dservice['dhostid']);
					}
// dcheckids
					if (isset($dservice['dcheckid']) && is_null($options['selectDChecks'])) {
						if (!isset($result[$dservice['dserviceid']]['dchecks']))
							$result[$dservice['dserviceid']]['dchecks'] = array();

						$result[$dservice['dserviceid']]['dchecks'][] = array('dcheckid' => $dservice['dcheckid']);
					}

					$result[$dservice['dserviceid']] += $dservice;
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
				'dserviceids' => $dserviceids,
				'preservekeys' => 1
			);

			if (is_array($options['selectDRules']) || str_in_array($options['selectDRules'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectDRules'];
				$drules = API::DRule()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($drules, 'name');
				foreach ($drules as $druleid => $drule) {
					unset($drules[$druleid]['dservices']);
					$count = array();
					foreach ($drule['dservices'] as $dnum => $dservice) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dservice['dserviceid']])) $count[$dservice['dserviceid']] = 0;
							$count[$dservice['dserviceid']]++;

							if ($count[$dservice['dserviceid']] > $options['limitSelects']) continue;
						}

						$result[$dservice['dserviceid']]['drules'][] = &$drules[$druleid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectDRules']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$drules = API::DRule()->get($objParams);
				$drules = zbx_toHash($drules, 'dserviceid');
				foreach ($result as $dserviceid => $dservice) {
					if (isset($drules[$dserviceid]))
						$result[$dserviceid]['drules'] = $drules[$dserviceid]['rowscount'];
					else
						$result[$dserviceid]['drules'] = 0;
				}
			}
		}

// selectDHosts
		if (!is_null($options['selectDHosts'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'dserviceids' => $dserviceids,
				'preservekeys' => 1
			);

			if (is_array($options['selectDHosts']) || str_in_array($options['selectDHosts'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectDHosts'];
				$dhosts = API::DHost()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($dhosts, 'dhostid');
				foreach ($dhosts as $dhostid => $dhost) {
					unset($dhosts[$dhostid]['dservices']);
					foreach ($dhost['dservices'] as $snum => $dservice) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dservice['dserviceid']])) $count[$dservice['dserviceid']] = 0;
							$count[$dservice['dserviceid']]++;

							if ($count[$dservice['dserviceid']] > $options['limitSelects']) continue;
						}

						$result[$dservice['dserviceid']]['dhosts'][] = &$dhosts[$dhostid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectDHosts']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$dhosts = API::DHost()->get($objParams);
				$dhosts = zbx_toHash($dhosts, 'dhostid');
				foreach ($result as $dserviceid => $dservice) {
					if (isset($dhosts[$dserviceid]))
						$result[$dserviceid]['dhosts'] = $dhosts[$dserviceid]['rowscount'];
					else
						$result[$dserviceid]['dhosts'] = 0;
				}
			}
		}

// selectHosts
		if (!is_null($options['selectHosts'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'dserviceids' => $dserviceids,
				'preservekeys' => 1,
				'sortfield' => 'status'
			);

			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectHosts'];
				$hosts = API::Host()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($hosts, 'hostid');

				foreach ($hosts as $hostid => $host) {
					unset($hosts[$hostid]['dservices']);
					foreach ($host['dservices'] as $dnum => $dservice) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dservice['dserviceid']])) $count[$dservice['dserviceid']] = 0;
							$count[$dservice['dserviceid']]++;

							if ($count[$dservice['dserviceid']] > $options['limitSelects']) continue;
						}

						$result[$dservice['dserviceid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectHosts']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$hosts = API::Host()->get($objParams);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach ($result as $dserviceid => $dservice) {
					if (isset($hosts[$dserviceid]))
						$result[$dserviceid]['hosts'] = $hosts[$dserviceid]['rowscount'];
					else
						$result[$dserviceid]['hosts'] = 0;
				}
			}
		}

// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public function exists($object) {
		$keyFields = array(array('dserviceid'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if (isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		elseif (isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

	return !empty($objs);
	}

/**
 * Add Service
 */
	public function create($dservices) {

	}

/**
 * Update DService
 */
	public function update($dservices) {

	}

/**
 * Delete Discovered Service
 */
	public function delete($dservices) {

	}

}
?>

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
?>
<?php
/**
 * File containing CCHost class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Discovered Hosts
 */
class CDHost extends CZBXAPI {

	protected $tableName = 'dhosts';

	protected $tableAlias = 'dh';

/**
 * Get Host data
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['groupids'] HostGroup IDs
 * @param array $options['hostids'] Host IDs
 * @param boolean $options['monitored_hosts'] only monitored Hosts
 * @param boolean $options['templated_hosts'] include templates in result
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['with_historical_items'] only with historical items
 * @param boolean $options['with_triggers'] only with triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param boolean $options['selectGroups'] select HostGroups
 * @param boolean $options['selectTemplates'] select Templates
 * @param boolean $options['selectItems'] select Items
 * @param boolean $options['selectTriggers'] select Triggers
 * @param boolean $options['selectGraphs'] select Graphs
 * @param boolean $options['selectApplications'] select Applications
 * @param boolean $options['selectMacros'] select Macros
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in Host name
 * @param string $options['extendPattern'] search hosts by pattern in Host name, ip and DNS
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Host data as array or false if error
 */
	public function get($options = array()) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];

		// allowed columns for sorting
		$sortColumns = array('dhostid', 'druleid');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sqlParts = array(
			'select'	=> array('dhosts' => 'dh.dhostid'),
			'from'		=> array('dhosts' => 'dhosts dh'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'druleids'					=> null,
			'dhostids'					=> null,
			'dserviceids'				=> null,
			'groupids'					=> null,
			'hostids'					=> null,
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
			'selectDServices'			=> null,
			'selectGroups'				=> null,
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
			unset($sqlParts['select']['dhosts']);

			$dbTable = DB::getSchema('dhosts');
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'dh.'.$field;
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

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);
			$sqlParts['where']['dhostid'] = dbConditionInt('dh.dhostid', $options['dhostids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dh.dhostid', $nodeids);
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['druleid'] = 'dh.druleid';
			}

			$sqlParts['where']['druleid'] = dbConditionInt('dh.druleid', $options['druleids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['druleid'] = 'dh.druleid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dh.druleid', $nodeids);
			}
		}

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['dserviceids'] = 'ds.dserviceids';
			}

			$sqlParts['from']['dservices'] = 'dservices ds';
			$sqlParts['where'][] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceids'] = 'ds.dserviceid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('ds.dserviceid', $nodeids);
			}
		}

// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['groupid'] = 'hg.groupid';
			}

			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['dhh'] = 'h.ip=dh.ip';
			$sqlParts['where']['hgh'] = 'hg.hostid=h.hostid';


			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['groupid'] = 'hg.groupid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('hg.groupid', $nodeids);
			}
		}

// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['hostid'] = 'h.hostid';
			}

			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where'][] = dbConditionInt('h.hostid', $options['hostids']);
			$sqlParts['where']['dhh'] = 'h.ip=dh.ip';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'h.hostid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('h.hostid', $nodeids);
			}
		}

// node check !!!!!
// should be last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('dh.dhostid', $nodeids);
		}

// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['dhosts'] = 'dh.*';
		}

// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT dh.dhostid) as rowscount');

//groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('dhosts dh', $options, $sqlParts);
		}

// search
		if (is_array($options['search'])) {
			zbx_db_search('dhosts dh', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'dh');

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------


		$dhostids = array();

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
		while ($dhost = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dhost;
				else
					$result = $dhost['rowscount'];
			}
			else{
				$dhostids[$dhost['dhostid']] = $dhost['dhostid'];
//				$dips[$dhost['ip']] = $dhost['ip'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$dhost['dhostid']] = array('dhostid' => $dhost['dhostid']);
				}
				else{
					if (!isset($result[$dhost['dhostid']])) $result[$dhost['dhostid']]= array();

					if (!is_null($options['selectDRules']) && !isset($result[$dhost['dhostid']]['drules'])) {
						$result[$dhost['dhostid']]['drules'] = array();
					}

					if (!is_null($options['selectDServices']) && !isset($result[$dhost['dhostid']]['dservices'])) {
						$result[$dhost['dhostid']]['dservices'] = array();
					}

					if (!is_null($options['selectGroups']) && !isset($result[$dhost['dhostid']]['groups'])) {
						$result[$dhost['dhostid']]['groups'] = array();
					}

					if (!is_null($options['selectHosts']) && !isset($result[$dhost['dhostid']]['hosts'])) {
						$result[$dhost['dhostid']]['hosts'] = array();
					}

// druleids
					if (isset($dhost['druleid']) && is_null($options['selectDRules'])) {
						if (!isset($result[$dhost['dhostid']]['drules']))
							$result[$dhost['dhostid']]['drules'] = array();

						$result[$dhost['dhostid']]['drules'][] = array('druleid' => $dhost['druleid']);
					}
// dserviceids
					if (isset($dhost['dserviceid']) && is_null($options['selectDServices'])) {
						if (!isset($result[$dhost['dhostid']]['dservices']))
							$result[$dhost['dhostid']]['dservices'] = array();

						$result[$dhost['dhostid']]['dservices'][] = array('dserviceid' => $dhost['dserviceid']);
						unset($dhost['dserviceid']);
					}
// groupids
					if (isset($dhost['groupid']) && is_null($options['selectGroups'])) {
						if (!isset($result[$dhost['dhostid']]['groups']))
							$result[$dhost['dhostid']]['groups'] = array();

						$result[$dhost['dhostid']]['groups'][] = array('groupid' => $dhost['groupid']);
						unset($dhost['groupid']);
					}

// hostids
					if (isset($dhost['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$dhost['hostid']]['hosts']))
							$result[$dhost['dhostid']]['hosts'] = array();

						$result[$dhost['dhostid']]['hosts'][] = array('hostid' => $dhost['hostid']);
						unset($dhost['hostid']);
					}

					$result[$dhost['dhostid']] += $dhost;
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
				'dhostids' => $dhostids,
				'preservekeys' => 1
			);

			if (is_array($options['selectDRules']) || str_in_array($options['selectDRules'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectDRules'];
				$drules = API::DRule()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($drules, 'name');
				foreach ($drules as $druleid => $drule) {
					unset($drules[$druleid]['dhosts']);
					$count = array();
					foreach ($drule['dhosts'] as $dnum => $dhost) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dhost['dhostid']])) $count[$dhost['dhostid']] = 0;
							$count[$dhost['dhostid']]++;

							if ($count[$dhost['dhostid']] > $options['limitSelects']) continue;
						}

						$result[$dhost['dhostid']]['drules'][] = &$drules[$druleid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectDRules']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$drules = API::DRule()->get($objParams);
				$drules = zbx_toHash($drules, 'dhostid');
				foreach ($result as $dhostid => $dhost) {
					if (isset($drules[$dhostid]))
						$result[$dhostid]['drules'] = $drules[$dhostid]['rowscount'];
					else
						$result[$dhostid]['drules'] = 0;
				}
			}
		}

// selectDServices
		if (!is_null($options['selectDServices'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'dhostids' => $dhostids,
				'preservekeys' => 1
			);

			if (is_array($options['selectDServices']) || str_in_array($options['selectDServices'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectDServices'];
				$dservices = API::DService()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($dservices, 'name');
				foreach ($dservices as $dserviceid => $dservice) {
					unset($dservices[$dserviceid]['dhosts']);
					foreach ($dservice['dhosts'] as $dnum => $dhost) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dhost['dhostid']])) $count[$dhost['dhostid']] = 0;
							$count[$dhost['dhostid']]++;

							if ($count[$dhost['dhostid']] > $options['limitSelects']) continue;
						}

						$result[$dhost['dhostid']]['dservices'][] = &$dservices[$dserviceid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectDServices']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$dservices = API::DService()->get($objParams);
				$dservices = zbx_toHash($dservices, 'dhostid');
				foreach ($result as $dhostid => $dhost) {
					if (isset($dservices[$dhostid]))
						$result[$dhostid]['dservices'] = $dservices[$dhostid]['rowscount'];
					else
						$result[$dhostid]['dservices'] = 0;
				}
			}
		}

// TODO :selectGroups
		if (!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselectsAllowedOutputs)) {
			$objParams = array(
					'nodeids' => $nodeids,
					'output' => $options['selectGroups'],
					'hostids' => $dhostids,
					'preservekeys' => 1
				);
			$groups = API::HostGroup()->get($objParams);

			foreach ($groups as $groupid => $group) {
				$ghosts = $group['hosts'];
				unset($group['hosts']);
				foreach ($ghosts as $num => $dhost) {
					$result[$dhost['hostid']]['groups'][] = $group;
				}
			}
		}

// selectHosts
		if (!is_null($options['selectHosts'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'dhostids' => $dhostids,
				'preservekeys' => 1
			);

			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectHosts'];
				$hosts = API::Host()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($hosts, 'host');

				foreach ($hosts as $hostid => $host) {
					unset($hosts[$hostid]['dhosts']);
					foreach ($host['dhosts'] as $dnum => $dhost) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$dhost['dhostid']])) $count[$dhost['dhostid']] = 0;
							$count[$dhost['dhostid']]++;

							if ($count[$dhost['dhostid']] > $options['limitSelects']) continue;
						}

						$result[$dhost['dhostid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectHosts']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$hosts = API::Host()->get($objParams);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach ($result as $dhostid => $dhost) {
					if (isset($hosts[$dhostid]))
						$result[$dhostid]['hosts'] = $hosts[$dhostid]['rowscount'];
					else
						$result[$dhostid]['hosts'] = 0;
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
		$keyFields = array(array('dhostid'));

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
 * Add Host
 *
 * @param _array $dhosts multidimensional array with Hosts data
 */
	public function create($dhosts) {

	}

/**
 * Update DHost
 *
 * @param _array $dhosts multidimensional array with Hosts data
 */
	public function update($dhosts) {

	}

/**
 * Delete Discovered Host
 *
 * @param array $dhosts
 * @param array $dhosts[0, ...]['hostid'] Host ID to delete
 * @return array|boolean
 */
	public function delete($dhostids) {

	}
}
?>

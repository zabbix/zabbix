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
 * @param boolean $options['with_monitored_items'] only with monitored items
 * @param boolean $options['with_historical_items'] only with historical items
 * @param boolean $options['with_triggers'] only with triggers
 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
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
			$sqlParts['where']['dserviceid'] = DBcondition('ds.dserviceid', $options['dserviceids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('ds.dserviceid', $nodeids);
			}
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);

			$sqlParts['select']['dhostid'] = 'ds.dhostid';
			$sqlParts['where'][] = DBcondition('ds.dhostid', $options['dhostids']);

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

			$sqlParts['select']['dcheckid'] = 'dc.dcheckid';
			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dchecks'] = 'dchecks dc';

			$sqlParts['where'][] = DBcondition('dc.dcheckid', $options['dcheckids']);
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dcheckid'] = 'dc.dcheckid';
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);

			$sqlParts['select']['druleid'] = 'dh.druleid';
			$sqlParts['from']['dhosts'] = 'dhosts dh';

			$sqlParts['where']['druleid'] = DBcondition('dh.druleid', $options['druleids']);
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

// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('dservices ds', $options, $sqlParts);
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

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($dservice = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dservice;
				else
					$result = $dservice['rowscount'];
			}
			else{
				$dserviceids[$dservice['dserviceid']] = $dservice['dserviceid'];

				if (!isset($result[$dservice['dserviceid']])) {
					$result[$dservice['dserviceid']]= array();
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

				$result[$dservice['dserviceid']] += $dservice;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// Adding Objects
		// select_drules
		if ($options['selectDRules'] !== null && $options['selectDRules'] != API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT ds.dserviceid,dh.druleid'.
					' FROM dservices ds,dhosts dh'.
					' WHERE '.DBcondition('ds.dserviceid', $dserviceids).
					' AND ds.dhostid=dh.dhostid'
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['dserviceid'], $rule['druleid']);
			}

			$drules = API::DRule()->get(array(
				'output' => $options['selectDRules'],
				'nodeids' => $nodeids,
				'druleids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			if (!is_null($options['limitSelects'])) {
				order_result($drules, 'name');
			}
			$result = $relationMap->mapMany($result, $drules, 'drules');
		}

		// selectDHosts
		if ($options['selectDHosts'] !== null && $options['selectDHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'dserviceid', 'dhostid');
			$dhosts = API::DHost()->get(array(
				'output' => $options['selectDHosts'],
				'nodeids' => $nodeids,
				'dhosts' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			if (!is_null($options['limitSelects'])) {
				order_result($dhosts, 'dhostid');
			}
			$result = $relationMap->mapMany($result, $dhosts, 'dhosts', $options['limitSelects']);
		}

		// selectHosts
		if (!is_null($options['selectHosts'])) {
			if ($options['selectHosts'] != API_OUTPUT_COUNT) {
				$relationMap = new CRelationMap();
				// discovered items
				$dbRules = DBselect(
					'SELECT ds.dserviceid,i.hostid'.
						' FROM dservices ds,interface i'.
						' WHERE '.DBcondition('ds.dserviceid', $dserviceids).
						' AND ds.ip=i.ip'
				);
				while ($rule = DBfetch($dbRules)) {
					$relationMap->addRelation($rule['dserviceid'], $rule['hostid']);
				}

				$hosts = API::Host()->get(array(
					'output' => $options['selectHosts'],
					'nodeids' => $nodeids,
					'hostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true,
					'sortfield' => 'status'
				));
				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'hostid');
				}
				$result = $relationMap->mapMany($result, $hosts, 'hosts');
			}
			else {
				$hosts = API::Host()->get(array(
					'nodeids' => $nodeids,
					'dserviceids' => $dserviceids,
					'countOutput' => true,
					'groupCount' => true
				));
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
			'output' => array('dserviceid'),
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

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectDHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('ds.dhostid', $sqlParts);
			}
		}

		return $sqlParts;
	}

}
?>

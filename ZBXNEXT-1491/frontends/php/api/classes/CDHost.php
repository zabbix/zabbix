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
 * @param boolean $options['monitored_hosts'] only monitored Hosts
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

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);
			$sqlParts['where']['dhostid'] = DBcondition('dh.dhostid', $options['dhostids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dh.dhostid', $nodeids);
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);

			$sqlParts['select']['druleid'] = 'dh.druleid';
			$sqlParts['where']['druleid'] = DBcondition('dh.druleid', $options['druleids']);

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

			$sqlParts['select']['dserviceids'] = 'ds.dserviceid';
			$sqlParts['from']['dservices'] = 'dservices ds';
			$sqlParts['where'][] = DBcondition('ds.dserviceid', $options['dserviceids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceids'] = 'ds.dserviceid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('ds.dserviceid', $nodeids);
			}
		}

// node check !!!!!
// should be last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('dh.dhostid', $nodeids);
		}

// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('dhosts dh', $options, $sqlParts);
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

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		$relationMap = new CRelationMap();
		while ($dhost = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dhost;
				else
					$result = $dhost['rowscount'];
			}
			else{
				$dhostids[$dhost['dhostid']] = $dhost['dhostid'];

				if (!isset($result[$dhost['dhostid']])) {
					$result[$dhost['dhostid']]= array();
				}

				// populate relation map
				if (isset($dhost['druleid']) && $dhost['druleid']) {
					$relationMap->addRelation($dhost['dhostid'], 'drules', $dhost['druleid']);
				}
				if (isset($dhost['dserviceid']) && $dhost['dserviceid']) {
					$relationMap->addRelation($dhost['dhostid'], 'dservices', $dhost['dserviceid']);
				}
				unset($dhost['dserviceid']);

				$result[$dhost['dhostid']] += $dhost;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// Adding Objects
		// select_drules
		if ($options['selectDRules'] !== null && $options['selectDRules'] != API_OUTPUT_COUNT) {
			$drules = API::DRule()->get(array(
				'output' => $options['selectDRules'],
				'nodeids' => $nodeids,
				'druleids' => $relationMap->getRelatedIds('drules'),
				'preservekeys' => true
			));

			if (!is_null($options['limitSelects'])) {
				order_result($drules, 'name');
			}

			$result = $relationMap->mapMany($result, $drules, 'drules', $options['limitSelects']);
		}

		// selectDServices
		if (!is_null($options['selectDServices'])) {
			if ($options['selectDServices'] != API_OUTPUT_COUNT) {
				$dservices = API::DService()->get(array(
					'output' => $options['selectDServices'],
					'nodeids' => $nodeids,
					'dserviceids' => $relationMap->getRelatedIds('dservices'),
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($dservices, 'name');
				}
				$result = $relationMap->mapMany($result, $dservices, 'dservices', $options['limitSelects']);
			}
			else {
				$dservices = API::DService()->get(array(
					'output' => $options['selectDServices'],
					'nodeids' => $nodeids,
					'dhostids' => $dhostids,
					'countOutput' => true,
					'groupCount' => true
				));
				$dservices = zbx_toHash($dservices, 'dhostid');
				foreach ($result as $dhostid => $dhost) {
					if (isset($dservices[$dhostid]))
						$result[$dhostid]['dservices'] = $dservices[$dhostid]['rowscount'];
					else
						$result[$dhostid]['dservices'] = 0;
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
			'output' => array('dhostid'),
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

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectDRules'] !== null) {
				$sqlParts = $this->addQuerySelect('dh.druleid', $sqlParts);
			}

			if ($options['selectDServices'] !== null && $options['selectDServices'] != API_OUTPUT_COUNT) {
				$sqlParts = $this->addQueryLeftJoin('dservices ds', 'dh.dhostid', 'ds.dhostid', $sqlParts);
				$sqlParts = $this->addQuerySelect('ds.dserviceid', $sqlParts);
			}
		}

		return $sqlParts;
	}
}
?>

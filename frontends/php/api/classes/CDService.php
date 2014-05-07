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
 * Class containing methods for operations with discovery services.
 *
 * @package API
 */
class CDService extends CApiService {

	protected $tableName = 'dservices';
	protected $tableAlias = 'ds';
	protected $sortColumns = array('dserviceid', 'dhostid', 'ip');

	/**
	 * Get discovery service data.
	 *
	 * @param array  $options
	 * @param array  $options['groupids']				ServiceGroup IDs
	 * @param array  $options['hostids']				Service IDs
	 * @param bool   $options['monitored_hosts']		only monitored Services
	 * @param bool   $options['templated_hosts']		include templates in result
	 * @param bool   $options['with_items']				only with items
	 * @param bool   $options['with_triggers']			only with triggers
	 * @param bool   $options['with_httptests']			only with http tests
	 * @param bool   $options['with_graphs']			only with graphs
	 * @param bool   $options['editable']				only with read-write permission. Ignored for SuperAdmins
	 * @param bool   $options['selectGroups']			select ServiceGroups
	 * @param bool   $options['selectTemplates']		select Templates
	 * @param bool   $options['selectItems']			select Items
	 * @param bool   $options['selectTriggers']			select Triggers
	 * @param bool   $options['selectGraphs']			select Graphs
	 * @param int    $options['count']					count Services, returned column name is rowscount
	 * @param string $options['pattern']				search hosts by pattern in Service name
	 * @param string $options['extendPattern']			search hosts by pattern in Service name, ip and DNS
	 * @param int    $options['limit']					limit selection
	 * @param string $options['sortfield']				field to sort by
	 * @param string $options['sortorder']				sort order
	 *
	 * @return array									service data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];

		$sqlParts = array(
			'select'	=> array('dservices' => 'ds.dserviceid'),
			'from'		=> array('dservices' => 'dservices ds'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
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
			'output'					=> API_OUTPUT_EXTEND,
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

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);
			$sqlParts['where']['dserviceid'] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);

			$sqlParts['where'][] = dbConditionInt('ds.dhostid', $options['dhostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dhostid'] = 'ds.dhostid';
			}
		}

// dcheckids
		if (!is_null($options['dcheckids'])) {
			zbx_value2array($options['dcheckids']);

			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dchecks'] = 'dchecks dc';

			$sqlParts['where'][] = dbConditionInt('dc.dcheckid', $options['dcheckids']);
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dcheckid'] = 'dc.dcheckid';
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);

			$sqlParts['from']['dhosts'] = 'dhosts dh';

			$sqlParts['where']['druleid'] = dbConditionInt('dh.druleid', $options['druleids']);
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['druleid'] = 'dh.druleid';
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

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($dservice = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dservice;
				else
					$result = $dservice['rowscount'];
			}
			else {
				$result[$dservice['dserviceid']] = $dservice;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, array('dhostid'), $options['output']);
		}

// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	/**
	 * Check if discovered service exists.
	 *
	 * @deprecated	As of version 2.4, use get method instead.
	 *
	 * @param array	$object
	 *
	 * @return bool
	 */
	public function exists($object) {
		$this->deprecated('dservice.exists method is deprecated.');

		$objs = $this->get(array(
			'filter' => zbx_array_mintersect(array(array('dserviceid')), $object),
			'output' => array('dserviceid'),
			'limit' => 1
		));

		return !empty($objs);
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

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$dserviceIds = array_keys($result);

		// select_drules
		if ($options['selectDRules'] !== null && $options['selectDRules'] != API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT ds.dserviceid,dh.druleid'.
					' FROM dservices ds,dhosts dh'.
					' WHERE '.dbConditionInt('ds.dserviceid', $dserviceIds).
					' AND ds.dhostid=dh.dhostid'
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['dserviceid'], $rule['druleid']);
			}

			$drules = API::DRule()->get(array(
				'output' => $options['selectDRules'],
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
						' WHERE '.dbConditionInt('ds.dserviceid', $dserviceIds).
						' AND ds.ip=i.ip'
				);
				while ($rule = DBfetch($dbRules)) {
					$relationMap->addRelation($rule['dserviceid'], $rule['hostid']);
				}

				$hosts = API::Host()->get(array(
					'output' => $options['selectHosts'],
					'hostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true,
					'sortfield' => 'status'
				));
				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'hostid');
				}
				$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
			}
			else {
				$hosts = API::Host()->get(array(
					'dserviceids' => $dserviceIds,
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

		return $result;
	}
}

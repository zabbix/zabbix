<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * Class containing methods for operations with discovery hosts.
 *
 * @package API
 */
class CDHost extends CApiService {

	protected $tableName = 'dhosts';
	protected $tableAlias = 'dh';
	protected $sortColumns = ['dhostid', 'druleid'];

	/**
	 * Get host data.
	 *
	 * @param array  $options
	 * @param array  $options['groupids']				HostGroup IDs
	 * @param bool   $options['monitored_hosts']		only monitored Hosts
	 * @param bool   $options['templated_hosts']		include templates in result
	 * @param bool   $options['with_items']				only with items
	 * @param bool   $options['with_triggers']			only with triggers
	 * @param bool   $options['with_httptests']			only with http tests
	 * @param bool   $options['with_graphs']			only with graphs
	 * @param bool   $options['editable']				only with read-write permission. Ignored for SuperAdmins
	 * @param bool   $options['selectTemplates']		select Templates
	 * @param bool   $options['selectItems']			select Items
	 * @param bool   $options['selectTriggers']			select Triggers
	 * @param bool   $options['selectGraphs']			select Graphs
	 * @param int    $options['count']					count Hosts, returned column name is rowscount
	 * @param string $options['pattern']				search hosts by pattern in Host name
	 * @param string $options['extendPattern']			search hosts by pattern in Host name, ip and DNS
	 * @param int    $options['limit']					limit selection
	 * @param string $options['sortfield']				field to sort by
	 * @param string $options['sortorder']				sort order
	 *
	 * @return array									Host data as array or false if error
	 */
	public function get($options = []) {
		$result = [];
		$userType = self::$userData['type'];

		$sqlParts = [
			'select'	=> ['dhosts' => 'dh.dhostid'],
			'from'		=> ['dhosts' => 'dhosts dh'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
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
			'output'					=> API_OUTPUT_EXTEND,
			'selectDRules'				=> null,
			'selectDServices'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType) {
		}
		elseif (is_null($options['editable']) && (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN)) {
		}
		elseif (!is_null($options['editable']) && (self::$userData['type']!=USER_TYPE_SUPER_ADMIN)) {
			return [];
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);
			$sqlParts['where']['dhostid'] = dbConditionInt('dh.dhostid', $options['dhostids']);
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);

			$sqlParts['where']['druleid'] = dbConditionInt('dh.druleid', $options['druleids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['druleid'] = 'dh.druleid';
			}
		}

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['from']['dservices'] = 'dservices ds';
			$sqlParts['where'][] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceids'] = 'ds.dserviceid';
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

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($dhost = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dhost;
				else
					$result = $dhost['rowscount'];
			}
			else {
				$result[$dhost['dhostid']] = $dhost;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['druleid'], $options['output']);
		}

// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectDRules'] !== null) {
				$sqlParts = $this->addQuerySelect('dh.druleid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$dhostIds = array_keys($result);

		// select_drules
		if ($options['selectDRules'] !== null && $options['selectDRules'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'dhostid', 'druleid');
			$drules = API::DRule()->get([
				'output' => $options['selectDRules'],
				'druleids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);

			if (!is_null($options['limitSelects'])) {
				order_result($drules, 'name');
			}

			$result = $relationMap->mapMany($result, $drules, 'drules', $options['limitSelects']);
		}

		// selectDServices
		if (!is_null($options['selectDServices'])) {
			if ($options['selectDServices'] != API_OUTPUT_COUNT) {
				$dservices = API::DService()->get([
					'output' => $this->outputExtend($options['selectDServices'], ['dserviceid', 'dhostid']),
					'dhostids' => $dhostIds,
					'preservekeys' => true
				]);
				$relationMap = $this->createRelationMap($dservices, 'dhostid', 'dserviceid');

				$dservices = $this->unsetExtraFields($dservices, ['dserviceid', 'dhostid'], $options['selectDServices']);
				if (!is_null($options['limitSelects'])) {
					order_result($dservices, 'name');
				}
				$result = $relationMap->mapMany($result, $dservices, 'dservices', $options['limitSelects']);
			}
			else {
				$dservices = API::DService()->get([
					'output' => $options['selectDServices'],
					'dhostids' => $dhostIds,
					'countOutput' => true,
					'groupCount' => true
				]);
				$dservices = zbx_toHash($dservices, 'dhostid');
				foreach ($result as $dhostid => $dhost) {
					if (isset($dservices[$dhostid]))
						$result[$dhostid]['dservices'] = $dservices[$dhostid]['rowscount'];
					else
						$result[$dhostid]['dservices'] = 0;
				}
			}
		}

		return $result;
	}
}

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
 * Class containing methods for operations with discovery checks.
 *
 * @package API
 */
class CDCheck extends CZBXAPI {

	protected $tableName = 'dchecks';
	protected $tableAlias = 'dc';
	protected $sortColumns = array('dcheckid', 'druleid');

	public function get($options) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];

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

// dcheckids
		if (!is_null($options['dcheckids'])) {
			zbx_value2array($options['dcheckids']);
			$sqlParts['where']['dcheckid'] = dbConditionInt('dc.dcheckid', $options['dcheckids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'dc.dcheckid', $nodeids);
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);

			$sqlParts['where'][] = dbConditionInt('dc.druleid', $options['druleids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['druleid'] = 'dc.druleid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'dc.druleid', $nodeids);
			}
		}

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dservices'] = 'dservices ds';

			$sqlParts['where']['ds'] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';
			$sqlParts['where']['dhds'] = 'dh.hostid=ds.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}

		// node check !!!!
		// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'dc.dcheckid', $nodeids);
		}

// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('dchecks dc', $options, $sqlParts);
		}

// search
		if (is_array($options['search'])) {
			zbx_db_search('dchecks dc', $options, $sqlParts);
		}

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($dcheck = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dcheck;
				else
					$result = $dcheck['rowscount'];
			}
			else {
				$result[$dcheck['dcheckid']] = $dcheck;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, array('druleid'), $options['output']);
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
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectDRules'] !== null) {
				$sqlParts = $this->addQuerySelect('dc.druleid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// select_drules
		if ($options['selectDRules'] !== null && $options['selectDRules'] !== API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'dcheckid', 'druleid');
			$drules = API::DRule()->get(array(
				'output' => $options['selectDRules'],
				'druleids' => $relationMap->getRelatedIds(),
				'nodeids' => $options['nodeids'],
				'preservekeys' => 1
			));
			if (!is_null($options['limitSelects'])) {
				order_result($drules, 'name');
			}
			$result = $relationMap->mapMany($result, $drules, 'drules', $options['limitSelects']);
		}

		return $result;
	}
}

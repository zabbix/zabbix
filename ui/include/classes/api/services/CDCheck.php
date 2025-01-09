<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Class containing methods for operations with discovery checks.
 */
class CDCheck extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'dchecks';
	protected $tableAlias = 'dc';
	protected $sortColumns = ['dcheckid', 'druleid'];

	public function get($options) {
		$result = [];

		$sqlParts = [
			'select'	=> ['dchecks' => 'dc.dcheckid'],
			'from'		=> ['dchecks' => 'dchecks dc'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'dcheckids'					=> null,
			'druleids'					=> null,
			'dserviceids'				=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectDRules'				=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			return [];
		}

// dcheckids
		if (!is_null($options['dcheckids'])) {
			zbx_value2array($options['dcheckids']);
			$sqlParts['where']['dcheckid'] = dbConditionInt('dc.dcheckid', $options['dcheckids']);
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);

			$sqlParts['where'][] = dbConditionInt('dc.druleid', $options['druleids']);

			if ($options['groupCount']) {
				$sqlParts['group']['druleid'] = 'dc.druleid';
			}
		}

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dservices'] = 'dservices ds';

			$sqlParts['where']['ds'] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if ($options['groupCount']) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
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
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($dcheck = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $dcheck;
				}
				else {
					$result = $dcheck['rowscount'];
				}
			}
			else {
				$result[$dcheck['dcheckid']] = $dcheck;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['druleid'], $options['output']);
		}

// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
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

		return $result;
	}
}

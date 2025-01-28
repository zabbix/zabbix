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
 * Class containing methods for operations with value maps.
 */
class CValueMap extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'valuemap';
	protected $tableAlias = 'vm';
	protected $sortColumns = ['valuemapid', 'name'];

	/**
	 * Get value maps.
	 *
	 * @param array  $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'valuemapids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['valuemapid', 'hostid', 'name']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['valuemapid', 'uuid', 'name', 'hostid']), 'default' => API_OUTPUT_EXTEND],
			'selectMappings' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['type', 'value', 'newvalue']), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_valuemaps = [];
		$sql_parts = $this->createSelectQueryParts($this->tableName(), $this->tableAlias(), $options);

		// Permission check.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sql_parts['from'][] = 'host_hgset hh';
			$sql_parts['from'][] = 'permission p';
			$sql_parts['where'][] = 'vm.hostid=hh.hostid';
			$sql_parts['where'][] = 'hh.hgsetid=p.hgsetid';
			$sql_parts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sql_parts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			$sql_parts['where']['hostid'] = dbConditionInt('vm.hostid', $options['hostids']);
		}

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_valuemaps[$row['valuemapid']] = $row;
		}

		if ($db_valuemaps) {
			$db_valuemaps = $this->addRelatedObjects($options, $db_valuemaps);
			$db_valuemaps = $this->unsetExtraFields($db_valuemaps, ['valuemapid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_valuemaps = array_values($db_valuemaps);
			}
		}

		return $db_valuemaps;
	}

	/**
	 * @param array $valuemaps
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function create(array $valuemaps) {
		$this->validateCreate($valuemaps);

		$valuemapids = DB::insert('valuemap', $valuemaps);

		$mappings = [];

		foreach ($valuemaps as $index => &$valuemap) {
			$valuemap['valuemapid'] = $valuemapids[$index];
			$sortorder = 0;

			foreach ($valuemap['mappings'] as $mapping) {
				$mappings[] = [
					'type' => array_key_exists('type', $mapping) ? $mapping['type'] : VALUEMAP_MAPPING_TYPE_EQUAL,
					'valuemapid' => $valuemap['valuemapid'],
					'value' => array_key_exists('value', $mapping) ? $mapping['value'] : '',
					'newvalue' => $mapping['newvalue'],
					'sortorder' => $sortorder++
				];
			}
		}
		unset($valuemap);

		DB::insert('valuemap_mapping', $mappings);

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_VALUE_MAP, $valuemaps);

		return ['valuemapids' => $valuemapids];
	}

	/**
	 * @param array $valuemap
	 *
	 * @return array
	 */
	public function update(array $valuemaps) {
		$this->validateUpdate($valuemaps, $db_valuemaps);

		$upd_valuemaps = [];
		$valuemaps_mappings = [];

		foreach ($valuemaps as $valuemap) {
			$valuemapid = $valuemap['valuemapid'];

			$db_valuemap = $db_valuemaps[$valuemapid];

			if (array_key_exists('name', $valuemap) && $valuemap['name'] !== $db_valuemap['name']) {
				$upd_valuemaps[] = [
					'values' => ['name' => $valuemap['name']],
					'where' => ['valuemapid' => $valuemap['valuemapid']]
				];
			}

			if (array_key_exists('uuid', $valuemap) && $valuemap['uuid'] !== $db_valuemap['uuid']) {
				$upd_valuemaps[] = [
					'values' => ['uuid' => $valuemap['uuid'], 'name' => $valuemap['name']],
					'where' => ['valuemapid' => $valuemap['valuemapid']]
				];
			}

			if (array_key_exists('mappings', $valuemap)) {
				$valuemaps_mappings[$valuemapid] = [];
				$sortorder = 0;

				foreach ($valuemap['mappings'] as $mapping) {
					$mapping += ['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => ''];
					$valuemaps_mappings[$valuemapid][] = [
						'type' => $mapping['type'],
						'value' => $mapping['value'],
						'newvalue' => $mapping['newvalue'],
						'sortorder' => $sortorder++
					];
				}
			}
		}

		if ($upd_valuemaps) {
			DB::update('valuemap', $upd_valuemaps);
		}

		if ($valuemaps_mappings) {
			$db_mappings = DB::select('valuemap_mapping', [
				'output' => ['valuemap_mappingid', 'valuemapid', 'type', 'value', 'newvalue', 'sortorder'],
				'filter' => ['valuemapid' => array_keys($valuemaps_mappings)]
			]);
			CArrayHelper::sort($db_mappings, [['field' => 'sortorder', 'order' => ZBX_SORT_UP]]);

			$ins_mapings = [];
			$upd_mapings = [];
			$del_mapingids = [];
			$valuemapid_db_mappings = array_fill_keys(array_keys($valuemaps_mappings), []);

			foreach ($db_mappings as $db_mapping) {
				$valuemapid_db_mappings[$db_mapping['valuemapid']][] = $db_mapping;
			}

			foreach ($valuemaps_mappings as $valuemapid => $mappings) {
				$db_mappings = &$valuemapid_db_mappings[$valuemapid];

				foreach ($mappings as $mapping) {
					$exists = false;

					foreach ($db_mappings as $i => $db_mapping) {
						if ($db_mapping['type'] == $mapping['type'] && $db_mapping['value'] == $mapping['value']) {
							$exists = true;
							break;
						}
					}

					if (!$exists) {
						$ins_mapings[] = ['valuemapid' => $valuemapid] + $mapping;
						continue;
					}

					$update_fields = array_diff_assoc($mapping, $db_mapping);

					if ($update_fields) {
						$upd_mapings[] = [
							'values' => $update_fields,
							'where' => ['valuemap_mappingid' => $db_mapping['valuemap_mappingid']]
						];
					}

					unset($db_mappings[$i]);
				}
			}
			unset($db_mappings);

			foreach ($valuemapid_db_mappings as $db_mappings) {
				if ($db_mappings) {
					$del_mapingids = array_merge($del_mapingids, array_column($db_mappings, 'valuemap_mappingid'));
				}
			}

			if ($del_mapingids) {
				DB::delete('valuemap_mapping', ['valuemap_mappingid' => $del_mapingids]);
			}

			if ($upd_mapings) {
				DB::update('valuemap_mapping', $upd_mapings);
			}

			if ($ins_mapings) {
				DB::insert('valuemap_mapping', $ins_mapings);
			}
		}

		$this->addAuditBulk(CAudit::ACTION_UPDATE, CAudit::RESOURCE_VALUE_MAP, $valuemaps, $db_valuemaps);

		return ['valuemapids' => array_column($valuemaps, 'valuemapid')];
	}

	/**
	 * @param array $valuemapids
	 *
	 * @return array
	 */
	public function delete(array $valuemapids) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $valuemapids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_valuemaps = $this->get([
			'output' => ['valuemapid', 'name'],
			'valuemapids' => $valuemapids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($valuemapids as $valuemapid) {
			if (!array_key_exists($valuemapid, $db_valuemaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		DB::update('items', [[
			'values' => ['valuemapid' => 0],
			'where' => ['valuemapid' => $valuemapids]
		]]);

		$this->deleteByIds($valuemapids);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_VALUE_MAP, $db_valuemaps);

		return ['valuemapids' => $valuemapids];
	}

	/**
	 * Check for duplicated value maps.
	 *
	 * @param array $names_by_hostid
	 *
	 * @throws APIException  if value map already exists.
	 */
	private function checkDuplicates(array $names_by_hostid) {
		$sql_where = [];
		foreach ($names_by_hostid as $hostid => $names) {
			$sql_where[] = '(vm.hostid='.$hostid.' AND '.dbConditionString('vm.name', $names).')';
		}

		$db_valuemaps = DBfetchArray(
			DBselect('SELECT vm.name FROM valuemap vm WHERE '.implode(' OR ', $sql_where), 1)
		);

		if ($db_valuemaps) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Value map "%1$s" already exists.', $db_valuemaps[0]['name'])
			);
		}
	}

	/**
	 * @param array $valuemaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$valuemaps) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid', 'name']], 'fields' => [
			'hostid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'uuid' =>		['type' => API_ANY],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemap', 'name')],
			'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'type' =>		['type' => API_INT32, 'default' => VALUEMAP_MAPPING_TYPE_EQUAL, 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_EQUAL, VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL, VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP, VALUEMAP_MAPPING_TYPE_DEFAULT])],
				'value' =>		['type' => API_MULTIPLE, 'rules' => [
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_EQUAL])],
						'type' => API_STRING_UTF8,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL])],
						'type' => API_FLOAT,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_IN_RANGE])],
						'type' => API_NUMERIC_RANGES,
						'flags' => API_NOT_EMPTY,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_REGEXP])],
						'type' => API_REGEX,
						'flags' => API_NOT_EMPTY,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_DEFAULT])],
						'type' => API_STRING_UTF8
					]
				]],
				'newvalue' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemap_mapping', 'newvalue')]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $valuemaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->validateValuemapMappings($valuemaps);
		$hostids = [];

		foreach ($valuemaps as $valuemap) {
			$hostids[$valuemap['hostid']] = true;
		}

		$db_hosts = API::Host()->get([
			'output' => ['status'],
			'hostids' => array_keys($hostids),
			'templated_hosts' => true,
			'editable' => true,
			'preservekeys' => true
		]);

		$names_by_hostid = [];

		foreach ($valuemaps as $valuemap) {
			// check permissions by hostid
			if (!array_key_exists($valuemap['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$names_by_hostid[$valuemap['hostid']][] = $valuemap['name'];
		}

		self::validateUuid($valuemaps, $db_hosts);

		self::addUuid($valuemaps, $db_hosts);

		self::checkUuidDuplicates($valuemaps);
		$this->checkDuplicates($names_by_hostid);
	}

	/**
	 * @param array $valuemaps
	 * @param array $db_hosts
	 *
	 * @throws APIException
	 */
	private static function validateUuid(array $valuemaps, array $db_hosts): void {
		foreach ($valuemaps as &$valuemap) {
			$valuemap['host_status'] = $db_hosts[$valuemap['hostid']]['status'];
		}
		unset($valuemap);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid']], 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'uuid' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('valuemap', 'uuid'), 'unset' => true]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $valuemaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Add the UUID to those of the given value maps that belong to a template and don't have the 'uuid' parameter set.
	 *
	 * @param array $valuemaps
	 * @param array $db_hosts
	 */
	private static function addUuid(array &$valuemaps, array $db_hosts): void {
		foreach ($valuemaps as &$valuemap) {
			if ($db_hosts[$valuemap['hostid']]['status'] == HOST_STATUS_TEMPLATE
					&& !array_key_exists('uuid', $valuemap)) {
				$valuemap['uuid'] = generateUuidV4();
			}
		}
		unset($valuemap);
	}

	/**
	 * Verify value map UUIDs are not repeated.
	 *
	 * @param array      $valuemaps
	 * @param array|null $db_valuemaps
	 *
	 * @throws APIException
	 */
	private static function checkUuidDuplicates(array $valuemaps, array $db_valuemaps = null): void {
		$valuemap_indexes = [];

		foreach ($valuemaps as $i => $valuemap) {
			if (!array_key_exists('uuid', $valuemap) || $valuemap['uuid'] === '') {
				continue;
			}

			if ($db_valuemaps === null || $valuemap['uuid'] !== $db_valuemaps[$valuemap['valuemapid']]['uuid']) {
				$valuemap_indexes[$valuemap['uuid']] = $i;
			}
		}

		if (!$valuemap_indexes) {
			return;
		}

		$duplicates = DB::select('valuemap', [
			'output' => ['uuid'],
			'filter' => [
				'uuid' => array_keys($valuemap_indexes)
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/'.($valuemap_indexes[$duplicates[0]['uuid']] + 1),
					_('value map with the same UUID already exists')
				)
			);
		}
	}

	/**
	 * @param array $valuemaps
	 * @param array $db_valuemaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$valuemaps, array &$db_valuemaps = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['valuemapid']], 'fields' => [
			'uuid' => 		['type' => API_ANY],
			'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemap', 'name')],
			'mappings' =>	['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => [
				'type' =>		['type' => API_INT32, 'default' => VALUEMAP_MAPPING_TYPE_EQUAL, 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_EQUAL, VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL, VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP, VALUEMAP_MAPPING_TYPE_DEFAULT])],
				'value' =>		['type' => API_MULTIPLE, 'rules' => [
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_EQUAL])],
						'type' => API_STRING_UTF8,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL])],
						'type' => API_FLOAT,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_IN_RANGE])],
						'type' => API_NUMERIC_RANGES,
						'flags' => API_NOT_EMPTY,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_REGEXP])],
						'type' => API_REGEX,
						'flags' => API_NOT_EMPTY,
						'length' => DB::getFieldLength('valuemap_mapping', 'value')
					],
					[
						'if' => ['field' => 'type', 'in' => implode(',', [VALUEMAP_MAPPING_TYPE_DEFAULT])],
						'type' => API_STRING_UTF8
					]
				]],
				'newvalue' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemap_mapping', 'newvalue')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $valuemaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->validateValuemapMappings($valuemaps);
		$db_valuemaps = $this->get([
			'output' => ['uuid', 'valuemapid', 'hostid', 'name'],
			'valuemapids' => array_column($valuemaps, 'valuemapid'),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($valuemaps as $valuemap) {
			if (!array_key_exists($valuemap['valuemapid'], $db_valuemaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$valuemaps = $this->extendObjectsByKey($valuemaps, $db_valuemaps, 'valuemapid', ['hostid']);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['hostid', 'name']], 'fields' => [
			'hostid' =>	['type' => API_ID],
			'name' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $valuemaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hosts = DB::select('hosts', [
			'output' => ['status'],
			'hostids' => array_unique(array_column($db_valuemaps, 'hostid')),
			'preservekeys' => true
		]);

		self::validateUuid($valuemaps, $db_hosts);

		self::checkUuidDuplicates($valuemaps, $db_valuemaps);

		$names_by_hostid = [];

		foreach ($valuemaps as $valuemap) {
			$db_valuemap = $db_valuemaps[$valuemap['valuemapid']];

			if (array_key_exists('name', $valuemap) && $valuemap['name'] !== $db_valuemap['name']) {
				$names_by_hostid[$valuemap['hostid']][] = $valuemap['name'];
			}
		}

		if ($names_by_hostid) {
			$this->checkDuplicates($names_by_hostid);
		}
	}

	/**
	 * Validate uniqueness of mapping value in value maps, type VALUEMAP_MAPPING_TYPE_DEFAULT can be defined only once
	 * per value map mappings.
	 *
	 * @param array $valuemaps  Array of valuemaps
	 *
	 * @throws Exception when non unique
	 */
	protected function validateValuemapMappings(array $valuemaps) {
		$i = 0;
		$error = '';

		foreach ($valuemaps as $valuemap) {
			$i++;

			if (!array_key_exists('mappings', $valuemap)) {
				continue;
			}

			$type_uniq = array_fill_keys([VALUEMAP_MAPPING_TYPE_EQUAL, VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
					VALUEMAP_MAPPING_TYPE_LESS_EQUAL, VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP
				], []
			);
			$has_default = false;

			foreach (array_values($valuemap['mappings']) as $j => $mapping) {
				$type = array_key_exists('type', $mapping) ? $mapping['type'] : VALUEMAP_MAPPING_TYPE_EQUAL;
				$value = array_key_exists('value', $mapping) ? (string) $mapping['value'] : '';

				if ($has_default && $type == VALUEMAP_MAPPING_TYPE_DEFAULT) {
					$error = _s('value %1$s already exists', '(type)=('.VALUEMAP_MAPPING_TYPE_DEFAULT.')');
				}
				elseif (!array_key_exists('value', $mapping) && $type != VALUEMAP_MAPPING_TYPE_DEFAULT) {
					$error = _s('the parameter "%1$s" is missing', 'value');
				}
				elseif ($value !== '' && $type == VALUEMAP_MAPPING_TYPE_DEFAULT) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						sprintf('/%1$s/mappings/%2$s/value', $i, $j + 1),
						_('should be empty')
					));
				}
				elseif ($type != VALUEMAP_MAPPING_TYPE_DEFAULT && array_key_exists($value, $type_uniq[$type])) {
					$error = _s('value %1$s already exists', '(value)=('.$value.')');
				}

				if ($error !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						sprintf('/%1$s/mappings/%2$s', $i, $j + 1),
						$error
					));
				}

				$has_default = ($has_default || $type == VALUEMAP_MAPPING_TYPE_DEFAULT);
				$type_uniq[$type][$value] = true;
			}
		}
	}

	protected function addRelatedObjects(array $options, array $db_valuemaps) {
		$db_valuemaps = parent::addRelatedObjects($options, $db_valuemaps);

		// Select mappings for value map.
		if ($options['selectMappings'] !== null) {
			$def_mappings = ($options['selectMappings'] == API_OUTPUT_COUNT) ? '0' : [];

			foreach ($db_valuemaps as $valuemapid => $db_valuemap) {
				$db_valuemaps[$valuemapid]['mappings'] = $def_mappings;
			}

			if ($options['selectMappings'] == API_OUTPUT_COUNT) {
				$db_mappings = DBselect(
					'SELECT m.valuemapid,COUNT(*) AS cnt'.
					' FROM valuemap_mapping m'.
					' WHERE '.dbConditionInt('m.valuemapid', array_keys($db_valuemaps)).
					' GROUP BY m.valuemapid'
				);

				while ($db_mapping = DBfetch($db_mappings)) {
					$db_valuemaps[$db_mapping['valuemapid']]['mappings'] = $db_mapping['cnt'];
				}
			}
			else {
				$db_mappings = API::getApiService()->select('valuemap_mapping', [
					'output' => $this->outputExtend($options['selectMappings'], ['valuemapid',
						'valuemap_mappingid', 'sortorder'
					]),
					'filter' => ['valuemapid' => array_keys($db_valuemaps)]
				]);
				CArrayHelper::sort($db_mappings, [['field' => 'sortorder', 'order' => ZBX_SORT_UP]]);

				foreach ($db_mappings as $db_mapping) {
					$valuemapid = $db_mapping['valuemapid'];
					unset($db_mapping['valuemap_mappingid'], $db_mapping['valuemapid'], $db_mapping['sortorder']);

					$db_valuemaps[$valuemapid]['mappings'][] = $db_mapping;
				}
			}
		}

		return $db_valuemaps;
	}
}

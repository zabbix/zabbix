<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * Class containing methods for operations with icon maps.
 *
 * Icon maps work only with map elements that represent hosts, and used for automatic changing
 * icons based on host inventory data.
 * Icon maps has read access for everyone and write access only for superamins
 */
class CIconMap extends CApiService {

	protected $tableName = 'icon_map';
	protected $tableAlias = 'im';
	protected $sortColumns = ['iconmapid', 'name'];

	/**
	 * Get IconMap data.
	 * @param array $options
	 * @param array $options['iconmapids']
	 * @param array $options['sysmapids']
	 * @param bool  $options['editable']
	 * @param array $options['count']
	 * @param array $options['limit']
	 * @param array $options['order']
	 * @return array
	 */
	public function get(array $options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['icon_map' => 'im.iconmapid'],
			'from'		=> ['icon_map' => 'icon_map im'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'iconmapids'				=> null,
			'sysmapids'					=> null,
			'nopermissions'				=> null,
			'editable'					=> false,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectMappings'			=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($options['editable'] && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return [];
		}

		// iconmapids
		if (!is_null($options['iconmapids'])) {
			zbx_value2array($options['iconmapids']);

			$sqlParts['where'][] = dbConditionInt('im.iconmapid', $options['iconmapids']);
		}

		// sysmapids
		if (!is_null($options['sysmapids'])) {
			zbx_value2array($options['sysmapids']);

			$sqlParts['from']['sysmaps'] = 'sysmaps s';
			$sqlParts['where'][] = dbConditionInt('s.sysmapid', $options['sysmapids']);
			$sqlParts['where']['ims'] = 'im.iconmapid=s.iconmapid';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('icon_map im', $options, $sqlParts);
		}
		// search
		if (is_array($options['search'])) {
			zbx_db_search('icon_map im', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($iconMap = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				$result = $iconMap['rowscount'];
			}
			else {
				$result[$iconMap['iconmapid']] = $iconMap;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * @param array $iconmaps
	 *
	 * @return array
	 */
	public function create(array $iconmaps) {
		$this->validateCreate($iconmaps);

		$ins_iconmaps = [];

		foreach ($iconmaps as $iconmap) {
			unset($iconmap['mappings']);
			$ins_iconmaps[] = $iconmap;
		}

		$iconmapids = DB::insertBatch('icon_map', $ins_iconmaps);

		foreach ($iconmaps as $index => &$iconmap) {
			$iconmap['iconmapid'] = $iconmapids[$index];
		}
		unset($iconmap);

		$this->updateMappings($iconmaps, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_ICON_MAP, $iconmaps);

		return ['iconmapids' => $iconmapids];
	}

	/**
	 * @param array $iconmaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$iconmaps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY | API_REQUIRED, 'length' => DB::getFieldLength('icon_map', 'name')],
			'default_iconid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'mappings' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_REQUIRED, 'uniq' => [['inventory_link', 'expression']], 'fields' => [
				'iconid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'expression' =>		['type' => API_REGEX, 'flags' => API_NOT_EMPTY | API_REQUIRED, 'length' => DB::getFieldLength('icon_mapping', 'expression')],
				'inventory_link' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:70'],
				'sortorder' =>		['type' => API_INT32, 'flags' => API_DEPRECATED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $iconmaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(zbx_objectValues($iconmaps, 'name'));
		$this->checkMappings($iconmaps);
		$this->checkIcons($iconmaps);
	}

	/**
	 * @param array $iconmaps
	 *
	 * @return array
	 */
	public function update(array $iconmaps) {
		$this->validateUpdate($iconmaps, $db_iconmaps);

		$upd_iconmaps = [];

		foreach ($iconmaps as $iconmap) {
			$db_iconmap = $db_iconmaps[$iconmap['iconmapid']];

			$upd_iconmap = [];

			if (array_key_exists('name', $iconmap) && $iconmap['name'] !== $db_iconmap['name']) {
				$upd_iconmap['name'] = $iconmap['name'];
			}
			if (array_key_exists('default_iconid', $iconmap)
					&& bccomp($iconmap['default_iconid'], $db_iconmap['default_iconid']) != 0) {
				$upd_iconmap['default_iconid'] = $iconmap['default_iconid'];
			}

			if ($upd_iconmap) {
				$upd_iconmaps[] = [
					'values' => $upd_iconmap,
					'where' => ['iconmapid' => $iconmap['iconmapid']]
				];
			}
		}

		if ($upd_iconmaps) {
			DB::update('icon_map', $upd_iconmaps);
		}

		$this->updateMappings($iconmaps, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ICON_MAP, $iconmaps, $db_iconmaps);

		return ['iconmapids' => zbx_objectValues($iconmaps, 'iconmapid')];
	}

	/**
	 * @param array $iconmaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$iconmaps, array &$db_iconmaps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['iconmapid'], ['name']], 'fields' => [
			'iconmapid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('icon_map', 'name')],
			'default_iconid' =>	['type' => API_ID],
			'mappings' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['inventory_link', 'expression']], 'fields' => [
				'iconid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'expression' =>		['type' => API_REGEX, 'flags' => API_NOT_EMPTY | API_REQUIRED, 'length' => DB::getFieldLength('icon_mapping', 'expression')],
				'inventory_link' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:70'],
				'sortorder' =>		['type' => API_INT32, 'flags' => API_DEPRECATED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $iconmaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_iconmaps = DB::select('icon_map', [
			'output' => ['iconmapid', 'name', 'default_iconid'],
			'iconmapids' => zbx_objectValues($iconmaps, 'iconmapid'),
			'preservekeys' => true
		]);

		$names = [];

		foreach ($iconmaps as $iconmap) {
			if (!array_key_exists($iconmap['iconmapid'], $db_iconmaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_iconmap = $db_iconmaps[$iconmap['iconmapid']];

			if (array_key_exists('name', $iconmap) && $iconmap['name'] !== $db_iconmap['name']) {
				$names[] = $iconmap['name'];
			}
		}

		if ($names) {
			$this->checkDuplicates($names);
		}
		$this->checkMappings($iconmaps);
		$this->checkIcons($iconmaps);
	}

	/**
	 * Check for duplicated icon maps.
	 *
	 * @param array $names
	 *
	 * @throws APIException  if user already exists.
	 */
	private function checkDuplicates(array $names) {
		$db_iconmaps = DB::select('icon_map', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_iconmaps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map "%s" already exists.', $db_iconmaps[0]['name']));
		}
	}

	/**
	 * Check icon mappings.
	 *
	 * @param $iconmaps
	 *
	 * @throws APIException
	 */
	private function checkMappings($iconmaps) {
		$names = [];

		foreach ($iconmaps as $iconmap) {
			if (array_key_exists('mappings', $iconmap)) {
				foreach ($iconmap['mappings'] as $mapping) {
					if ($mapping['expression'][0] === '@') {
						$names[mb_substr($mapping['expression'], 1)] = true;
					}
				}
			}
		}

		if ($names) {
			$names = array_keys($names);

			$db_regexps = DB::select('regexps', [
				'output' => ['name'],
				'filter' => ['name' => $names]
			]);

			$db_regexps = zbx_toHash($db_regexps, 'name');

			foreach ($names as $name) {
				if (!array_key_exists($name, $db_regexps)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Global regular expression "%1$s" does not exist.', $name)
					);
				}
			}
		}
	}

	/**
	 * Check icons.
	 *
	 * @param $iconmaps
	 *
	 * @throws APIException
	 */
	private function checkIcons($iconmaps) {
		$iconids = [];

		foreach ($iconmaps as $iconmap) {
			if (array_key_exists('default_iconid', $iconmap)) {
				$iconids[$iconmap['default_iconid']] = true;
			}

			if (array_key_exists('mappings', $iconmap)) {
				foreach ($iconmap['mappings'] as $mapping) {
					$iconids[$mapping['iconid']] = true;
				}
			}
		}

		if ($iconids) {
			$iconids = array_keys($iconids);

			$db_icons = API::Image()->get([
				'output' => [],
				'imageids' => $iconids,
				'filter' => ['imagetype' => IMAGE_TYPE_ICON],
				'preservekeys' => true
			]);

			foreach ($iconids as $iconid) {
				if (!array_key_exists($iconid, $db_icons)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon with ID "%1$s" is not available.', $iconid));
				}
			}
		}
	}

	/**
	 * Update table "icon_mapping".
	 *
	 * @param array  $iconmaps
	 * @param string $method
	 */
	private function updateMappings(array $iconmaps, $method) {
		$mappings = [];

		foreach ($iconmaps as $iconmap) {
			if (array_key_exists('mappings', $iconmap)) {
				foreach ($iconmap['mappings'] as $index => $mapping) {
					$mapping['iconmapid'] = $iconmap['iconmapid'];
					$mapping['sortorder'] = $index;

					$mappings[$iconmap['iconmapid']][] = $mapping;
				}
			}
		}

		if (!$mappings) {
			return;
		}

		$db_mappings = ($method === 'update')
			? DB::select('icon_mapping', [
				'output' => ['iconmappingid', 'iconmapid', 'iconid', 'expression', 'inventory_link', 'sortorder'],
				'filter' => ['iconmapid' => array_keys($mappings)],
				'sortfield' => ['iconmapid', 'sortorder']
			])
			: [];

		$ins_mappings = [];
		$upd_mappings = [];
		$del_mappingids = [];

		foreach ($db_mappings as $db_mapping) {
			if ($mappings[$db_mapping['iconmapid']]) {
				$mapping = array_shift($mappings[$db_mapping['iconmapid']]);

				$upd_mapping = [];

				if (bccomp($mapping['iconid'], $db_mapping['iconid']) != 0) {
					$upd_mapping['iconid'] = $mapping['iconid'];
				}
				if ($mapping['expression'] !== $db_mapping['expression']) {
					$upd_mapping['expression'] = $mapping['expression'];
				}
				if ($mapping['inventory_link'] != $db_mapping['inventory_link']) {
					$upd_mapping['inventory_link'] = $mapping['inventory_link'];
				}
				if ($mapping['sortorder'] != $db_mapping['sortorder']) {
					$upd_mapping['sortorder'] = $mapping['sortorder'];
				}

				if ($upd_mapping) {
					$upd_mappings[] = [
						'values' => $upd_mapping,
						'where' => ['iconmappingid' => $db_mapping['iconmappingid']]
					];
				}
			}
			else {
				$del_mappingids[] = $db_mapping['iconmappingid'];
			}
		}

		foreach ($iconmaps as $iconmap) {
			$ins_mappings = array_merge($ins_mappings, $mappings[$iconmap['iconmapid']]);
		}

		if ($ins_mappings) {
			DB::insertBatch('icon_mapping', $ins_mappings);
		}

		if ($upd_mappings) {
			DB::update('icon_mapping', $upd_mappings);
		}

		if ($del_mappingids) {
			DB::delete('icon_mapping', ['iconmappingid' => $del_mappingids]);
		}
	}

	/**
	 * @param array $iconmapids
	 *
	 * @return array
	 */
	public function delete(array $iconmapids) {
		$this->validateDelete($iconmapids, $db_iconmaps);

		DB::delete('icon_mapping', ['iconmapid' => $iconmapids]);
		DB::delete('icon_map', ['iconmapid' => $iconmapids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ICON_MAP, $db_iconmaps);

		return ['iconmapids' => $iconmapids];
	}

	/**
	 * @param array $iconmapids
	 * @param array $db_iconmaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$iconmapids, array &$db_iconmaps = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $iconmapids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_iconmaps = $this->get([
			'output' => ['iconmapid', 'name'],
			'iconmapids' => $iconmapids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($iconmapids as $iconmapid) {
			if (!array_key_exists($iconmapid, $db_iconmaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$db_sysmaps = DB::select('sysmaps', [
			'output' => ['name', 'iconmapid'],
			'filter' => ['iconmapid' => $iconmapids],
			'limit' => 1
		]);

		if ($db_sysmaps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map "%1$s" cannot be deleted. Used in map "%2$s".',
				$db_iconmaps[$db_sysmaps[0]['iconmapid']]['name'], $db_sysmaps[0]['name']
			));
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$iconMapIds = array_keys($result);

		if ($options['selectMappings'] !== null && $options['selectMappings'] != API_OUTPUT_COUNT) {
			$mappings = API::getApiService()->select('icon_mapping', [
				'output' => $this->outputExtend($options['selectMappings'], ['iconmapid', 'iconmappingid']),
				'filter' => ['iconmapid' => $iconMapIds],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($mappings, 'iconmapid', 'iconmappingid');

			$mappings = $this->unsetExtraFields($mappings, ['iconmapid', 'iconmappingid'], $options['selectMappings']);
			$result = $relationMap->mapMany($result, $mappings, 'mappings');
		}

		return $result;
	}
}

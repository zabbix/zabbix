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
 * Class containing methods for operations with icon maps.
 *
 * Icon maps work only with map elements that represent hosts, and used for automatic changing
 * icons based on host inventory data.
 * Icon maps has read access for everyone and write access only for superadmins
 */
class CIconMap extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

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
		$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'iconmap', __FUNCTION__)
			);
		}

		self::validateCreate($iconmaps);

		$ins_iconmaps = [];

		foreach ($iconmaps as $iconmap) {
			unset($iconmap['mappings']);
			$ins_iconmaps[] = $iconmap;
		}

		$iconmapids = DB::insert('icon_map', $ins_iconmaps);

		foreach ($iconmaps as $index => &$iconmap) {
			$iconmap['iconmapid'] = $iconmapids[$index];
		}
		unset($iconmap);

		self::updateMappings($iconmaps, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_ICON_MAP, $iconmaps);

		return ['iconmapids' => $iconmapids];
	}

	/**
	 * @param array $iconmaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$iconmaps) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('icon_map', 'name')],
			'default_iconid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'mappings' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['inventory_link', 'expression']], 'fields' => [
				'iconid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'expression' =>		['type' => API_REGEX, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_GLOBAL_REGEX, 'length' => DB::getFieldLength('icon_mapping', 'expression')],
				'inventory_link' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:70']
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $iconmaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($iconmaps);
		self::checkMappings($iconmaps);
		self::checkIcons($iconmaps);
	}

	/**
	 * @param array $iconmaps
	 *
	 * @return array
	 */
	public function update(array $iconmaps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'iconmap', __FUNCTION__)
			);
		}

		self::validateUpdate($iconmaps, $db_iconmaps);

		$upd_iconmaps = [];

		foreach ($iconmaps as $iconmap) {
			$upd_iconmap = DB::getUpdatedValues('icon_map', $iconmap, $db_iconmaps[$iconmap['iconmapid']]);

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

		self::updateMappings($iconmaps, __FUNCTION__, $db_iconmaps);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_ICON_MAP, $iconmaps, $db_iconmaps);

		return ['iconmapids' => array_column($iconmaps, 'iconmapid')];
	}

	/**
	 * @param array $iconmaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateUpdate(array &$iconmaps, ?array &$db_iconmaps = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['iconmapid'], ['name']], 'fields' => [
			'iconmapid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('icon_map', 'name')],
			'default_iconid' =>	['type' => API_ID],
			'mappings' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['inventory_link', 'expression']], 'fields' => [
				'iconid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'expression' =>		['type' => API_REGEX, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_GLOBAL_REGEX, 'length' => DB::getFieldLength('icon_mapping', 'expression')],
				'inventory_link' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:70']
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $iconmaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_iconmaps = DB::select('icon_map', [
			'output' => ['iconmapid', 'name', 'default_iconid'],
			'iconmapids' => array_column($iconmaps, 'iconmapid'),
			'preservekeys' => true
		]);

		if (count($db_iconmaps) != count($iconmaps)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::addAffectedObjects($iconmaps, $db_iconmaps);

		self::checkDuplicates($iconmaps, $db_iconmaps);
		self::checkMappings($iconmaps);
		self::checkIcons($iconmaps);
	}

	/**
	 * Check for duplicated icon maps.
	 *
	 * @param array      $iconmaps
	 * @param array|null $db_iconmaps
	 *
	 * @throws APIException  if user already exists.
	 */
	private static function checkDuplicates(array $iconmaps, ?array $db_iconmaps = null) {
		$names = [];

		foreach ($iconmaps as $iconmap) {
			if (!array_key_exists('name', $iconmap)) {
				continue;
			}

			if ($db_iconmaps === null || $iconmap['name'] !== $db_iconmaps[$iconmap['iconmapid']]['name']) {
				$names[] = $iconmap['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('icon_map', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * Check icon mappings.
	 *
	 * @param array $iconmaps
	 *
	 * @throws APIException
	 */
	private static function checkMappings(array $iconmaps): void {
		$names = [];

		foreach ($iconmaps as $iconmap) {
			if (array_key_exists('mappings', $iconmap)) {
				foreach ($iconmap['mappings'] as $mapping) {
					if ($mapping['expression'][0] === '@') {
						/*
						 * substr() performs differently on PHP < 7. Prior to PHP 7 version, FALSE was returned if
						 * string is equal to start characters long. As of PHP 7, an empty string will be returned.
						 */
						$names[isset($mapping['expression'][1]) ? substr($mapping['expression'], 1) : ''] = true;
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

			$db_regexps = array_column($db_regexps, null, 'name');

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
	 * @param array $iconmaps
	 *
	 * @throws APIException
	 */
	private static function checkIcons(array $iconmaps): void {
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
	 * @param array      $iconmaps
	 * @param string     $method
	 * @param array|null $db_iconmaps
	 */
	private static function updateMappings(array &$iconmaps, string $method, ?array $db_iconmaps = null): void {
		$ins_mappings = [];
		$upd_mappings = [];
		$del_mappingids = [];

		foreach ($iconmaps as &$iconmap) {
			if (!array_key_exists('mappings', $iconmap)) {
				continue;
			}

			$db_mappings = ($method === 'update') ? $db_iconmaps[$iconmap['iconmapid']]['mappings'] : [];

			foreach ($iconmap['mappings'] as $index => &$mapping) {
				$mapping['sortorder'] = $index;

				$db_mapping = current(array_filter($db_mappings,
					static function (array $db_mapping) use ($mapping): bool {
						return ($mapping['inventory_link'] == $db_mapping['inventory_link']
								&& $mapping['expression'] === $db_mapping['expression']);
					}
				));

				if ($db_mapping) {
					$mapping['iconmappingid'] = $db_mapping['iconmappingid'];

					$upd_mapping = DB::getUpdatedValues('icon_mapping', $mapping, $db_mapping);

					if ($upd_mapping) {
						$upd_mappings[] = [
							'values' => $upd_mapping,
							'where' => ['iconmappingid' => $db_mapping['iconmappingid']]
						];
					}

					unset($db_mappings[$db_mapping['iconmappingid']]);
				}
				else {
					$ins_mappings[] = ['iconmapid' => $iconmap['iconmapid']] + $mapping;
				}
			}
			unset($mapping);

			$del_mappingids = array_merge($del_mappingids, array_keys($db_mappings));
		}
		unset($iconmap);

		if ($ins_mappings) {
			$iconmappingids = DB::insert('icon_mapping', $ins_mappings);
		}

		if ($upd_mappings) {
			DB::update('icon_mapping', $upd_mappings);
		}

		if ($del_mappingids) {
			DB::delete('icon_mapping', ['iconmappingid' => $del_mappingids]);
		}

		foreach ($iconmaps as &$iconmap) {
			if (!array_key_exists('mappings', $iconmap)) {
				continue;
			}

			foreach ($iconmap['mappings'] as &$mapping) {
				if (!array_key_exists('iconmappingid', $mapping)) {
					$mapping['iconmappingid'] = array_shift($iconmappingids);
				}
			}
			unset($mapping);
		}
		unset($iconmap);
	}

	/**
	 * @param array $iconmapids
	 *
	 * @return array
	 */
	public function delete(array $iconmapids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'iconmap', __FUNCTION__)
			);
		}

		self::validateDelete($iconmapids, $db_iconmaps);

		DB::delete('icon_mapping', ['iconmapid' => $iconmapids]);
		DB::delete('icon_map', ['iconmapid' => $iconmapids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_ICON_MAP, $db_iconmaps);

		return ['iconmapids' => $iconmapids];
	}

	/**
	 * @param array      $iconmapids
	 * @param array|null $db_iconmaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateDelete(array &$iconmapids, ?array &$db_iconmaps = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $iconmapids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_iconmaps = DB::select('icon_map', [
			'output' => ['iconmapid', 'name'],
			'iconmapids' => $iconmapids,
			'preservekeys' => true
		]);

		if (count($db_iconmaps) != count($iconmapids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkUsedSysMaps($iconmapids, $db_iconmaps);
	}

	/**
	 * @param array      $iconmapids
	 * @param array|null $db_iconmaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function checkUsedSysMaps(array $iconmapids, array $db_iconmaps): void {
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

	/**
	 * Add the existing mappings to $db_iconmaps whether these are affected by the update.
	 *
	 * @param array $iconmaps
	 * @param array $db_iconmaps
	 */
	private static function addAffectedObjects(array $iconmaps, array &$db_iconmaps): void {
		$iconmapids = [];

		foreach ($iconmaps as $iconmap) {
			if (array_key_exists('mappings', $iconmap)) {
				$iconmapids[] = $iconmap['iconmapid'];
				$db_iconmaps[$iconmap['iconmapid']]['mappings'] = [];
			}
		}

		if ($iconmapids) {
			$options = [
				'output' => ['iconmappingid', 'iconmapid', 'iconid', 'inventory_link', 'expression', 'sortorder'],
				'filter' => ['iconmapid' => $iconmapids]
			];
			$db_mappings = DBselect(DB::makeSql('icon_mapping', $options));

			while ($db_mapping = DBfetch($db_mappings)) {
				$db_iconmaps[$db_mapping['iconmapid']]['mappings'][$db_mapping['iconmappingid']] =
					array_diff_key($db_mapping, array_flip(['iconmapid']));
			}
		}
	}
}

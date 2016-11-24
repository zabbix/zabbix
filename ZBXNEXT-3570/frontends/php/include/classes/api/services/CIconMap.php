<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	 * @param array $options['editable']
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
			'editable'					=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectMappings'			=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Add IconMap.
	 * @param array $iconMaps
	 * @return array
	 */
	public function create(array $iconMaps) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create icon maps.'));
		}

		$iconMaps = zbx_toArray($iconMaps);
		$iconMapRequiredFields = ['name' => null];

		$duplicates = [];
		foreach ($iconMaps as $iconMap) {
			if (!check_db_fields($iconMapRequiredFields, $iconMap)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parameter is used for icon map "%s".', $iconMap['name']));
			}
			if (zbx_empty($iconMap['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map name cannot be empty.'));
			}
			if (isset($duplicates[$iconMap['name']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot create icon maps with identical name "%s".', $iconMap['name']));
			}
			else {
				$duplicates[$iconMap['name']] = $iconMap['name'];
			}
		}

		$this->validateMappings($iconMaps);

		$options = [
			'filter' => ['name' => $duplicates],
			'output' => ['name'],
			'editable' => true,
			'nopermissions' => true
		];
		$dbIconMaps = $this->get($options);
		foreach ($dbIconMaps as $dbIconMap) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map "%s" already exists.', $dbIconMap['name']));
		}

		$iconMapids = DB::insert('icon_map', $iconMaps);
		$mappings = [];

		foreach ($iconMaps as $imnum => $iconMap) {
			$sort_order = 0;

			foreach ($iconMap['mappings'] as $mapping) {
				$mapping['iconmapid'] = $iconMapids[$imnum];

				if (!array_key_exists('sortorder', $mapping)) {
					$mapping['sortorder'] = $sort_order;
				}

				$sort_order++;

				$mappings[] = $mapping;
			}
		}
		DB::insert('icon_mapping', $mappings);

		return ['iconmapids' => $iconMapids];
	}

	/**
	 * Update IconMap.
	 * @param array $iconMaps
	 * @return array
	 */
	public function update(array $iconMaps) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update icon maps.'));
		}

		$iconMaps = zbx_toArray($iconMaps);

		$iconMapids = zbx_objectValues($iconMaps, 'iconmapid');
		$updates = [];

		$duplicates = [];
		foreach ($iconMaps as $iconMap) {
			if (!check_db_fields(['iconmapid' => null], $iconMap)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parameters for icon map update method "%s".', $iconMap['name']));
			}

			if (isset($iconMap['name'])) {
				if (zbx_empty($iconMap['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map name cannot be empty.'));
				}
				elseif (isset($duplicates[$iconMap['name']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot create icon maps with identical name "%s".', $iconMap['name']));
				}
				else {
					$duplicates[$iconMap['name']] = $iconMap['name'];
				}
			}
		}

		$this->validateMappings($iconMaps, false);


		$iconMapsUpd = API::IconMap()->get([
			'iconmapids' => $iconMapids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'selectMappings' => API_OUTPUT_EXTEND
		]);

		$oldIconMappings = [];
		$newIconMappings = [];
		foreach ($iconMaps as $iconMap) {
			if (!isset($iconMapsUpd[$iconMap['iconmapid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map with iconmapid "%s" does not exist.', $iconMap['iconmapid']));
			}

			// Existence
			if (isset($iconMap['name'])) {
				$iconMapExists = $this->get([
					'filter' => ['name' => $iconMap['name']],
					'output' => ['iconmapid'],
					'editable' => true,
					'nopermissions' => true,
					'preservekeys' => true
				]);
				if (($iconMapExists = reset($iconMapExists)) && (bccomp($iconMapExists['iconmapid'], $iconMap['iconmapid']) != 0)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Icon map "%s" already exists.', $iconMap['name']));
				}
			}

			if (isset($iconMap['mappings'])) {
				$mappingsDb = $iconMapsUpd[$iconMap['iconmapid']]['mappings'];
				$sort_order = 0;

				foreach ($mappingsDb as $mapping) {
					$oldIconMappings[] = $mapping;
				}

				foreach ($iconMap['mappings'] as $mapping) {
					$mapping['iconmapid'] = $iconMap['iconmapid'];

					if (!array_key_exists('sortorder', $mapping)) {
						$mapping['sortorder'] = $sort_order;
					}

					$sort_order++;

					$newIconMappings[] = $mapping;
				}
			}

			$iconMapid = $iconMap['iconmapid'];
			unset($iconMap['iconmapid']);
			if (!empty($iconMap)) {
				$updates[] = [
					'values' => $iconMap,
					'where' => ['iconmapid' => $iconMapid]
				];
			}
		}

		DB::save('icon_map', $iconMaps);
		DB::replace('icon_mapping', $oldIconMappings, $newIconMappings);

		return ['iconmapids' => $iconMapids];
	}

	/**
	 * Delete IconMap.
	 *
	 * @param array $iconmapids
	 *
	 * @return array
	 */
	public function delete(array $iconmapids) {
		if (empty($iconmapids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}
		if (!$this->isWritable($iconmapids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$sql = 'SELECT m.name AS mapname, im.name as iconmapname'.
			' FROM sysmaps m, icon_map im'.
			' WHERE m.iconmapid=im.iconmapid'.
			' AND '.dbConditionInt('m.iconmapid', $iconmapids);
		if ($names = DBfetch(DBselect($sql))) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Icon map "%1$s" cannot be deleted. Used in map "%2$s".', $names['iconmapname'], $names['mapname'])
			);
		}

		DB::delete('icon_map', ['iconmapid' => $iconmapids]);

		return ['iconmapids' => $iconmapids];
	}

	/**
	 * Check if user has write permissions for given icon map IDs.
	 * @param $ids
	 * @return bool
	 */
	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'iconmapids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return count($ids) == $count;
	}

	/**
	 * Checks icon maps.
	 * @throws APIException
	 * @param $iconMaps
	 * @param bool $mustExist if icon map should be checked against having at least one mapping
	 */
	protected function validateMappings($iconMaps, $mustExist = true) {
		$inventoryFields = getHostInventories();
		$imageids = API::Image()->get([
			'output' => ['imageid'],
			'preservekeys' => true,
			'filter' => ['imagetype' => IMAGE_TYPE_ICON]
		]);

		foreach ($iconMaps as $iconMap) {
			if (isset($iconMap['mappings']) && empty($iconMap['mappings'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Icon map "%s" must have at least one mapping.', $iconMap['name']));
			}
			elseif (!isset($iconMap['mappings'])) {
				if ($mustExist) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Icon map "%s" must have at least one mapping.', $iconMap['name']));
				}
				else {
					continue;
				}
			}

			$uniqField = [];
			foreach ($iconMap['mappings'] as $mapping) {
				if (!isset($mapping['expression'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Required field "expression" is missing in icon mapping.'));
				}
				elseif (!isset($mapping['inventory_link'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Required field "inventory_link" is missing in icon mapping.'));
				}
				elseif (!isset($mapping['iconid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Required field "iconid" is missing in icon mapping.'));
				}
				elseif (!isset($inventoryFields[$mapping['inventory_link']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Icon map "%1$s" has mapping with incorrect inventory link "%2$s".', $iconMap['name'], $mapping['inventory_link']));
				}
				elseif (!isset($imageids[$mapping['iconid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Icon map "%1$s" has mapping with incorrect iconid "%2$s".', $iconMap['name'], $mapping['iconid']));
				}

				try {
					CGlobalRegexp::isValid($mapping['expression']);
				}
				catch (Exception $e) {
					switch ($e->getCode()) {
						case CGlobalRegexp::ERROR_REGEXP_EMPTY:
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Icon map "%s" cannot have mapping with empty expression.', $iconMap['name']));
							break;
						case CGlobalRegexp::ERROR_REGEXP_NOT_EXISTS:
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Icon map "%s" cannot have mapping with global expression that does not exist.', $iconMap['name']));
							break;
						default:
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Icon map "%s" has incorrect expression.', $iconMap['name']));
					}
				}

				if (isset($uniqField[$mapping['inventory_link'].$mapping['expression']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Icon mapping entry "%1$s" against "%2$s" already exists.',
							$mapping['expression'],
							$inventoryFields[$mapping['inventory_link']]['title'])
					);
				}
				$uniqField[$mapping['inventory_link'].$mapping['expression']] = true;
			}
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

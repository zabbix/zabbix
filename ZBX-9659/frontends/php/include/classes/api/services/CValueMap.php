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
 * Class containing methods for operations with value maps.
 */
class CValueMap extends CApiService {

	protected $tableName = 'valuemaps';
	protected $tableAlias = 'vm';
	protected $sortColumns = ['valuemapid', 'name'];

	/**
	 * Set value map default options in addition to global options.
	 */
	public function __construct() {
		parent::__construct();

		$this->getOptions = array_merge($this->getOptions, [
			'valuemapids'		=> null,
			'editable'			=> null,
			'selectMappings'	=> null,
			'sortfield'			=> '',
			'sortorder'			=> ''
		]);
	}

	/**
	 * Get value maps.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$options = zbx_array_merge($this->getOptions, $options);

		if ($options['editable'] !== null && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return ($options['countOutput'] !== null && $options['groupCount'] === null) ? 0 : [];
		}

		$res = DBselect($this->createSelectQuery($this->tableName(), $options), $options['limit']);

		$result = [];
		while ($row = DBfetch($res)) {
			if ($options['countOutput'] === null) {
				$result[$row[$this->pk()]] = $row;
			}
			else {
				if ($options['groupCount'] === null) {
					$result = $row['rowscount'];
				}
				else {
					$result[] = $row;
				}
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Add value maps.
	 *
	 * @param array		$valuemaps								An array of value maps.
	 * @param string	$valuemaps[]['name']					Name of the value map.
	 * @param array		$valuemaps[]['mappings']				Value mappings for value map.
	 * @param string	$valuemaps[]['mappings'][]['value']		Value mapping original value.
	 * @param string	$valuemaps[]['mappings'][]['newvalue']	Value to which the original value is mapped to.
	 *
	 * @return array
	 */
	public function create($valuemaps) {
		$valuemaps = zbx_toArray($valuemaps);

		$this->validateCreate($valuemaps);

		$valuemapids = DB::insertBatch('valuemaps', $valuemaps);

		$mappings = [];

		foreach ($valuemaps as $key => $valuemap) {
			foreach ($valuemap['mappings'] as $mapping) {
				$mappings[] = [
					'valuemapid' => $valuemapids[$key],
					'value' => $mapping['value'],
					'newvalue' => $mapping['newvalue']
				];
			}
		}

		DB::insertBatch('mappings', $mappings);

		foreach ($valuemaps as $key => $valuemap) {
			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_VALUE_MAP, $valuemapids[$key], $valuemap['name'],
				null, null, null
			);
		}

		return ['valuemapids' => $valuemapids];
	}

	/**
	 * Update value maps.
	 *
	 * @param array		$valuemaps								An array of value maps.
	 * @param string	$valuemaps[]['valuemapid']				ID of the value map.
	 * @param string	$valuemaps[]['name']					Name of the value map (optional).
	 * @param array		$valuemaps[]['mappings']				Value mappings for value map (optional).
	 * @param string	$valuemaps[]['mappings'][]['value']		Value mapping original value (optional).
	 * @param string	$valuemaps[]['mappings'][]['newvalue']	Value to which the original value is mapped to (optional).
	 *
	 * @return array
	 */
	public function update($valuemaps) {
		$valuemaps = zbx_toArray($valuemaps);

		$this->validateUpdate($valuemaps);

		$upd_valuemaps = [];
		$upd_mappings = [];

		foreach ($valuemaps as $valuemap) {
			$valuemapid = $valuemap['valuemapid'];

			if (array_key_exists('mappings', $valuemap)) {
				$upd_mappings[$valuemapid] = [];
				foreach ($valuemap['mappings'] as $mapping) {
					$upd_mappings[$valuemapid][$mapping['value']] = $mapping['newvalue'];
				}
			}

			unset($valuemap['valuemapid'], $valuemap['mappings']);

			// Skip updating value maps, if name is not given.
			if (array_key_exists('name', $valuemap)) {
				$upd_valuemaps[$valuemapid] = $valuemap;
			}
		}

		if ($upd_valuemaps) {
			$db_valuemaps = API::getApiService()->select('valuemaps', [
				'output' => ['valuemapid', 'name'],
				'valuemapids' => array_keys($upd_valuemaps)
			]);

			$update = [];

			foreach ($db_valuemaps as $db_valuemap) {
				$upd_valuemap = $upd_valuemaps[$db_valuemap['valuemapid']];

				// Skip updating value maps, if name was not changed.
				if ($upd_valuemap['name'] !== $db_valuemap['name']) {
					$update[] = [
						'values' => $upd_valuemap,
						'where' => ['valuemapid' => $db_valuemap['valuemapid']]
					];

					add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_VALUE_MAP, $db_valuemap['valuemapid'],
						$upd_valuemap['name'], 'valuemaps', $db_valuemap, $upd_valuemap
					);
				}
			}

			if ($update) {
				DB::update('valuemaps', $update);
			}
		}

		if ($upd_mappings) {
			$db_mappings = API::getApiService()->select('mappings', [
				'output' => ['mappingid', 'valuemapid', 'value', 'newvalue'],
				'filter' => ['valuemapid' => array_keys($upd_mappings)]
			]);

			$insert_mapings = [];
			$update_mapings = [];
			$delete_mapingids = [];

			foreach ($db_mappings as $db_mapping) {
				if (array_key_exists($db_mapping['valuemapid'], $upd_mappings)) {
					$upd_mapping = &$upd_mappings[$db_mapping['valuemapid']];

					if (array_key_exists($db_mapping['value'], $upd_mapping)) {
						if ($upd_mapping[$db_mapping['value']] !== $db_mapping['newvalue']) {
							$update_mapings[] = [
								'values' => ['newvalue' => $upd_mapping[$db_mapping['value']]],
								'where' => ['mappingid' => $db_mapping['mappingid']]
							];
						}
						unset($upd_mapping[$db_mapping['value']]);
					}
					else {
						$delete_mapingids[] = $db_mapping['mappingid'];
					}

					unset($upd_mapping);
				}
				else {
					$delete_mapingids[] = $db_mapping['mappingid'];
				}
			}

			foreach ($upd_mappings as $valuemapid => $upd_mapping) {
				foreach ($upd_mapping as $value => $newvalue) {
					$insert_mapings[] = ['valuemapid' => $valuemapid, 'value' => $value, 'newvalue' => $newvalue];
				}
			}

			if ($insert_mapings) {
				DB::insertBatch('mappings', $insert_mapings);
			}

			if ($update_mapings) {
				DB::update('mappings', $update_mapings);
			}

			if ($delete_mapingids) {
				DB::delete('mappings', ['mappingid' => $delete_mapingids]);
			}
		}

		return ['valuemapids' => zbx_objectValues($valuemaps, 'valuemapid')];
	}

	/**
	 * Delete value maps.
	 *
	 * @param array $valuemapids
	 *
	 * @return array
	 */
	public function delete(array $valuemapids) {
		// Check permissions.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can delete value maps.'));
		}

		if (!$valuemapids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// Check if value map exists.
		$db_valuemaps = API::getApiService()->select('valuemaps', [
			'output' => ['valuemapid', 'name'],
			'valuemapids' => $valuemapids,
			'preservekeys' => true
		]);

		foreach ($valuemapids as $valuemapid) {
			if (!is_int($valuemapid) && !is_string($valuemapid)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif (!array_key_exists($valuemapid, $db_valuemaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// Mappings are handled with cascade delete, but items.valuemapid reference should be removed first.
		$result = DB::update('items', [[
			'values' => ['valuemapid' => 0],
			'where' => ['valuemapid' => $valuemapids]
		]]);
		if ($result) {
			$this->deleteByIds($valuemapids);
		}

		foreach ($db_valuemaps as $db_valuemap) {
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_VALUE_MAP, $db_valuemap['valuemapid'],
				$db_valuemap['name'], null, null, null
			);
		}

		return ['valuemapids' => $valuemapids];
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $valuemaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $valuemaps) {
		// Check permissions.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can create value maps.'));
		}

		if (!$valuemaps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$required_fields = ['name', 'mappings'];

		foreach ($valuemaps as $valuemap) {
			if (!is_array($valuemap)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// Check required parameters.
			$missing_keys = checkRequiredKeys($valuemap, $required_fields);
			if ($missing_keys) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Value map is missing parameters: %1$s', implode(', ', $missing_keys))
				);
			}

			// Validate "name" field.
			if (is_array($valuemap['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif ($valuemap['name'] === '' || $valuemap['name'] === null || $valuemap['name'] === false) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Value map name cannot be empty.'));
			}
		}

		// Check for duplicate names.
		$duplicate = CArrayHelper::findDuplicate($valuemaps, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "name" value "%1$s" for value map.', $duplicate['name'])
			);
		}

		// Check if value map already exists.
		$db_valuemaps = API::getApiService()->select('valuemaps', [
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($valuemaps, 'name')],
			'limit' => 1
		]);

		if ($db_valuemaps) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Value map "%1$s" already exists.', $db_valuemaps[0]['name'])
			);
		}

		// Validate "mappings" field and its properties.
		$this->checkMappings($valuemaps);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $valuemaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $valuemaps) {
		// Check permissions.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can update value maps.'));
		}

		if (!$valuemaps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// Validate given IDs.
		$this->checkObjectIds($valuemaps, 'valuemapid',
			_('No "%1$s" given for value map.'),
			_('Empty value map ID.'),
			_('Incorrect value map ID.')
		);

		// Check value map names.
		$db_valuemaps = API::getApiService()->select('valuemaps', [
			'output' => ['valuemapid', 'name'],
			'valuemapids' => zbx_objectValues($valuemaps, 'valuemapid'),
			'preservekeys' => true
		]);

		$check_names = [];

		foreach ($valuemaps as $valuemap) {
			// Check if this value map exists.
			if (!array_key_exists($valuemap['valuemapid'], $db_valuemaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Validate "name" field.
			if (array_key_exists('name', $valuemap)) {
				if (is_array($valuemap['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($valuemap['name'] === '' || $valuemap['name'] === null || $valuemap['name'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Value map name cannot be empty.'));
				}

				if ($db_valuemaps[$valuemap['valuemapid']]['name'] !== $valuemap['name']) {
					$check_names[] = $valuemap;
				}
			}
		}

		if ($check_names) {
			// Check for duplicate names.
			$duplicate = CArrayHelper::findDuplicate($check_names, 'name');
			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Duplicate "name" value "%1$s" for value map.', $duplicate['name'])
				);
			}

			// Check if value map already exists.
			$db_valuemap_names = API::getApiService()->select('valuemaps', [
				'output' => ['valuemapid', 'name'],
				'filter' => ['name' => zbx_objectValues($check_names, 'name')]
			]);
			$db_valuemap_names = zbx_toHash($db_valuemap_names, 'name');

			foreach ($check_names as $valuemap) {
				if (array_key_exists($valuemap['name'], $db_valuemap_names)
						&& bccomp($db_valuemap_names[$valuemap['name']]['valuemapid'], $valuemap['valuemapid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Value map "%1$s" already exists.', $valuemap['name'])
					);
				}
			}
		}

		// Populate "name" field, if not set.
		$valuemaps = $this->extendFromObjects(zbx_toHash($valuemaps, 'valuemapid'), $db_valuemaps, ['name']);

		// Validate "mappings" field and its properties.
		$this->checkMappings($valuemaps);
	}

	/**
	 * Check "mappings" field properties.
	 *
	 * @param array $valuemaps									An array of value maps.
	 * @param array $valuemaps[]['mappings']					An array of "mappings" data.
	 * @param string $valuemaps[]['mappings'][]['value']		Original mapping value.
	 * @param string $valuemaps[]['mappings'][]['newvalue']		Value to which the original value is mapped to.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkMappings(array $valuemaps) {
		$required_fields = ['value', 'newvalue'];

		foreach ($valuemaps as $valuemap) {
			if (!array_key_exists('mappings', $valuemap)) {
				continue;
			}

			if (!is_array($valuemap['mappings'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif (!$valuemap['mappings']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('At least one mapping should be given for value map "%1$s".', $valuemap['name'])
				);
			}

			foreach ($valuemap['mappings'] as $mapping) {
				if (!is_array($mapping)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$missing_keys = checkRequiredKeys($mapping, $required_fields);

				if ($missing_keys) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Mapping is missing parameters: %1$s for value map "%2$s".',
						implode(', ', $missing_keys),
						$valuemap['name']
					));
				}
				else {
					foreach (['value', 'newvalue'] as $field) {
						if (is_array($mapping[$field])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
					}
				}

				if ($mapping['newvalue'] === '' || $mapping['newvalue'] === null || $mapping['newvalue'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Empty new value in value map "%1$s".', $valuemap['name'])
					);
				}
			}

			$duplicate = CArrayHelper::findDuplicate($valuemap['mappings'], 'value');
			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Duplicate mapping value "%1$s" for value map "%2$s".', $duplicate['value'], $valuemap['name'])
				);
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// Select mappings for value map.
		if ($options['selectMappings'] !== null) {
			if ($options['selectMappings'] == API_OUTPUT_COUNT) {
				$db_mappings = DBselect(
					'SELECT m.valuemapid,COUNT(*) AS cnt'.
					' FROM mappings m'.
					' WHERE '.dbConditionInt('m.valuemapid', array_keys($result)).
					' GROUP BY m.valuemapid'
				);

				foreach ($result as &$valuemap) {
					$valuemap['mappings'] = 0;
				}
				unset($valuemap);

				while ($db_mapping = DBfetch($db_mappings)) {
					$result[$db_mapping['valuemapid']]['mappings'] = $db_mapping['cnt'];
				}
			}
			else {
				$db_mappings = API::getApiService()->select('mappings', [
					'output' => $this->outputExtend($options['selectMappings'], ['valuemapid']),
					'filter' => ['valuemapid' => array_keys($result)]
				]);

				foreach ($result as &$valuemap) {
					$valuemap['mappings'] = [];
				}
				unset($valuemap);

				foreach ($db_mappings as $db_mapping) {
					$valuemapid = $db_mapping['valuemapid'];
					unset($db_mapping['mappingid'], $db_mapping['valuemapid']);

					$result[$valuemapid]['mappings'][] = $db_mapping;
				}
			}
		}

		return $result;
	}
}

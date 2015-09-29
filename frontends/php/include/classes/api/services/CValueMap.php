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
 * Class containing methods for operations with value maps.
 */
class CValueMap extends CApiService {

	protected $tableName = 'valuemaps';
	protected $tableAlias = 'vm';
	protected $sortColumns = ['valuemapid'];

	/**
	 * Set value map default options in addition to global options.
	 */
	public function __construct() {
		parent::__construct();

		$this->getOptions = array_merge($this->getOptions, [
			'valuemapids'				=> null,
			'editable'					=> null,
			'selectMappings'			=> null,
			'sortfield'					=> '',
			'sortorder'					=> ''
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

		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
		}
		elseif ($options['editable'] === null && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
		}
		elseif ($options['editable'] !== null || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return [];
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

		$valuemapids = DB::insert('valuemaps', $valuemaps);

		$db_valuemaps = API::getApiService()->select('valuemaps', [
			'output' => ['valuemapid', 'name'],
			'valuemapids' => $valuemapids
		]);

		$db_valuemaps = zbx_toHash($db_valuemaps, 'name');

		$mappings = [];

		foreach ($valuemaps as $valuemap) {
			foreach ($valuemap['mappings'] as $mapping) {
				$mappings[] = [
					'valuemapid' => $db_valuemaps[$valuemap['name']]['valuemapid'],
					'value' => $mapping['value'],
					'newvalue' => $mapping['newvalue']
				];
			}
		}

		DB::insertBatch('mappings', $mappings);

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

		$update = [];
		$mappings_create = [];
		$mappings_delete = [];

		foreach ($valuemaps as $valuemap) {
			// Old mappings are deleted and new ones are created.
			if (array_key_exists('mappings', $valuemap)) {
				$mappings_delete[$valuemap['valuemapid']] = true;

				foreach ($valuemap['mappings'] as $mapping) {
					$mappings_create[] = [
						'valuemapid' => $valuemap['valuemapid'],
						'value' => $mapping['value'],
						'newvalue' => $mapping['newvalue']
					];
				}
			}

			$valuemapid = $valuemap['valuemapid'];
			unset($valuemap['valuemapid'], $valuemap['mappings']);

			// Skip updating value maps, if "name" is not given. (Does not check if name is changed).
			if ($valuemap) {
				$update[] = [
					'values' => $valuemap,
					'where' => ['valuemapid' => $valuemapid]
				];
			}
		}

		if ($update) {
			DB::update('valuemaps', $update);
		}

		$valuemapids = zbx_objectValues($valuemaps, 'valuemapid');

		// Update mappings.
		if ($mappings_delete) {
			DB::delete('mappings', ['valuemapid' => array_keys($mappings_delete)]);
			DB::insertBatch('mappings', $mappings_create);
		}

		return ['valuemapids' => $valuemapids];
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
			'output' => ['valuemapid'],
			'valuemapids' => $valuemapids,
			'preservekeys' => true
		]);

		foreach ($valuemapids as $valuemapid) {
			if (!array_key_exists($valuemapid, $db_valuemaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// Mappings are handled with cascade delete.
		$this->deleteByIds($valuemapids);

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
		$duplicate_name = CArrayHelper::findDuplicate($valuemaps, 'name');
		if ($duplicate_name) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "name" value "%1$s" for value map.', $duplicate_name['name'])
			);
		}

		// Check if value map already exists.
		foreach ($valuemaps as $valuemap) {
			$db_valuemap = API::getApiService()->select('valuemaps', [
				'output' => ['name'],
				'filter' => ['name' => $valuemap['name']],
				'limit' => 1
			]);

			if ($db_valuemap) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Value map "%1$s" already exists.', $valuemap['name'])
				);
			}
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

		// Check for duplicate names.
		$duplicate_name = CArrayHelper::findDuplicate($valuemaps, 'name');
		if ($duplicate_name) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "name" value "%1$s" for value map.', $duplicate_name['name'])
			);
		}

		$valuemapids = zbx_objectValues($valuemaps, 'valuemapid');

		// Check
		$db_valuemaps = API::getApiService()->select('valuemaps', [
			'output' => ['valuemapid', 'name'],
			'valuemapids' => $valuemapids,
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

				$check_names[$valuemap['name']] = true;
			}
		}

		// Check if value map already exists.
		if ($check_names) {
			$db_valuemap_names = API::getApiService()->select('valuemaps', [
				'output' => ['valuemapid', 'name'],
				'filter' => ['name' => array_keys($check_names)]
			]);
			$db_valuemap_names = zbx_toHash($db_valuemap_names, 'name');

			foreach ($valuemaps as $valuemap) {
				if (array_key_exists('name', $valuemap)
						&& array_key_exists($valuemap['name'], $db_valuemap_names)
						&& !idcmp($db_valuemap_names[$valuemap['name']]['valuemapid'], $valuemap['valuemapid'])) {
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
		$required_mapping_fields = ['value', 'newvalue'];

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
				$missing_keys = checkRequiredKeys($mapping, $required_mapping_fields);

				if ($missing_keys === null) {
					// object {"value":"1", "newvalue":"2"} given instead of an array [{"value":"1", "newvalue":"2"}].
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'"mappings" property is missing parameters: %1$s for value map "%2$s".',
						implode(', ', $required_mapping_fields),
						$valuemap['name']
					));
				}
				elseif ($missing_keys) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'"mappings" property is missing parameters: %1$s for value map "%2$s".',
						implode(', ', $missing_keys),
						$valuemap['name']
					));
				}
				else {
					foreach ($required_mapping_fields as $field) {
						if (is_array($mapping[$field])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($mapping[$field] === '' || $mapping[$field] === null || $mapping[$field] === false) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Field "%1$s" in "mappings" property is missing a value for value map "%2$s".',
								$field,
								$valuemap['name']
							));
						}
					}
				}
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// Select mappings for value map.
		if ($options['selectMappings'] !== null && $options['selectMappings'] != API_OUTPUT_COUNT) {
			$relation_map = $this->createRelationMap($result, 'valuemapid', 'mappingid', 'mappings');

			// Set output fields.
			if (is_array($options['selectMappings'])) {
				$pk_field = $this->pk('mappings');

				$output_fields = [
					$pk_field => $this->fieldId($pk_field, 'm')
				];

				foreach ($options['selectMappings'] as $field) {
					if ($this->hasField($field, 'mappings')) {
						$output_fields[$field] = $this->fieldId($field, 'm');
					}
				}

				$output_fields = implode(',', $output_fields);
			}
			else {
				$output_fields = 'm.*';
			}

			$db_mappings = DBfetchArray(DBselect(
				'SELECT '.$output_fields.
				' FROM mappings m'.
				' WHERE '.dbConditionInt('m.mappingid', $relation_map->getRelatedIds())
			));
			$db_mappings = zbx_toHash($db_mappings, 'mappingid');

			foreach ($db_mappings as $i => &$db_mapping) {
				// These fields are for internal usage and must not be in output.
				unset($db_mapping['mappingid'], $db_mapping['valuemapid']);

				// Remove record from output if no fields to display.
				if (!$db_mappings[$i]) {
					unset($db_mappings[$i]);
				}
			}
			unset($db_mapping);

			$result = $relation_map->mapMany($result, $db_mappings, 'mappings');
		}

		return $result;
	}
}

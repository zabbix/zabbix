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
 * Class containing methods for operations with images.
 *
 * @package API
 */
class CImage extends CApiService {

	protected $tableName = 'images';
	protected $tableAlias = 'i';
	protected $sortColumns = ['imageid', 'name'];

	/**
	 * Get images data
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['imageids']
	 * @param boolean $options['status']
	 * @param boolean $options['editable']
	 * @param boolean $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 * @return array|boolean image data as array or false if error
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['images' => 'i.imageid'],
			'from'		=> ['images' => 'images i'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'imageids'					=> null,
			'sysmapids'					=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'select_image'				=> null,
			'editable'					=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (!is_null($options['editable']) && self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			return $result;
		}

		// imageids
		if (!is_null($options['imageids'])) {
			zbx_value2array($options['imageids']);
			$sqlParts['where']['imageid'] = dbConditionInt('i.imageid', $options['imageids']);
		}

		// sysmapids
		if (!is_null($options['sysmapids'])) {
			zbx_value2array($options['sysmapids']);

			$sqlParts['from']['sysmaps'] = 'sysmaps sm';
			$sqlParts['from']['sysmaps_elements'] = 'sysmaps_elements se';
			$sqlParts['where']['sm'] = dbConditionInt('sm.sysmapid', $options['sysmapids']);
			$sqlParts['where']['smse_or_bg'] = '('.
				'sm.backgroundid=i.imageid'.
				' OR ('.
					'sm.sysmapid=se.sysmapid'.
					' AND ('.
						'se.iconid_off=i.imageid'.
						' OR se.iconid_on=i.imageid'.
						' OR se.iconid_disabled=i.imageid'.
						' OR se.iconid_maintenance=i.imageid'.
					')'.
				')'.
			')';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('images i', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('images i', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$imageids = [];
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($image = DBfetch($res)) {
			if ($options['countOutput']) {
				return $image['rowscount'];
			}
			else {
				$imageids[$image['imageid']] = $image['imageid'];

				$result[$image['imageid']] = $image;
			}
		}

		// adding objects
		if (!is_null($options['select_image'])) {
			$dbImg = DBselect('SELECT i.imageid,i.image FROM images i WHERE '.dbConditionInt('i.imageid', $imageids));
			while ($img = DBfetch($dbImg)) {
				// PostgreSQL and SQLite images are stored escaped in the DB
				$img['image'] = zbx_unescape_image($img['image']);
				$result[$img['imageid']]['image'] = base64_encode($img['image']);
			}
		}

		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Add images.
	 *
	 * @param array $images ['name' => string, 'image' => string, 'imagetype' => int]
	 *
	 * @return array
	 */
	public function create($images) {
		global $DB;

		$images = zbx_toArray($images);

		$this->validateCreate($images);

		$imageids = [];
		foreach ($images as $image) {
			// decode BASE64
			$image['image'] = base64_decode($image['image']);

			// validate image (size and format)
			$this->checkImage($image['image']);

			$imageid = get_dbid('images', 'imageid');
			$values = [
				'imageid' => $imageid,
				'name' => zbx_dbstr($image['name']),
				'imagetype' => zbx_dbstr($image['imagetype'])
			];

			switch ($DB['TYPE']) {
				case ZBX_DB_ORACLE:
					$values['image'] = 'EMPTY_BLOB()';

					$lob = oci_new_descriptor($DB['DB'], OCI_D_LOB);

					$sql = 'INSERT INTO images ('.implode(' ,', array_keys($values)).') VALUES ('.implode(',', $values).')'.
						' returning image into :imgdata';
					$stmt = oci_parse($DB['DB'], $sql);
					if (!$stmt) {
						$e = oci_error($DB['DB']);
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parse SQL error [%1$s] in [%2$s].', $e['message'], $e['sqltext']));
					}

					oci_bind_by_name($stmt, ':imgdata', $lob, -1, OCI_B_BLOB);
					if (!oci_execute($stmt, OCI_DEFAULT)) {
						$e = oci_error($stmt);
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Execute SQL error [%1$s] in [%2$s].', $e['message'], $e['sqltext']));
					}
					if (!$lob->save($image['image'])) {
						$e = oci_error($stmt);
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Image load error [%1$s] in [%2$s].', $e['message'], $e['sqltext']));
					}
					$lob->free();
					oci_free_statement($stmt);
				break;
				case ZBX_DB_DB2:
					$stmt = db2_prepare($DB['DB'], 'INSERT INTO images ('.implode(' ,', array_keys($values)).',image)'.
						' VALUES ('.implode(',', $values).', ?)');

					if (!$stmt) {
						self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
					}

					$variable = $image['image'];
					if (!db2_bind_param($stmt, 1, "variable", DB2_PARAM_IN, DB2_BINARY)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
					}
					if (!db2_execute($stmt)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
					}
				break;
				case ZBX_DB_SQLITE3:
					$values['image'] = zbx_dbstr(bin2hex($image['image']));
					$sql = 'INSERT INTO images ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
					if (!DBexecute($sql)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					}
				break;
				case ZBX_DB_MYSQL:
						$values['image'] = zbx_dbstr($image['image']);
						$sql = 'INSERT INTO images ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
						if (!DBexecute($sql)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
						}
				break;
				case ZBX_DB_POSTGRESQL:
					$values['image'] = "'".pg_escape_bytea($image['image'])."'";
					$sql = 'INSERT INTO images ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
					if (!DBexecute($sql)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					}
				break;
			}

			$imageids[] = $imageid;
		}

		return ['imageids' => $imageids];
	}

	/**
	 * Update images.
	 *
	 * @param array $images
	 *
	 * @return array (updated images)
	 */
	public function update($images) {
		global $DB;

		$images = zbx_toArray($images);

		$this->validateUpdate($images);

		foreach ($images as $image) {
			$values = [];

			if (isset($image['name'])) {
				$values['name'] = zbx_dbstr($image['name']);
			}

			if (isset($image['image'])) {
				// decode BASE64
				$image['image'] = base64_decode($image['image']);

				// validate image
				$this->checkImage($image['image']);

				switch ($DB['TYPE']) {
					case ZBX_DB_POSTGRESQL:
						$values['image'] = "'".pg_escape_bytea($image['image'])."'";
						break;

					case ZBX_DB_SQLITE3:
						$values['image'] = zbx_dbstr(bin2hex($image['image']));
						break;

					case ZBX_DB_MYSQL:
						$values['image'] = zbx_dbstr($image['image']);
						break;

					case ZBX_DB_ORACLE:
						$sql = 'SELECT i.image FROM images i WHERE i.imageid='.zbx_dbstr($image['imageid']).' FOR UPDATE';

						if (!$stmt = oci_parse($DB['DB'], $sql)) {
							$e = oci_error($DB['DB']);
							self::exception(ZBX_API_ERROR_PARAMETERS, 'SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
						}

						if (!oci_execute($stmt, OCI_DEFAULT)) {
							$e = oci_error($stmt);
							self::exception(ZBX_API_ERROR_PARAMETERS, 'SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
						}

						if (false === ($row = oci_fetch_assoc($stmt))) {
							self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
						}

						$row['IMAGE']->truncate();
						$row['IMAGE']->save($image['image']);
						$row['IMAGE']->free();
						break;

					case ZBX_DB_DB2:
						$stmt = db2_prepare($DB['DB'], 'UPDATE images SET image=? WHERE imageid='.zbx_dbstr($image['imageid']));

						if (!$stmt) {
							self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
						}

						// not unused, db2_bind_param requires variable name as string
						$variable = $image['image'];
						if (!db2_bind_param($stmt, 1, 'variable', DB2_PARAM_IN, DB2_BINARY)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
						}
						if (!db2_execute($stmt)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
						}
						break;
				}
			}

			if ($values) {
				$sqlUpd = [];
				foreach ($values as $field => $value) {
					$sqlUpd[] = $field.'='.$value;
				}
				$sql = 'UPDATE images SET '.implode(', ', $sqlUpd).' WHERE imageid='.zbx_dbstr($image['imageid']);
				$result = DBexecute($sql);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Could not save image!'));
				}
			}
		}

		return ['imageids' => zbx_objectValues($images, 'imageid')];
	}

	/**
	 * Delete images.
	 *
	 * @param array $imageids
	 *
	 * @return array
	 */
	public function delete(array $imageids) {
		if (empty($imageids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty parameters'));
		}

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if icon is used in icon maps
		$dbIconmaps = DBselect(
			'SELECT DISTINCT im.name'.
			' FROM icon_map im,icon_mapping imp'.
			' WHERE im.iconmapid=imp.iconmapid'.
				' AND ('.dbConditionInt('im.default_iconid', $imageids).
					' OR '.dbConditionInt('imp.iconid', $imageids).')'
		);

		$usedInIconmaps = [];
		while ($iconmap = DBfetch($dbIconmaps)) {
			$usedInIconmaps[] = $iconmap['name'];
		}

		if (!empty($usedInIconmaps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_n('The image is used in icon map %1$s.', 'The image is used in icon maps %1$s.',
					'"'.implode('", "', $usedInIconmaps).'"', count($usedInIconmaps))
			);
		}

		// check if icon is used in maps
		$dbSysmaps = DBselect(
			'SELECT DISTINCT sm.sysmapid,sm.name'.
			' FROM sysmaps_elements se,sysmaps sm'.
			' WHERE sm.sysmapid=se.sysmapid'.
				' AND (sm.iconmapid IS NULL'.
					' OR se.use_iconmap='.SYSMAP_ELEMENT_USE_ICONMAP_OFF.')'.
				' AND ('.dbConditionInt('se.iconid_off', $imageids).
					' OR '.dbConditionInt('se.iconid_on', $imageids).
					' OR '.dbConditionInt('se.iconid_disabled', $imageids).
					' OR '.dbConditionInt('se.iconid_maintenance', $imageids).')'.
				' OR '.dbConditionInt('sm.backgroundid', $imageids)
		);

		$usedInMaps = [];
		while ($sysmap = DBfetch($dbSysmaps)) {
			$usedInMaps[] = $sysmap['name'];
		}

		if (!empty($usedInMaps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_n('The image is used in map %1$s.', 'The image is used in maps %1$s.',
				'"'.implode('", "', $usedInMaps).'"', count($usedInMaps))
			);
		}

		DB::update('sysmaps_elements', ['values' => ['iconid_off' => 0], 'where' => ['iconid_off' => $imageids]]);
		DB::update('sysmaps_elements', ['values' => ['iconid_on' => 0], 'where' => ['iconid_on' => $imageids]]);
		DB::update('sysmaps_elements', ['values' => ['iconid_disabled' => 0], 'where' => ['iconid_disabled' => $imageids]]);
		DB::update('sysmaps_elements', ['values' => ['iconid_maintenance' => 0], 'where' => ['iconid_maintenance' => $imageids]]);

		DB::delete('images', ['imageid' => $imageids]);

		return ['imageids' => $imageids];
	}

	/**
	 * Validate create.
	 *
	 * @param array $images
	 *
	 * @throws APIException if user has no permissions.
	 * @throws APIException if wrong fields are passed.
	 * @throws APIException if image with same name already exists.
	 */
	protected function validateCreate(array $images) {
		// validate permissions
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check fields
		foreach ($images as $image) {
			$imageDbFields = [
				'name' => null,
				'image' => null,
				'imagetype' => 1
			];

			if (!check_db_fields($imageDbFields, $image)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for image "%1$s".', $image['name']));
			}
		}

		// check host name duplicates
		$collectionValidator = new CCollectionValidator([
			'uniqueField' => 'name',
			'messageDuplicate' => _('Image "%1$s" already exists.')
		]);
		$this->checkValidator($images, $collectionValidator);

		// check existing names
		$dbImages = API::getApiService()->select($this->tableName(), [
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($images, 'name')],
			'limit' => 1
		]);

		if ($dbImages) {
			$dbImage = reset($dbImages);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Image "%1$s" already exists.', $dbImage['name']));
		}
	}

	/**
	 * Validate update.
	 *
	 * @param array $images
	 *
	 * @throws APIException if user has no permissions.
	 * @throws APIException if wrong fields are passed.
	 * @throws APIException if image with same name already exists.
	 */
	protected function validateUpdate(array $images) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($images as $image) {
			if (!check_db_fields(['imageid'], $image)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}
		}

		$dbImages = API::getApiService()->select($this->tableName(), [
			'filter' => ['imageid' => zbx_objectValues($images, 'imageid')],
			'output' => ['imageid', 'name'],
			'preservekeys' => true
		]);

		$changedImageNames = [];
		foreach ($images as $image) {
			if (!isset($dbImages[$image['imageid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			if (array_key_exists('imagetype', $image)) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update "imagetype" for image "%1$s".', $dbImages[$image['imageid']]['name'])
				);
			}

			if (isset($image['name']) && !zbx_empty($image['name'])
					&& $dbImages[$image['imageid']]['name'] !== $image['name']) {
				if (isset($changedImageNames[$image['name']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Image "%1$s" already exists.', $image['name']));
				}
				else {
					$changedImageNames[$image['name']] = $image['name'];
				}
			}
		}

		// check for existing image names
		if ($changedImageNames) {
			$dbImages = API::getApiService()->select($this->tableName(), [
				'output' => ['name'],
				'filter' => ['name' => $changedImageNames],
				'limit' => 1
			]);

			if ($dbImages) {
				$dbImage = reset($dbImages);
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Image "%1$s" already exists.', $dbImage['name']));
			}
		}
	}

	/**
	 * Validate image.
	 *
	 * @param string $image string representing image, for example, result of base64_decode()
	 *
	 * @throws APIException if image size is 1MB or greater.
	 * @throws APIException if file format is unsupported, GD can not create image from given string
	 */
	protected function checkImage($image) {
		// check size
		if (strlen($image) > ZBX_MAX_IMAGE_SIZE) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Image size must be less than 1MB.'));
		}

		// check file format
		if (@imageCreateFromString($image) === false) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('File format is unsupported.'));
		}
	}

	/**
	 * Unset "image" field from output.
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array  $options
	 * @param array  $sqlParts
	 *
	 * @return array				The resulting SQL parts array
	 */
	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if ($options['countOutput'] === null) {
			if ($options['output'] == API_OUTPUT_EXTEND) {
				$options['output'] = ['imageid', 'imagetype', 'name'];
			}
			elseif (is_array($options['output']) && in_array('image', $options['output'])) {
				foreach ($options['output'] as $idx => $field) {
					if ($field === 'image') {
						unset($options['output'][$idx]);
					}
				}
			}

			$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}
}

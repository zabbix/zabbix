<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 */
class CImage extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'images';
	protected $tableAlias = 'i';
	protected $sortColumns = ['imageid', 'name'];

	/**
	 * Get images data
	 *
	 * @param array   $options
	 * @param array   $options['imageids']
	 * @param array   $options['sysmapids']
	 * @param array   $options['filter']
	 * @param array   $options['search']
	 * @param bool    $options['searchByAny']
	 * @param bool    $options['startSearch']
	 * @param bool    $options['excludeSearch']
	 * @param bool    $options['searchWildcardsEnabled']
	 * @param array   $options['output']
	 * @param int     $options['select_image']
	 * @param bool    $options['editable']
	 * @param bool    $options['countOutput']
	 * @param bool    $options['preservekeys']
	 * @param string  $options['sortfield']
	 * @param string  $options['sortorder']
	 * @param int     $options['limit']
	 *
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
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'select_image'				=> null,
			'editable'					=> false,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($options['editable'] && self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			return [];
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
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
				// PostgreSQL images are stored escaped in the DB
				$img['image'] = zbx_unescape_image($img['image']);
				$result[$img['imageid']]['image'] = base64_encode($img['image']);
			}
		}

		if (!$options['preservekeys']) {
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

		$this->validateCreate($images);

		foreach ($images as &$image) {
			list(,, $img_type) = getimagesizefromstring($image['image']);

			if (!in_array($img_type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
				// Converting to PNG all images except PNG, JPEG and GIF
				$image['image'] = self::convertToPng($image['image']);
			}

			$imageid = get_dbid('images', 'imageid');
			$values = [
				'imageid' => $imageid,
				'name' => zbx_dbstr($image['name']),
				'imagetype' => zbx_dbstr($image['imagetype'])
			];

			switch ($DB['TYPE']) {
				case ZBX_DB_POSTGRESQL:
					$values['image'] = "'".pg_escape_bytea($image['image'])."'";
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
			}

			$image['imageid'] = $imageid;
		}
		unset($image);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_IMAGE, $images);

		return ['imageids' => array_column($images, 'imageid')];
	}

	/**
	 * Validate create.
	 *
	 * @param array $images
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateCreate(array &$images): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'imagetype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => IMAGE_TYPE_ICON.','.IMAGE_TYPE_BACKGROUND],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('images', 'name')],
			'image' =>		['type' => API_IMAGE, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $images, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($images);
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

		$this->validateUpdate($images, $db_images);

		foreach ($images as $image) {
			$values = [];

			if (array_key_exists('name', $image)) {
				$values['name'] = zbx_dbstr($image['name']);
			}

			if (array_key_exists('image', $image)) {
				list(,, $img_type) = getimagesizefromstring($image['image']);

				if (!in_array($img_type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
					// Converting to PNG all images except PNG, JPEG and GIF
					$image['image'] = self::convertToPng($image['image']);
				}

				switch ($DB['TYPE']) {
					case ZBX_DB_POSTGRESQL:
						$values['image'] = "'".pg_escape_bytea($image['image'])."'";
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

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_IMAGE, $images, $db_images);

		return ['imageids' => array_column($images, 'imageid')];
	}

	/**
	 * Validate update.
	 *
	 * @param array      $images
	 * @param array|null $db_images
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateUpdate(array &$images, array &$db_images = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'imageid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('images', 'name')],
			'image' =>		['type' => API_IMAGE, 'flags' => API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $images, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_images = $this->get([
			'output' => ['name', 'imagetype'],
			'imageids' => array_column($images, 'imageid'),
			'preservekeys' => true
		]);

		if (count($db_images) != count($images)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDuplicates($images, $db_images);
	}

	/**
	 * Delete images.
	 *
	 * @param array $imageids
	 *
	 * @return array
	 */
	public function delete(array $imageids) {
		$this->validateDelete($imageids, $db_images);

		DB::update('sysmaps_elements', ['values' => ['iconid_off' => 0], 'where' => ['iconid_off' => $imageids]]);
		DB::update('sysmaps_elements', ['values' => ['iconid_on' => 0], 'where' => ['iconid_on' => $imageids]]);
		DB::update('sysmaps_elements', ['values' => ['iconid_disabled' => 0], 'where' => ['iconid_disabled' => $imageids]]);
		DB::update('sysmaps_elements', ['values' => ['iconid_maintenance' => 0], 'where' => ['iconid_maintenance' => $imageids]]);

		DB::delete('images', ['imageid' => $imageids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_IMAGE, $db_images);

		return ['imageids' => $imageids];
	}

	/**
	 * @param array      $imageids
	 * @param array|null $db_images
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateDelete(array &$imageids, array &$db_images = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $imageids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_images = $this->get([
			'output' => ['imageid', 'name'],
			'imageids' => $imageids,
			'preservekeys' => true
		]);

		if (count($db_images) != count($imageids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkUsedIconMaps($imageids);
		self::checkUsedSysMaps($imageids);
	}

	/**
	 * Unset "image" field from the output.
	 *
	 * @param string $table_name
	 * @param string $table_alias
	 * @param array  $options
	 * @param array  $sql_parts
	 *
	 * @return array The resulting SQL parts array.
	 */
	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts) {
		if (!$options['countOutput']) {
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
		}

		return parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);
	}

	/**
	 * Convert image body to PNG.
	 *
	 * @static
	 *
	 * @param string $image  Base64 encoded body of image.
	 *
	 * @return string
	 */
	protected static function convertToPng($image): string {
		$image = imagecreatefromstring($image);

		ob_start();
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagepng($image);
		imagedestroy($image);

		return ob_get_clean();
	}

	/**
	 * Check for unique image names.
	 *
	 * @static
	 *
	 * @param array      $images
	 * @param array|null $db_images
	 *
	 * @throws APIException if image names are not unique.
	 */
	private static function checkDuplicates(array $images, array $db_images = null): void {
		$names = [];

		foreach ($images as $image) {
			if (!array_key_exists('name', $image)) {
				continue;
			}

			if ($db_images === null || $image['name'] !== $db_images[$image['imageid']]['name']) {
				$names[] = $image['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicate = DBfetch(DBselect('SELECT i.name FROM images i WHERE '.dbConditionString('i.name', $names), 1));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Image "%1$s" already exists.', $duplicate['name']));
		}
	}

	/**
	 * Validate image used in icon mapping.
	 *
	 * @static
	 *
	 * @param array $imageids
	 *
	 * @throws APIException if image used in icon mapping.
	 */
	private static function checkUsedIconMaps(array $imageids): void {
		$used = [];

		$db_iconmaps = DBselect(
			'SELECT DISTINCT im.name'.
			' FROM icon_map im,icon_mapping imp'.
			' WHERE im.iconmapid=imp.iconmapid'.
				' AND ('.dbConditionInt('im.default_iconid', $imageids).
					' OR '.dbConditionInt('imp.iconid', $imageids).')'
		);

		while ($db_iconmap = DBfetch($db_iconmaps)) {
			$used[] = $db_iconmap['name'];
		}

		if ($used) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_n('The image is used in icon map %1$s.', 'The image is used in icon maps %1$s.',
					'"'.implode('", "', $used).'"', count($used))
			);
		}
	}

	/**
	 * Validate image used in maps.
	 *
	 * @static
	 *
	 * @param array $imageids
	 *
	 * @throws APIException if image used in map.
	 */
	private static function checkUsedSysMaps(array $imageids): void {
		$used = [];

		$db_sysmaps = DBselect(
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

		while ($db_sysmap = DBfetch($db_sysmaps)) {
			$used[] = $db_sysmap['name'];
		}

		if ($used) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_n('The image is used in map %1$s.', 'The image is used in maps %1$s.',
				'"'.implode('", "', $used).'"', count($used))
			);
		}
	}
}

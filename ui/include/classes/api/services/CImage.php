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
				$result[$img['imageid']]['image'] = base64_encode($img['image']);
			}
		}

		if (!$options['preservekeys']) {
			$result = array_values($result);
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
	public function create(array $images): array {
		self::validateCreate($images);

		$imageids = DB::insert('images', $images);

		foreach ($images as $index => &$image) {
			$image['imageid'] = $imageids[$index];
		}
		unset($image);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_IMAGE, $images);

		return ['imageids' => array_column($images, 'imageid')];
	}

	/**
	 * @param array $images
	 *
	 * @throws APIException if the input is invalid
	 */
	private static function validateCreate(array &$images): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'imagetype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => IMAGE_TYPE_ICON.','.IMAGE_TYPE_BACKGROUND],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('images', 'name')],
			'image' =>		['type' => API_IMAGE, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $images, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($images);
		self::prepareImages($images);
	}

	/**
	 * Update images.
	 *
	 * @param array $images
	 *
	 * @return array (updated images)
	 */
	public function update(array $images): array {
		self::validateUpdate($images, $db_images);

		$upd_images = [];

		foreach ($images as $image) {
			$upd_image = DB::getUpdatedValues('images', $image, $db_images[$image['imageid']]);

			if ($upd_image) {
				$upd_images[] = [
					'values' => $upd_image,
					'where' => ['imageid' => $image['imageid']]
				];
			}
		}

		if ($upd_images) {
			DB::update('images', $upd_images);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_IMAGE, $images, $db_images);

		return ['imageids' => array_column($images, 'imageid')];
	}

	/**
	 * @param array      $images
	 * @param array|null $db_images
	 *
	 * @throws APIException if the input is invalid
	 */
	private static function validateUpdate(array &$images, ?array &$db_images = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['imageid'], ['name']], 'fields' => [
			'imageid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('images', 'name')],
			'image' =>		['type' => API_IMAGE, 'flags' => API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $images, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_images = DB::select('images', [
			'output' => ['imageid', 'name', 'image'],
			'imageids' => array_column($images, 'imageid'),
			'preservekeys' => true
		]);

		if (count($db_images) != count($images)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDuplicates($images, $db_images);
		self::prepareImages($images);
	}

	/**
	 * Delete images.
	 *
	 * @param array $imageids
	 *
	 * @return array
	 */
	public function delete(array $imageids) {
		self::validateDelete($imageids, $db_images);

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
	private static function validateDelete(array &$imageids, ?array &$db_images = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $imageids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_images = DB::select('images', [
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
	 * @param array      $images
	 * @param array|null $db_images
	 *
	 * @throws APIException if image names are not unique.
	 */
	private static function checkDuplicates(array $images, ?array $db_images = null): void {
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

		$duplicates = DB::select('images', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Image "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * Preparing images before saving to the DB.
	 *
	 * @param array  $images
	 * @param string $images[]['image']  (optional)
	 *
	 * @return string
	 */
	private static function prepareImages(array &$images): void {
		foreach ($images as &$image) {
			if (!array_key_exists('image', $image)) {
				continue;
			}

			list(,, $img_type) = getimagesizefromstring($image['image']);

			// Converting to PNG all images except PNG, JPEG and GIF
			if (!in_array($img_type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
				$image['image'] = self::convertToPng($image['image']);
			}
		}
	}

	/**
	 * Validate image used in icon mapping.
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
			self::exception(ZBX_API_ERROR_PARAMETERS, _n('The image is used in icon map %1$s.',
				'The image is used in icon maps %1$s.', '"'.implode('", "', $used).'"', count($used)
			));
		}
	}

	/**
	 * Validate image used in maps.
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
			self::exception(ZBX_API_ERROR_PARAMETERS, _n('The image is used in map %1$s.',
				'The image is used in maps %1$s.', '"'.implode('", "', $used).'"', count($used)
			));
		}
	}
}

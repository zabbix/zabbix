<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
 * @package API
 */
class CImage extends CZBXAPI {

	protected $tableName = 'images';

	protected $tableAlias = 'i';

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
	public function get($options = array()) {
		$result = array();

		// allowed columns for sorting
		$sortColumns = array('imageid', 'name');

		$sqlParts = array(
			'select'	=> array('images' => 'i.imageid'),
			'from'		=> array('images' => 'images i'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'select_image'				=> null,
			'editable'					=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (!is_null($options['editable']) && self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			return $result;
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// imageids
		if (!is_null($options['imageids'])) {
			zbx_value2array($options['imageids']);
			$sqlParts['where']['imageid'] = DBcondition('i.imageid', $options['imageids']);
		}

		// sysmapids
		if (!is_null($options['sysmapids'])) {
			zbx_value2array($options['sysmapids']);

			$sqlParts['select']['sm'] = 'sm.sysmapid';
			$sqlParts['from']['sysmaps'] = 'sysmaps sm';
			$sqlParts['from']['sysmaps_elements'] = 'sysmaps_elements se';
			$sqlParts['where']['sm'] = DBcondition('sm.sysmapid', $options['sysmapids']);
			$sqlParts['where']['smse'] = 'sm.sysmapid=se.sysmapid ';
			$sqlParts['where']['se'] = '('.
				'se.iconid_off=i.imageid'.
				' OR se.iconid_on=i.imageid'.
				' OR se.iconid_disabled=i.imageid'.
				' OR se.iconid_maintenance=i.imageid'.
				' OR sm.backgroundid=i.imageid)';
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['images'] = 'i.imageid, i.imagetype, i.name';
		}

		// count
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT i.imageid) as rowscount');
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('images i', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('images i', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'i');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$imageids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= ' AND '.implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.DBin_node('i.imageid', $nodeids).
					$sqlWhere.
					$sqlOrder;
		$res = DBselect($sql, $sqlParts['limit']);
		while ($image = DBfetch($res)) {
			if ($options['countOutput']) {
				return $image['rowscount'];
			}
			else {
				$imageids[$image['imageid']] = $image['imageid'];

				if (!isset($result[$image['imageid']])) {
					$result[$image['imageid']] = array();
				}

				// sysmapds
				if (isset($image['sysmapid'])) {
					if (!isset($result[$image['imageid']]['sysmaps'])) {
						$result[$image['imageid']]['sysmaps'] = array();
					}
					$result[$image['imageid']]['sysmaps'][] = array('sysmapid' => $image['sysmapid']);
				}
				$result[$image['imageid']] += $image;
			}
		}

		// adding objects
		if (!is_null($options['select_image'])) {
			$dbImg = DBselect('SELECT i.imageid,i.image FROM images i WHERE '.DBCondition('i.imageid', $imageids));
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
	 * Get images.
	 *
	 * @param array $image
	 * @param array $image['name']
	 * @param array $image['hostid']
	 *
	 * @return array|boolean
	 */
	public function getObjects($imageData) {
		$options = array(
			'filter' => $imageData,
			'output' => API_OUTPUT_EXTEND
		);

		if (isset($imageData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($imageData['node']);
		}
		elseif (isset($imageData['nodeids'])) {
			$options['nodeids'] = $imageData['nodeids'];
		}
		else {
			$options['nodeids'] = get_current_nodeid(true);
		}

		return $this->get($options);
	}

	/**
	 * Check image existence.
	 *
	 * @param array $images
	 * @param array $images['name']
	 *
	 * @return boolean
	 */
	public function exists($object) {
		$keyFields = array(array('imageid', 'name'), 'imagetype');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => array('imageid'),
			'nopermissions' => true,
			'limit' => 1
		);

		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}

		$objs = $this->get($options);

		return !empty($objs);
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
		$imageids = array();

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($images as $image) {
			$imageDbFields = array(
				'name' => null,
				'image' => null,
				'imagetype' => 1
			);

			if (!check_db_fields($imageDbFields, $image)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for image [ '.$image['name'].' ]');
			}
			if ($this->exists(array('name' => $image['name']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Image').' [ '.$image['name'].' ] '._('already exists'));
			}

			// decode BASE64
			$image['image'] = base64_decode($image['image']);

			// validate image
			$this->checkImage($image['image']);

			$imageid = get_dbid('images', 'imageid');
			$values = array(
				'imageid' => $imageid,
				'name' => zbx_dbstr($image['name']),
				'imagetype' => $image['imagetype']
			);

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

		return array('imageids' => $imageids);
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

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($images as $image) {
			if (!isset($image['imageid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for image.'));
			}

			$imageExists = $this->get(array(
				'filter' => array('name' => $image['name']),
				'output' => array('imageid'),
				'nopermissions' => true
			));
			$imageExists = reset($imageExists);

			if ($imageExists && (bccomp($imageExists['imageid'], $image['imageid']) != 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Image').' [ '.$image['name'].' ] '._('already exists'));
			}

			$values = array();
			if (isset($image['name'])) {
				$values['name'] = zbx_dbstr($image['name']);
			}
			if (isset($image['imagetype'])) {
				$values['imagetype'] = $image['imagetype'];
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
						$sql = 'SELECT i.image FROM images i WHERE i.imageid='.$image['imageid'].' FOR UPDATE';

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
						$stmt = db2_prepare($DB['DB'], 'UPDATE images SET image=? WHERE imageid='.$image['imageid']);

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

			$sqlUpd = array();
			foreach ($values as $field => $value) {
				$sqlUpd[] = $field.'='.$value;
			}
			$sql = 'UPDATE images SET '.implode(', ', $sqlUpd).' WHERE imageid='.$image['imageid'];
			$result = DBexecute($sql);

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Could not save image!'));
			}
		}

		return array('imageids' => zbx_objectValues($images, 'imageid'));
	}

	/**
	 * Delete images.
	 *
	 * @param array $imageids
	 *
	 * @return array
	 */
	public function delete($imageids) {
		$imageids = zbx_toArray($imageids);

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
				' AND ('.DBCondition('im.default_iconid', $imageids).
					' OR '.DBCondition('imp.iconid', $imageids).')'
		);

		$usedInIconmaps = array();
		while ($iconmap = DBfetch($dbIconmaps)) {
			$usedInIconmaps[] = $iconmap['name'];
		}

		if (!empty($usedInIconmaps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_n('The image is used in icon map %2$s.', 'The image is used in icon maps %2$s.',
					count($usedInIconmaps), '"'.implode('", "', $usedInIconmaps).'"')
			);
		}

		// check if icon is used in maps
		$dbSysmaps = DBselect(
			'SELECT DISTINCT sm.sysmapid,sm.name'.
			' FROM sysmaps_elements se,sysmaps sm'.
			' WHERE sm.sysmapid=se.sysmapid'.
				' AND (sm.iconmapid IS NULL'.
					' OR se.use_iconmap='.SYSMAP_ELEMENT_USE_ICONMAP_OFF.')'.
				' AND ('.DBCondition('se.iconid_off', $imageids).
					' OR '.DBCondition('se.iconid_on', $imageids).
					' OR '.DBCondition('se.iconid_disabled', $imageids).
					' OR '.DBCondition('se.iconid_maintenance', $imageids).')'.
				' OR '.DBCondition('sm.backgroundid', $imageids)
		);

		$usedInMaps = array();
		while ($sysmap = DBfetch($dbSysmaps)) {
			$usedInMaps[] = $sysmap['name'];
		}

		if (!empty($usedInMaps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_n('The image is used in map %2$s.', 'The image is used in maps %2$s.',
				count($usedInMaps), '"'.implode('", "', $usedInMaps).'"')
			);
		}

		DB::update('sysmaps_elements', array('values' => array('iconid_off' => 0), 'where' => array('iconid_off' => $imageids)));
		DB::update('sysmaps_elements', array('values' => array('iconid_on' => 0), 'where' => array('iconid_on' => $imageids)));
		DB::update('sysmaps_elements', array('values' => array('iconid_disabled' => 0), 'where' => array('iconid_disabled' => $imageids)));
		DB::update('sysmaps_elements', array('values' => array('iconid_maintenance' => 0), 'where' => array('iconid_maintenance' => $imageids)));

		DB::delete('images', array('imageid' => $imageids));

		return array('imageids' => $imageids);
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
}

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
 * Class containing methods for operations with maps.
 */
class CMap extends CMapElement {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_MAPS],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_MAPS],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_MAPS]
	];

	protected $tableName = 'sysmaps';
	protected $tableAlias = 's';
	protected $sortColumns = ['name', 'width', 'height'];

	public const OUTPUT_FIELDS = ['sysmapid', 'userid', 'name', 'width', 'height', 'backgroundid', 'background_scale',
		'iconmapid', 'highlight', 'markelements', 'expandproblem', 'label_format', 'label_type', 'label_type_hostgroup',
		'label_string_hostgroup', 'label_type_host', 'label_string_host', 'label_type_trigger', 'label_string_trigger',
		'label_type_map', 'label_string_map', 'label_type_image', 'label_string_image', 'label_location',
		'show_element_label', 'show_link_label', 'show_unack', 'severity_min', 'show_suppressed', 'private',
		'expand_macros', 'grid_show', 'grid_align', 'grid_size'
	];

	private const LINK_THRESHOLD_TYPE_THRESHOLD = 0;
	private const LINK_THRESHOLD_TYPE_HIGHLIGHT = 1;

	private $defOptions = [
		'sysmapids'					=> null,
		'userids'					=> null,
		'editable'					=> false,
		'nopermissions'				=> null,
		// filter
		'filter'					=> null,
		'search'					=> null,
		'searchByAny'				=> null,
		'startSearch'				=> false,
		'excludeSearch'				=> false,
		'searchWildcardsEnabled'	=> null,
		// output
		'output'					=> API_OUTPUT_EXTEND,
		'selectSelements'			=> null,
		'selectShapes'				=> null,
		'selectLines'				=> null,
		'selectLinks'				=> null,
		'selectIconMap'				=> null,
		'selectUrls'				=> null,
		'selectUsers'				=> null,
		'selectUserGroups'			=> null,
		'countOutput'				=> false,
		'expandUrls' 				=> null,
		'preservekeys'				=> false,
		'sortfield'					=> '',
		'sortorder'					=> '',
		'limit'						=> null
	];

	/**
	 * Get map data.
	 *
	 * @param array  $options
	 * @param array  $options['sysmapids']					Map IDs.
	 * @param bool	 $options['output']						List of map parameters to return.
	 * @param array  $options['selectSelements']			List of map element properties to return.
	 * @param array  $options['selectShapes']				List of map shape properties to return.
	 * @param array  $options['selectLines']				List of map line properties to return.
	 * @param array  $options['selectLinks']				List of map link properties to return.
	 * @param array  $options['selectIconMap']				List of map icon map properties to return.
	 * @param array  $options['selectUrls']					List of map URL properties to return.
	 * @param array  $options['selectUsers']				List of users that the map is shared with.
	 * @param array  $options['selectUserGroups']			List of user groups that the map is shared with.
	 * @param bool	 $options['countOutput']				Return the count of records, instead of actual results.
	 * @param array  $options['userids']					Map owner user IDs.
	 * @param bool   $options['editable']					Return with read-write permission only. Ignored for
	 *														SuperAdmins.
	 * @param bool	 $options['nopermissions']				Return requested maps even if user has no permissions to
	 *														them.
	 * @param array  $options['filter']						List of field and exactly matched value pairs by which maps
	 *														need to be filtered.
	 * @param array  $options['search']						List of field-value pairs by which maps need to be searched.
	 * @param array  $options['expandUrls']					Adds global map URLs to the corresponding map elements and
	 *														expands macros in all map element URLs.
	 * @param bool	 $options['searchByAny']
	 * @param bool	 $options['startSearch']
	 * @param bool	 $options['excludeSearch']
	 * @param bool	 $options['searchWildcardsEnabled']
	 * @param array  $options['preservekeys']				Use IDs as keys in the resulting array.
	 * @param int    $options['limit']						Limit selection.
	 * @param string $options['sortorder']
	 * @param string $options['sortfield']
	 *
	 * @return array|integer Requested map data as array or the count of retrieved objects, if the countOutput
	 *						 parameter has been used.
	 */
	public function get(array $options = []) {
		$options = zbx_array_merge($this->defOptions, $options);

		$limit = $options['limit'];
		$options['limit'] = null;

		if ($options['countOutput']) {
			$count_output = true;
			$options['output'] = ['sysmapid'];
			$options['countOutput'] = false;
		}
		else {
			$count_output = false;
		}

		$result = $this->getMaps($options);

		if ($result && self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$sysmapids = array_flip($this->checkPermissions(array_keys($result), (bool) $options['editable']));

			foreach ($result as $sysmapid => $foo) {
				if (!array_key_exists($sysmapid, $sysmapids)) {
					unset($result[$sysmapid]);
				}
			}
		}

		if ($count_output) {
			return (string) count($result);
		}

		if ($limit !== null) {
			$result = array_slice($result, 0, $limit, true);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
		}

		return $result;
	}

	/**
	 * Returns maps without checking permissions to the elements.
	 */
	private function getMaps(array $options) {
		$sql_parts = [
			'select'	=> ['sysmaps' => 's.sysmapid'],
			'from'		=> ['sysmaps' => 'sysmaps s'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		// Editable + permission check.
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN && !$options['nopermissions']) {
			$public_maps = '';

			if ($options['editable']) {
				$permission = PERM_READ_WRITE;
			}
			else {
				$permission = PERM_READ;
				$public_maps = ' OR s.private='.PUBLIC_SHARING;
			}

			$user_groups = getUserGroupsByUserId(self::$userData['userid']);

			$sql_parts['where'][] = '(EXISTS ('.
					'SELECT NULL'.
					' FROM sysmap_user su'.
					' WHERE s.sysmapid=su.sysmapid'.
						' AND su.userid='.self::$userData['userid'].
						' AND su.permission>='.$permission.
				')'.
				' OR EXISTS ('.
					'SELECT NULL'.
					' FROM sysmap_usrgrp sg'.
					' WHERE s.sysmapid=sg.sysmapid'.
						' AND '.dbConditionInt('sg.usrgrpid', $user_groups).
						' AND sg.permission>='.$permission.
				')'.
				' OR s.userid='.self::$userData['userid'].
				$public_maps.
			')';
		}

		// sysmapids
		if ($options['sysmapids'] !== null) {
			zbx_value2array($options['sysmapids']);
			$sql_parts['where']['sysmapid'] = dbConditionInt('s.sysmapid', $options['sysmapids']);
		}

		// userids
		if ($options['userids'] !== null) {
			zbx_value2array($options['userids']);

			$sql_parts['where'][] = dbConditionInt('s.userid', $options['userids']);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('sysmaps s', $options, $sql_parts);
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('sysmaps s', $options, $sql_parts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$result = [];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sysmaps = DBselect(self::createSelectQueryFromParts($sql_parts), $sql_parts['limit']);

		while ($sysmap = DBfetch($sysmaps)) {
			$result[$sysmap['sysmapid']] = $sysmap;
		}

		return $result;
	}

	/**
	 * Returns maps with selected permission level.
	 *
	 * @param array $sysmapids
	 * @param bool  $editable
	 *
	 * @return array
	 */
	private function checkPermissions(array $sysmapids, $editable) {
		$sysmaps_r = [];
		foreach ($sysmapids as $sysmapid) {
			$sysmaps_r[$sysmapid] = true;
		}

		$selement_maps = [];

		// Populating the map tree $selement_maps and list of shared maps $sysmaps_r.
		do {
			$selements = self::getSelements($sysmapids, SYSMAP_ELEMENT_TYPE_MAP);

			$sysmapids = [];

			foreach ($selements as $sysmapid => $selement) {
				if (!array_key_exists($sysmapid, $sysmaps_r)) {
					$sysmapids[$sysmapid] = true;
				}
			}

			$sysmapids = array_keys($sysmapids);
			$selement_maps += $selements;

			if ($sysmapids) {
				$db_sysmaps = $this->getMaps([
					'output' => [],
					'sysmapids' => $sysmapids,
					'preservekeys' => true
				] + $this->defOptions);

				foreach ($sysmapids as $i => $sysmapid) {
					if (array_key_exists($sysmapid, $db_sysmaps)) {
						$sysmaps_r[$sysmapid] = true;
					}
					else {
						unset($sysmapids[$i]);
					}
				}
			}
		}
		while ($sysmapids);

		$sysmaps_rw = $editable ? $sysmaps_r : [];

		foreach ($sysmaps_r as &$sysmap_r) {
			$sysmap_r = ['permission' => PERM_NONE, 'has_elements' => false];
		}
		unset($sysmap_r);

		self::setHasElements($sysmaps_r, $selement_maps);

		// Setting PERM_READ permission for maps with at least one image.
		$selement_images = self::getSelements(array_keys($sysmaps_r), SYSMAP_ELEMENT_TYPE_IMAGE);
		self::setMapPermissions($sysmaps_r, $selement_images, [0 => []], $selement_maps);
		self::setHasElements($sysmaps_r, $selement_images);

		$sysmapids = self::getSysmapIds($sysmaps_r, $sysmaps_rw);

		// Check permissions to the host groups.
		if ($sysmapids) {
			$selement_groups = self::getSelements($sysmapids, SYSMAP_ELEMENT_TYPE_HOST_GROUP);

			$db_groups = API::HostGroup()->get([
				'output' => [],
				'groupids' => array_keys($selement_groups),
				'preservekeys' => true
			]);

			if ($editable) {
				self::unsetMapsByElements($sysmaps_rw, $selement_groups, $db_groups);
			}
			self::setMapPermissions($sysmaps_r, $selement_groups, $db_groups, $selement_maps);
			self::setHasElements($sysmaps_r, $selement_groups);

			$sysmapids = self::getSysmapIds($sysmaps_r, $sysmaps_rw);
		}

		// Check permissions to the hosts.
		if ($sysmapids) {
			$selement_hosts = self::getSelements($sysmapids, SYSMAP_ELEMENT_TYPE_HOST);

			$db_hosts = API::Host()->get([
				'output' => [],
				'hostids' => array_keys($selement_hosts),
				'preservekeys' => true
			]);

			if ($editable) {
				self::unsetMapsByElements($sysmaps_rw, $selement_hosts, $db_hosts);
			}
			self::setMapPermissions($sysmaps_r, $selement_hosts, $db_hosts, $selement_maps);
			self::setHasElements($sysmaps_r, $selement_hosts);

			$sysmapids = self::getSysmapIds($sysmaps_r, $sysmaps_rw);
		}

		// Check permissions to the triggers.
		if ($sysmapids) {
			$selement_triggers = self::getSelements($sysmapids, SYSMAP_ELEMENT_TYPE_TRIGGER);
			$link_triggers = self::getLinkTriggers($sysmapids);

			$db_triggers = API::Trigger()->get([
				'output' => [],
				'triggerids' => array_keys($selement_triggers + $link_triggers),
				'preservekeys' => true
			]);

			if ($editable) {
				self::unsetMapsByElements($sysmaps_rw, $selement_triggers, $db_triggers);
				self::unsetMapsByElements($sysmaps_rw, $link_triggers, $db_triggers);
			}
			self::setMapPermissions($sysmaps_r, $selement_triggers, $db_triggers, $selement_maps);
			self::setMapPermissions($sysmaps_r, $link_triggers, $db_triggers, $selement_maps);
			self::setHasElements($sysmaps_r, $selement_triggers);
			self::setHasElements($sysmaps_r, $link_triggers);

			$sysmapids = self::getSysmapIds($sysmaps_r, $sysmaps_rw);
		}

		// Check permissions to items.
		if ($sysmapids) {
			$link_items = self::getLinkItems($sysmapids);

			$db_items = API::Item()->get([
				'output' => [],
				'itemids' => array_keys($link_items),
				'preservekeys' => true
			]);

			if ($editable) {
				self::unsetMapsByElements($sysmaps_rw, $link_items, $db_items);
			}

			self::setMapPermissions($sysmaps_r, $link_items, $db_items, $selement_maps);
			self::setHasElements($sysmaps_r, $link_items);
		}

		foreach ($sysmaps_r as $sysmapid => $sysmap_r) {
			if (!$sysmap_r['has_elements']) {
				self::setMapPermission($sysmaps_r, $selement_maps, $sysmapid);
			}
		}

		foreach ($sysmaps_r as $sysmapid => $sysmap_r) {
			if ($sysmap_r['permission'] == PERM_NONE) {
				unset($sysmaps_r[$sysmapid]);
			}
		}

		if ($editable) {
			self::unsetMapsByTree($sysmaps_rw, $sysmaps_r, $selement_maps);
		}

		return array_keys($editable ? $sysmaps_rw : $sysmaps_r);
	}

	/**
	 * Returns map elements for selected maps.
	 */
	private static function getSelements(array $sysmapids, $elementtype) {
		$selements = [];

		switch ($elementtype) {
			case SYSMAP_ELEMENT_TYPE_IMAGE:
				$sql = 'SELECT se.sysmapid,0 AS elementid'.
					' FROM sysmaps_elements se'.
					' WHERE '.dbConditionInt('se.sysmapid', $sysmapids).
						' AND '.dbConditionInt('se.elementtype', [$elementtype]);
				break;

			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
			case SYSMAP_ELEMENT_TYPE_HOST:
			case SYSMAP_ELEMENT_TYPE_MAP:
				$sql = 'SELECT se.sysmapid,se.elementid'.
					' FROM sysmaps_elements se'.
					' WHERE '.dbConditionInt('se.sysmapid', $sysmapids).
						' AND '.dbConditionInt('se.elementtype', [$elementtype]);
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$sql = 'SELECT se.sysmapid,st.triggerid AS elementid'.
					' FROM sysmaps_elements se,sysmap_element_trigger st'.
					' WHERE se.selementid=st.selementid'.
						' AND '.dbConditionInt('se.sysmapid', $sysmapids).
						' AND '.dbConditionInt('se.elementtype', [$elementtype]);
				break;
		}
		$db_selements = DBSelect($sql);

		while ($db_selement = DBfetch($db_selements)) {
			$selements[$db_selement['elementid']][] = ['sysmapid' => $db_selement['sysmapid']];
		}

		return $selements;
	}

	/**
	 * Returns map links for selected maps.
	 */
	private static function getLinkTriggers(array $sysmapids) {
		$link_triggers = [];

		$db_links = DBSelect(
			'SELECT sl.sysmapid,slt.triggerid'.
			' FROM sysmaps_links sl,sysmaps_link_triggers slt'.
			' WHERE sl.linkid=slt.linkid'.
				' AND '.dbConditionInt('sl.sysmapid', $sysmapids)
		);

		while ($db_link = DBfetch($db_links)) {
			$link_triggers[$db_link['triggerid']][] = ['sysmapid' => $db_link['sysmapid']];
		}

		return $link_triggers;
	}

	private static function getLinkItems(array $sysmapids): array {
		$link_items = [];

		$resource = DBselect(
			'SELECT sl.sysmapid,sl.itemid'.
			' FROM sysmaps_links sl'.
			' WHERE sl.indicator_type='.MAP_INDICATOR_TYPE_ITEM_VALUE.
				' AND '.dbConditionId('sl.sysmapid', $sysmapids)
		);

		while ($db_link = DBfetch($resource)) {
			$link_items[$db_link['itemid']][] = ['sysmapid' => $db_link['sysmapid']];
		}

		return $link_items;
	}

	/**
	 * Removes all inaccessible maps by map tree.
	 *
	 * @param array $sysmaps_rw[<sysmapids>]                  The list of writable maps.
	 * @param array $sysmaps_r[<sysmapids>]                   The list of readable maps.
	 * @param array $selement_maps                            The map tree.
	 * @param array $selement_maps[<sysmapid>][]['sysmapid']  Parent map ID.
	 */
	private static function unsetMapsByTree(array &$sysmaps_rw, array $sysmaps_r, array $selement_maps) {
		foreach ($selement_maps as $child_sysmapid => $selements) {
			if (!array_key_exists($child_sysmapid, $sysmaps_r)) {
				foreach ($selements as $selement) {
					unset($sysmaps_rw[$selement['sysmapid']]);
				}
			}
		}
	}

	/**
	 * Removes all inaccessible maps by inaccessible elements.
	 *
	 * @param array $sysmaps_rw[<sysmapids>]              The list of writable maps.
	 * @param array $elements                             The map elements.
	 * @param array $elements[<elementid>][]['sysmapid']  Map ID.
	 * @param array $db_elements                          The list of readable elements.
	 * @param array $db_elements[<elementid>]
	 */
	private static function unsetMapsByElements(array &$sysmaps_rw, array $elements, array $db_elements) {
		foreach ($elements as $elementid => $selements) {
			if (!array_key_exists($elementid, $db_elements)) {
				foreach ($selements as $selement) {
					unset($sysmaps_rw[$selement['sysmapid']]);
				}
			}
		}
	}

	/**
	 * Set PERM_READ permission for map and all parent maps.
	 *
	 * @param array  $sysmaps_r[<sysmapids>]                   The list of readable maps.
	 * @param array  $selement_maps                            The map elements.
	 * @param array  $selement_maps[<sysmapid>][]['sysmapid']  Map ID.
	 * @param string $sysmapid
	 */
	private static function setMapPermission(array &$sysmaps_r, array $selement_maps, $sysmapid) {
		if (array_key_exists($sysmapid, $selement_maps)) {
			foreach ($selement_maps[$sysmapid] as $selement) {
				self::setMapPermission($sysmaps_r, $selement_maps, $selement['sysmapid']);
			}
		}
		$sysmaps_r[$sysmapid]['permission'] = PERM_READ;
	}

	/**
	 * Setting PERM_READ permissions for maps with at least one available element.
	 *
	 * @param array $sysmaps_r[<sysmapids>]                   The list of readable maps.
	 * @param array $elements                                 The map elements.
	 * @param array $elements[<elementid>][]['sysmapid']      Map ID.
	 * @param array $db_elements                              The list of readable elements.
	 * @param array $db_elements[<elementid>]
	 * @param array $selement_maps                            The map elements.
	 * @param array $selement_maps[<sysmapid>][]['sysmapid']  Map ID.
	 */
	private static function setMapPermissions(array &$sysmaps_r, array $elements, array $db_elements,
			array $selement_maps) {
		foreach ($elements as $elementid => $selements) {
			if (array_key_exists($elementid, $db_elements)) {
				foreach ($selements as $selement) {
					self::setMapPermission($sysmaps_r, $selement_maps, $selement['sysmapid']);
				}
			}
		}
	}

	/**
	 * Setting "has_elements" flag for maps.
	 *
	 * @param array $sysmaps_r[<sysmapids>]                   The list of readable maps.
	 * @param array $elements                                 The map elements.
	 * @param array $elements[<elementid>]
	 */
	private static function setHasElements(array &$sysmaps_r, array $elements) {
		foreach ($elements as $elementid => $selements) {
			foreach ($selements as $selement) {
				$sysmaps_r[$selement['sysmapid']]['has_elements'] = true;
			}
		}
	}

	/**
	 * Returns map ids which will be checked for permissions.
	 *
	 * @param array $sysmaps_r
	 * @param array $sysmaps_r[<sysmapid>]['permission']
	 * @param array $sysmaps_rw
	 * @param array $sysmaps_rw[<sysmapid>]
	 */
	private static function getSysmapIds(array $sysmaps_r, array $sysmaps_rw) {
		$sysmapids = $sysmaps_rw;

		foreach ($sysmaps_r as $sysmapid => $sysmap) {
			if ($sysmap['permission'] == PERM_NONE) {
				$sysmapids[$sysmapid] = true;
			}
		}

		return array_keys($sysmapids);
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $sysmapids
	 * @param array $db_maps
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array $sysmapids, ?array &$db_maps = null) {
		if (!$sysmapids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$db_maps = $this->get([
			'output' => ['sysmapid', 'name'],
			'sysmapids' => $sysmapids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($sysmapids as $sysmapid) {
			if (!array_key_exists($sysmapid, $db_maps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}
	}

	/**
	 * Validate the input parameters for the create() method.
	 *
	 * @param array $maps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$maps): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'background_scale' =>	['type' => API_INT32, 'in' => implode(',', [SYSMAP_BACKGROUND_SCALE_NONE, SYSMAP_BACKGROUND_SCALE_COVER])],
			'show_element_label' =>	['type' => API_INT32, 'in' => implode(',', [MAP_SHOW_LABEL_AUTO_HIDE, MAP_SHOW_LABEL_ALWAYS])],
			'show_link_label' =>	['type' => API_INT32, 'in' => implode(',', [MAP_SHOW_LABEL_AUTO_HIDE, MAP_SHOW_LABEL_ALWAYS])],
			'selements' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['selementid']], 'fields' => [
				'selementid' =>			['type' => API_SELEMENTID],
				'show_label' =>			['type' => API_INT32, 'in' => implode(',', [MAP_SHOW_LABEL_DEFAULT, MAP_SHOW_LABEL_AUTO_HIDE, MAP_SHOW_LABEL_ALWAYS])],
				'zindex' =>				['type' => API_INT32]
			]],
			'links' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
				'linkid' =>				['type' => API_UNEXPECTED],
				'item_value_type' =>	['type' => API_UNEXPECTED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $maps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateLinks($maps);

		$user_data = self::$userData;

		$map_db_fields = [
			'name' => null,
			'width' => null,
			'height' => null,
			'urls' => [],
			'selements' => [],
			'links' => []
		];

		// Validate mandatory fields and map name.
		foreach ($maps as $map) {
			if (!check_db_fields($map_db_fields, $map)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect fields for sysmap.'));
			}
		}

		// Check for duplicate names.
		$duplicate = CArrayHelper::findDuplicate($maps, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "name" value "%1$s" for map.', $duplicate['name'])
			);
		}

		// Check if map already exists.
		$db_maps = $this->get([
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($maps, 'name')],
			'nopermissions' => true,
			'limit' => 1
		]);

		if ($db_maps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Map "%1$s" already exists.', $db_maps[0]['name']));
		}

		$private_validator = new CLimitedSetValidator([
			'values' => [PUBLIC_SHARING, PRIVATE_SHARING]
		]);

		$permission_validator = new CLimitedSetValidator([
			'values' => [PERM_READ, PERM_READ_WRITE]
		]);

		$show_suppressed_types = [ZBX_PROBLEM_SUPPRESSED_FALSE, ZBX_PROBLEM_SUPPRESSED_TRUE];
		$show_suppressed_validator = new CLimitedSetValidator(['values' => $show_suppressed_types]);

		$expandproblem_types = [SYSMAP_PROBLEMS_NUMBER, SYSMAP_SINGLE_PROBLEM, SYSMAP_PROBLEMS_NUMBER_CRITICAL];
		$expandproblem_validator = new CLimitedSetValidator(['values' => $expandproblem_types]);

		// Continue to check 2 more mandatory fields and other optional fields.
		foreach ($maps as $map_index => $map) {
			// Check mandatory fields "width" and "height".
			if ($map['width'] > 65535 || $map['width'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "width" value for map "%1$s".', $map['name'])
				);
			}

			if ($map['height'] > 65535 || $map['height'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "height" value for map "%1$s".', $map['name'])
				);
			}

			// Check if owner can be set.
			if (array_key_exists('userid', $map)) {
				if ($map['userid'] === '' || $map['userid'] === null || $map['userid'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Map owner cannot be empty.'));
				}
				elseif ($map['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
						&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only administrators can set map owner.'));
				}
			}

			// Check for invalid "private" values.
			if (array_key_exists('private', $map)) {
				if (!$private_validator->validate($map['private'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect "private" value "%1$s" for map "%2$s".', $map['private'], $map['name'])
					);
				}
			}

			// Check for invalid "show_suppressed" values.
			if (array_key_exists('show_suppressed', $map)
					&& !$show_suppressed_validator->validate($map['show_suppressed'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'show_suppressed', _s('value must be one of %1$s', implode(', ', $show_suppressed_types))
				));
			}

			if (array_key_exists('expandproblem', $map) && !$expandproblem_validator->validate($map['expandproblem'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'expandproblem',
					_s('value must be one of %1$s', implode(', ', $expandproblem_types))
				));
			}

			$userids = [];

			// Map user shares.
			if (array_key_exists('users', $map)) {
				if (!is_array($map['users'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$required_fields = ['userid', 'permission'];

				foreach ($map['users'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in users for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if (array_key_exists('private', $map) && $map['private'] == PUBLIC_SHARING
							&& $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Duplicate userid "%1$s" in users for map "%2$s".', $share['userid'], $map['name'])
						);
					}

					$userids[$share['userid']] = $share['userid'];
				}
			}

			if (array_key_exists('userid', $map) && $map['userid']) {
				$userids[$map['userid']] = $map['userid'];
			}

			// Users validation.
			if ($userids) {
				$db_users = API::User()->get([
					'userids' => $userids,
					'countOutput' => true
				]);

				if (count($userids) != $db_users) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect user ID specified for map "%1$s".', $map['name'])
					);
				}
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $map)) {
				if (!is_array($map['userGroups'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$shared_user_groupids = [];
				$required_fields = ['usrgrpid', 'permission'];

				foreach ($map['userGroups'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User group sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Field "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in user groups for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if (array_key_exists('private', $map) && $map['private'] == PUBLIC_SHARING
							&& $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['usrgrpid'], $shared_user_groupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Duplicate "usrgrpid" "%1$s" in user groups for map "%2$s".',
							$share['usrgrpid'],
							$map['name']
						));
					}

					$shared_user_groupids[$share['usrgrpid']] = $share['usrgrpid'];
				}

				if ($shared_user_groupids) {
					$db_user_groups = API::UserGroup()->get([
						'usrgrpids' => $shared_user_groupids,
						'countOutput' => true
					]);

					if (count($shared_user_groupids) != $db_user_groups) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect user group ID specified for map "%1$s".', $map['name'])
						);
					}
				}

				unset($shared_user_groupids);
			}

			// Map labels.
			$map_labels = ['label_type' => ['typeName' => _('icon')]];

			if (array_key_exists('label_format', $map) && $map['label_format'] == SYSMAP_LABEL_ADVANCED_ON) {
				$map_labels['label_type_hostgroup'] = [
					'string' => 'label_string_hostgroup',
					'typeName' => _('host group')
				];
				$map_labels['label_type_host'] = [
					'string' => 'label_string_host',
					'typeName' => _('host')
				];
				$map_labels['label_type_trigger'] = [
					'string' => 'label_string_trigger',
					'typeName' => _('trigger')
				];
				$map_labels['label_type_map'] = [
					'string' => 'label_string_map',
					'typeName' => _('map')
				];
				$map_labels['label_type_image'] = [
					'string' => 'label_string_image',
					'typeName' => _('image')
				];
			}

			foreach ($map_labels as $label_name => $label_data) {
				if (!array_key_exists($label_name, $map)) {
					continue;
				}

				if (sysmapElementLabel($map[$label_name]) === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
					);
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_CUSTOM) {
					if (!array_key_exists('string', $label_data)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
						);
					}

					if (!array_key_exists($label_data['string'], $map) || zbx_empty($map[$label_data['string']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Custom label for map "%2$s" elements of type "%1$s" may not be empty.',
								$label_data['typeName'],
								$map['name']
							)
						);
					}
				}

				if ($label_name == 'label_type_image' && $map[$label_name] == MAP_LABEL_TYPE_STATUS) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
					);
				}

				if ($label_name !== 'label_type') {
					$label_string_length = DB::getFieldLength('sysmaps', $label_data['string']);

					if (array_key_exists($label_data['string'], $map)
							&& mb_strlen($map[$label_data['string']]) > $label_string_length) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', $label_data['string'], _('value is too long'))
						);
					}
				}

				if ($label_name === 'label_type' || $label_name === 'label_type_host') {
					continue;
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_IP) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
					);
				}
			}

			// Validating grid options.
			$possibleGridSizes = [20, 40, 50, 75, 100];

			// Grid size.
			if (array_key_exists('grid_size', $map) && !in_array($map['grid_size'], $possibleGridSizes)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s".',
					$map['grid_size'],
					implode('", "', $possibleGridSizes)
				));
			}

			// Grid auto align.
			if (array_key_exists('grid_align', $map) && $map['grid_align'] != SYSMAP_GRID_ALIGN_ON
					&& $map['grid_align'] != SYSMAP_GRID_ALIGN_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_align". Choices are: "%2$s" and "%3$s"',
					$map['grid_align'],
					SYSMAP_GRID_ALIGN_ON,
					SYSMAP_GRID_ALIGN_OFF
				));
			}

			// Grid show.
			if (array_key_exists('grid_show', $map) && $map['grid_show'] != SYSMAP_GRID_SHOW_ON
					&& $map['grid_show'] != SYSMAP_GRID_SHOW_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s" and "%3$s".',
					$map['grid_show'],
					SYSMAP_GRID_SHOW_ON,
					SYSMAP_GRID_SHOW_OFF
				));
			}

			// Urls.
			if (array_key_exists('urls', $map) && $map['urls']) {
				$url_names = zbx_toHash($map['urls'], 'name');

				foreach ($map['urls'] as $url) {
					if ($url['name'] === '' || $url['url'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL should have both "name" and "url" fields for map "%1$s".', $map['name'])
						);
					}

					if (!array_key_exists($url['name'], $url_names)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL name should be unique for map "%1$s".', $map['name'])
						);
					}

					$url_validate_options = ['allow_user_macro' => false];
					if ($url['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
						$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_HOST;
					}
					elseif ($url['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
						$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_TRIGGER;
					}
					else {
						$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_NONE;
					}

					if (!CHtmlUrlValidator::validate($url['url'], $url_validate_options)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong value for "url" field.'));
					}

					unset($url_names[$url['name']]);
				}
			}

			if (array_key_exists('selements', $map)) {
				if (!CMapHelper::checkSelementPermissions($map['selements'])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}

				foreach (array_values($map['selements']) as $selement_index => $selement) {
					$this->validateSelementTags($selement, '/'.($map_index + 1).'/selements/'.($selement_index + 1));
				}
			}
		}
	}

	/**
	 * Validate the input parameters for the update() method.
	 *
	 * @param array $maps			maps data array
	 * @param array $db_maps		db maps data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$maps, ?array &$db_maps): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['sysmapid']], 'fields' => [
			'sysmapid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $maps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_maps = $this->get([
			'output' => self::OUTPUT_FIELDS,
			'sysmapids' => array_column($maps, 'sysmapid'),
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font', 'font_size',
				'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width', 'border_color',
				'background_color', 'zindex'
			],
			'selectLines' => ['sysmap_shapeid', 'x1', 'y1', 'x2', 'y2', 'line_type', 'line_width', 'line_color',
				'zindex'
			],
			'selectUrls' => ['sysmapid', 'sysmapurlid', 'name', 'url', 'elementtype'],
			'selectUsers' => ['sysmapuserid', 'sysmapid', 'userid', 'permission'],
			'selectUserGroups' => ['sysmapusrgrpid', 'sysmapid', 'usrgrpid', 'permission'],
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_maps) != count($maps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'sysmapid' =>			['type' => API_ANY],
			'background_scale' =>	['type' => API_INT32, 'in' => implode(',', [SYSMAP_BACKGROUND_SCALE_NONE, SYSMAP_BACKGROUND_SCALE_COVER])],
			'show_element_label' =>	['type' => API_INT32, 'in' => implode(',', [MAP_SHOW_LABEL_AUTO_HIDE, MAP_SHOW_LABEL_ALWAYS])],
			'show_link_label' =>	['type' => API_INT32, 'in' => implode(',', [MAP_SHOW_LABEL_AUTO_HIDE, MAP_SHOW_LABEL_ALWAYS])],
			'selements' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['selementid']], 'fields' => [
				'selementid' =>			['type' => API_SELEMENTID],
				'show_label' =>			['type' => API_INT32, 'in' => implode(',', [MAP_SHOW_LABEL_DEFAULT, MAP_SHOW_LABEL_AUTO_HIDE, MAP_SHOW_LABEL_ALWAYS])],
				'zindex' =>				['type' => API_INT32]
			]],
			'links' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['linkid']], 'fields' => [
				'linkid' =>				['type' => API_ID],
				'item_value_type' =>	['type' => API_UNEXPECTED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $maps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::addAffectedObjects($maps, $db_maps);

		self::validateLinks($maps, $db_maps);

		$user_data = self::$userData;

		$check_names = [];

		foreach ($maps as $map) {
			// Validate "name" field.
			if (array_key_exists('name', $map)) {
				if (is_array($map['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($map['name'] === '' || $map['name'] === null || $map['name'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Map name cannot be empty.'));
				}

				if ($db_maps[$map['sysmapid']]['name'] !== $map['name']) {
					$check_names[] = $map;
				}
			}
		}

		if ($check_names) {
			// Check for duplicate names.
			$duplicate = CArrayHelper::findDuplicate($check_names, 'name');
			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Duplicate "name" value "%1$s" for map.', $duplicate['name'])
				);
			}

			$db_map_names = $this->get([
				'output' => ['sysmapid', 'name'],
				'filter' => ['name' => zbx_objectValues($check_names, 'name')],
				'nopermissions' => true
			]);
			$db_map_names = zbx_toHash($db_map_names, 'name');

			// Check for existing names.
			foreach ($check_names as $map) {
				if (array_key_exists($map['name'], $db_map_names)
						&& bccomp($db_map_names[$map['name']]['sysmapid'], $map['sysmapid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Map "%1$s" already exists.', $map['name'])
					);
				}
			}
		}

		$private_validator = new CLimitedSetValidator([
			'values' => [PUBLIC_SHARING, PRIVATE_SHARING]
		]);

		$permission_validator = new CLimitedSetValidator([
			'values' => [PERM_READ, PERM_READ_WRITE]
		]);

		$show_suppressed_types = [ZBX_PROBLEM_SUPPRESSED_FALSE, ZBX_PROBLEM_SUPPRESSED_TRUE];
		$show_suppressed_validator = new CLimitedSetValidator(['values' => $show_suppressed_types]);

		$expandproblem_types = [SYSMAP_PROBLEMS_NUMBER, SYSMAP_SINGLE_PROBLEM, SYSMAP_PROBLEMS_NUMBER_CRITICAL];
		$expandproblem_validator = new CLimitedSetValidator(['values' => $expandproblem_types]);

		foreach ($maps as $map_index => $map) {
			// Check if owner can be set.
			if (array_key_exists('userid', $map)) {
				if ($map['userid'] === '' || $map['userid'] === null || $map['userid'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Map owner cannot be empty.'));
				}
				elseif ($map['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
						&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only administrators can set map owner.'));
				}
			}

			// Unset extra field.
			unset($db_maps[$map['sysmapid']]['userid']);

			$map = array_merge($db_maps[$map['sysmapid']], $map);

			// Check "width" and "height" fields.
			if ($map['width'] > 65535 || $map['width'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "width" value for map "%1$s".', $map['name'])
				);
			}

			if ($map['height'] > 65535 || $map['height'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "height" value for map "%1$s".', $map['name'])
				);
			}

			if (!$private_validator->validate($map['private'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "private" value "%1$s" for map "%2$s".', $map['private'], $map['name'])
				);
			}

			// Check for invalid "show_suppressed" values.
			if (array_key_exists('show_suppressed', $map)
					&& !$show_suppressed_validator->validate($map['show_suppressed'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'show_suppressed', _s('value must be one of %1$s', implode(', ', $show_suppressed_types))
				));
			}

			if (array_key_exists('expandproblem', $map) && !$expandproblem_validator->validate($map['expandproblem'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'expandproblem',
					_s('value must be one of %1$s', implode(', ', $expandproblem_types))
				));
			}

			$userids = [];

			// Map user shares.
			if (array_key_exists('users', $map)) {
				if (!is_array($map['users'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$required_fields = ['userid', 'permission'];

				foreach ($map['users'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in users for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if ($map['private'] == PUBLIC_SHARING && $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Duplicate userid "%1$s" in users for map "%2$s".', $share['userid'], $map['name'])
						);
					}

					$userids[$share['userid']] = $share['userid'];
				}
			}

			if (array_key_exists('userid', $map) && $map['userid']) {
				$userids[$map['userid']] = $map['userid'];
			}

			// Users validation.
			if ($userids) {
				$db_users = API::User()->get([
					'userids' => $userids,
					'countOutput' => true
				]);

				if (count($userids) != $db_users) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect user ID specified for map "%1$s".', $map['name'])
					);
				}
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $map)) {
				if (!is_array($map['userGroups'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$shared_user_groupids = [];
				$required_fields = ['usrgrpid', 'permission'];

				foreach ($map['userGroups'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User group sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name'])
						);
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in user groups for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if ($map['private'] == PUBLIC_SHARING && $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['usrgrpid'], $shared_user_groupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Duplicate "usrgrpid" "%1$s" in user groups for map "%2$s".',
							$share['usrgrpid'],
							$map['name']
						));
					}

					$shared_user_groupids[$share['usrgrpid']] = $share['usrgrpid'];
				}

				if ($shared_user_groupids) {
					$db_user_groups = API::UserGroup()->get([
						'usrgrpids' => $shared_user_groupids,
						'countOutput' => true
					]);

					if (count($shared_user_groupids) != $db_user_groups) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect user group ID specified for map "%1$s".', $map['name'])
						);
					}
				}

				unset($shared_user_groupids);
			}

			// Map labels.
			$map_labels = ['label_type' => ['typeName' => _('icon')]];

			if (array_key_exists('label_format', $map)
					&& $map['label_format'] == SYSMAP_LABEL_ADVANCED_ON) {
				$map_labels['label_type_hostgroup'] = [
					'string' => 'label_string_hostgroup',
					'typeName' => _('host group')
				];
				$map_labels['label_type_host'] = [
					'string' => 'label_string_host',
					'typeName' => _('host')
				];
				$map_labels['label_type_trigger'] = [
					'string' => 'label_string_trigger',
					'typeName' => _('trigger')
				];
				$map_labels['label_type_map'] = [
					'string' => 'label_string_map',
					'typeName' => _('map')
				];
				$map_labels['label_type_image'] = [
					'string' => 'label_string_image',
					'typeName' => _('image')
				];
			}

			foreach ($map_labels as $label_name => $labelData) {
				if (!array_key_exists($label_name, $map)) {
					continue;
				}

				if (sysmapElementLabel($map[$label_name]) === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect %1$s label type value for map "%2$s".',
						$labelData['typeName'],
						$map['name']
					));
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_CUSTOM) {
					if (!array_key_exists('string', $labelData)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect %1$s label type value for map "%2$s".',
							$labelData['typeName'],
							$map['name']
						));
					}

					if (!array_key_exists($labelData['string'], $map) || zbx_empty($map[$labelData['string']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Custom label for map "%2$s" elements of type "%1$s" may not be empty.',
							$labelData['typeName'],
							$map['name']
						));
					}
				}

				if ($label_name === 'label_type_image' && $map[$label_name] == MAP_LABEL_TYPE_STATUS) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect %1$s label type value for map "%2$s".',
						$labelData['typeName'],
						$map['name']
					));
				}

				if ($label_name !== 'label_type') {
					$label_string_length = DB::getFieldLength('sysmaps', $labelData['string']);

					if (array_key_exists($labelData['string'], $map)
							&& mb_strlen($map[$labelData['string']]) > $label_string_length) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', $labelData['string'], _('value is too long'))
						);
					}
				}

				if ($label_name === 'label_type' || $label_name === 'label_type_host') {
					continue;
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_IP) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect %1$s label type value for map "%2$s".',
						$labelData['typeName'],
						$map['name']
					));
				}
			}

			// Validating grid options.
			$possibleGridSizes = [20, 40, 50, 75, 100];

			// Grid size.
			if (array_key_exists('grid_size', $map) && !in_array($map['grid_size'], $possibleGridSizes)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s".',
					$map['grid_size'],
					implode('", "', $possibleGridSizes)
				));
			}

			// Grid auto align.
			if (array_key_exists('grid_align', $map) && $map['grid_align'] != SYSMAP_GRID_ALIGN_ON
					&& $map['grid_align'] != SYSMAP_GRID_ALIGN_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_align". Choices are: "%2$s" and "%3$s"',
					$map['grid_align'],
					SYSMAP_GRID_ALIGN_ON,
					SYSMAP_GRID_ALIGN_OFF
				));
			}

			// Grid show.
			if (array_key_exists('grid_show', $map) && $map['grid_show'] != SYSMAP_GRID_SHOW_ON
					&& $map['grid_show'] != SYSMAP_GRID_SHOW_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s" and "%3$s".',
					$map['grid_show'],
					SYSMAP_GRID_SHOW_ON,
					SYSMAP_GRID_SHOW_OFF
				));
			}

			// Urls.
			if (array_key_exists('urls', $map) && !empty($map['urls'])) {
				$urlNames = zbx_toHash($map['urls'], 'name');

				foreach ($map['urls'] as $url) {
					if ($url['name'] === '' || $url['url'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL should have both "name" and "url" fields for map "%1$s".', $map['name'])
						);
					}

					if (!array_key_exists($url['name'], $urlNames)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL name should be unique for map "%1$s".', $map['name'])
						);
					}

					$url_validate_options = ['allow_user_macro' => false];
					if ($url['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
						$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_HOST;
					}
					elseif ($url['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
						$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_TRIGGER;
					}
					else {
						$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_NONE;
					}

					if (!CHtmlUrlValidator::validate($url['url'], $url_validate_options)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong value for "url" field.'));
					}

					unset($urlNames[$url['name']]);
				}
			}

			if (array_key_exists('selements', $map)) {
				foreach (array_values($map['selements']) as $selement_index => $selement) {
					$this->validateSelementTags($selement, '/'.($map_index + 1).'/selements/'.($selement_index + 1));
				}
			}
		}

		// Validate circular reference.
		foreach ($maps as &$map) {
			$map = array_merge($db_maps[$map['sysmapid']], $map);
			$this->cref_maps[$map['sysmapid']] = $map;
		}
		unset($map);

		$this->validateCircularReference($maps);
	}

	private static function addAffectedObjects(array $maps, array &$db_maps): void {
		self::addAffectedSelements($maps, $db_maps);
		self::addAffectedLinks($maps, $db_maps);
	}

	private static function addAffectedSelements(array $maps, array &$db_maps): void {
		$sysmapids = [];

		foreach ($maps as $map) {
			if (array_key_exists('selements', $map) || array_key_exists('links', $map)) {
				$sysmapids[] = $map['sysmapid'];
				$db_maps[$map['sysmapid']]['selements'] = [];
			}
		}

		if (!$sysmapids) {
			return;
		}

		$options = [
			'output' => ['selementid', 'sysmapid', 'elementtype', 'elementsubtype', 'areatype', 'width', 'height',
				'viewtype', 'label', 'label_location', 'show_label', 'elementid', 'evaltype', 'use_iconmap',
				'iconid_off', 'iconid_on', 'iconid_maintenance', 'iconid_disabled', 'x', 'y'
			],
			'filter' => ['sysmapid' => $sysmapids]
		];
		$resource = DBselect(DB::makeSql('sysmaps_elements', $options));

		$db_selements = [];

		while ($db_selement = DBfetch($resource)) {
			$db_maps[$db_selement['sysmapid']]['selements'][$db_selement['selementid']] =
				array_diff_key($db_selement, array_flip(['sysmapid']));

			$db_selements[$db_selement['selementid']] =
				&$db_maps[$db_selement['sysmapid']]['selements'][$db_selement['selementid']];
		}

		if (!$db_selements) {
			return;
		}

		self::addAffectedSelementElements($db_selements);
		self::addAffectedSelementUrls($db_selements);
		self::addAffectedSelementTags($db_selements);
	}

	private static function addAffectedSelementElements(array &$db_selements): void {
		$trigger_selementids = [];

		foreach ($db_selements as $selementid => &$db_selement) {
			$db_selement['elements'] = [];

			switch ($db_selement['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST:
					$db_selement['elements'][] = ['hostid' => $db_selement['elementid']];
					break;

				case SYSMAP_ELEMENT_TYPE_MAP:
					$db_selement['elements'][] = ['sysmapid' => $db_selement['elementid']];
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$trigger_selementids[] = $selementid;
					break;

				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$db_selement['elements'][] = ['groupid' => $db_selement['elementid']];
					break;
			}

			unset($db_selement['elementid']);
		}
		unset($db_selement);

		if ($trigger_selementids) {
			$resource = DBselect(
				'SELECT st.selement_triggerid,st.selementid,st.triggerid'.
				' FROM sysmap_element_trigger st,triggers t'.
				' WHERE st.triggerid=t.triggerid'.
					' AND '.dbConditionId('st.selementid', $trigger_selementids).
				' ORDER BY t.priority DESC,st.selement_triggerid'
			);

			while ($db_trigger = DBfetch($resource)) {
				$db_selements[$db_trigger['selementid']]['elements'][$db_trigger['selement_triggerid']] =
					array_diff_key($db_trigger, array_flip(['selementid']));
			}
		}
	}

	private static function addAffectedSelementUrls(array &$db_selements): void {
		foreach ($db_selements as &$db_selement) {
			$db_selement['urls'] = [];
		}
		unset($db_selement);

		$options = [
			'output' => ['sysmapelementurlid', 'selementid', 'name', 'url'],
			'filter' => ['selementid' => array_keys($db_selements)]
		];
		$resource = DBselect(DB::makeSql('sysmap_element_url', $options));

		while ($db_url = DBfetch($resource)) {
			$db_selements[$db_url['selementid']]['urls'][$db_url['sysmapelementurlid']] =
				array_diff_key($db_url, array_flip(['selementid']));
		}
	}

	private static function addAffectedSelementTags(array &$db_selements): void {
		foreach ($db_selements as &$db_selement) {
			$db_selement['tags'] = [];
		}
		unset($db_selement);

		$options = [
			'output' => ['selementtagid', 'selementid', 'tag', 'value', 'operator'],
			'filter' => ['selementid' => array_keys($db_selements)]
		];
		$resource = DBselect(DB::makeSql('sysmaps_element_tag', $options));

		while ($db_tag = DBfetch($resource)) {
			$db_selements[$db_tag['selementid']]['tags'][$db_tag['selementtagid']] =
				array_diff_key($db_tag, array_flip(['selementid']));
		}
	}

	private static function addAffectedLinks(array $maps, array &$db_maps): void {
		$sysmapids = [];

		foreach ($maps as $map) {
			if (array_key_exists('selements', $map) || array_key_exists('links', $map)) {
				$sysmapids[] = $map['sysmapid'];
				$db_maps[$map['sysmapid']]['links'] = [];
			}
		}

		if (!$sysmapids) {
			return;
		}

		$resource = DBselect(
			'SELECT sl.linkid,sl.sysmapid,sl.selementid1,sl.selementid2,sl.label,sl.show_label,sl.drawtype,sl.color,'.
				'sl.indicator_type,sl.itemid,'.dbConditionCoalesce('i.value_type', -1, 'item_value_type').
			' FROM sysmaps_links sl'.
			' LEFT JOIN items i ON sl.itemid=i.itemid'.
			' WHERE '.dbConditionId('sl.sysmapid', $sysmapids)
		);

		$db_links = [];

		while ($db_link = DBfetch($resource)) {
			$db_maps[$db_link['sysmapid']]['links'][$db_link['linkid']] =
				array_diff_key($db_link, array_flip(['sysmapid']));

			$db_links[$db_link['linkid']] = &$db_maps[$db_link['sysmapid']]['links'][$db_link['linkid']];
		}

		if (!$db_links) {
			return;
		}

		self::addAffectedLinkTriggers($db_links);
		self::addAffectedLinkThresholds($db_links);
		self::addAffectedLinkHighlights($db_links);
	}

	private static function addAffectedLinkTriggers(array &$db_links): void {
		$linkids = [];

		foreach ($db_links as &$db_link) {
			$db_link['linktriggers'] = [];

			if ($db_link['indicator_type'] == MAP_INDICATOR_TYPE_TRIGGER) {
				$linkids[] = $db_link['linkid'];
			}
		}
		unset($db_link);

		if (!$linkids) {
			return;
		}

		$options = [
			'output' => ['linktriggerid', 'linkid', 'triggerid', 'drawtype', 'color'],
			'filter' => ['linkid' => $linkids]
		];
		$resource = DBselect(DB::makeSql('sysmaps_link_triggers', $options));

		while ($db_trigger = DBfetch($resource)) {
			$db_links[$db_trigger['linkid']]['linktriggers'][$db_trigger['linktriggerid']] =
				array_diff_key($db_trigger, array_flip(['linkid']));
		}
	}

	private static function addAffectedLinkThresholds(array &$db_links): void {
		$linkids = [];

		foreach ($db_links as &$db_link) {
			$db_link['thresholds'] = [];

			if ($db_link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE) {
				$linkids[] = $db_link['linkid'];
			}
		}
		unset($db_link);

		if (!$linkids) {
			return;
		}

		$resource = DBselect(
			'SELECT slt.linkthresholdid,slt.linkid,slt.threshold,slt.drawtype,slt.color'.
			' FROM sysmap_link_threshold slt'.
			' WHERE slt.type='.self::LINK_THRESHOLD_TYPE_THRESHOLD.
				' AND '.dbConditionId('slt.linkid', $linkids)
		);

		while ($db_threshold = DBfetch($resource)) {
			$db_links[$db_threshold['linkid']]['thresholds'][$db_threshold['linkthresholdid']] =
				array_diff_key($db_threshold, array_flip(['linkid']));
		}
	}

	private static function addAffectedLinkHighlights(array &$db_links): void {
		$linkids = [];

		foreach ($db_links as &$db_link) {
			$db_link['highlights'] = [];

			if ($db_link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE) {
				$linkids[] = $db_link['linkid'];
			}
		}
		unset($db_link);

		if (!$linkids) {
			return;
		}

		$resource = DBselect(
			'SELECT slt.linkthresholdid,slt.linkid,slt.pattern,slt.sortorder,slt.drawtype,slt.color'.
			' FROM sysmap_link_threshold slt'.
			' WHERE slt.type='.self::LINK_THRESHOLD_TYPE_HIGHLIGHT.
				' AND '.dbConditionId('slt.linkid', $linkids)
		);

		while ($db_threshold = DBfetch($resource)) {
			$db_links[$db_threshold['linkid']]['highlights'][$db_threshold['linkthresholdid']] =
				array_diff_key($db_threshold, array_flip(['linkid']));
		}
	}

	private static function validateLinks(array &$maps, ?array $db_maps = null): void {
		self::validateLinkIndicatorTypes($maps, $db_maps);

		if ($db_maps !== null) {
			self::addRequiredFieldsByLinkIndicatorType($maps, $db_maps);
		}

		self::validateLinkItemids($maps, $db_maps);

		self::checkLinkItems($maps, $db_maps, $db_items, $link_indexes);
		self::addLinkItemValueType($maps, $db_items, $link_indexes);

		if ($db_maps !== null) {
			self::addRequiredFieldsByItemValueType($maps, $db_maps);
		}

		self::validateLinkFields($maps);

		if ($db_maps !== null) {
			self::addLinkSelementids($maps, $db_maps);
		}

		self::checkLinkSelementids($maps, $db_maps);
		self::checkLinkTriggers($maps, $db_maps);
	}

	private static function validateLinkIndicatorTypes(array &$maps, ?array $db_maps): void {
		foreach ($maps as $i1 => &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			$path = '/'.($i1 + 1).'/links';

			foreach ($map['links'] as $i2 => &$link) {
				$is_update = array_key_exists('linkid', $link);

				if ($is_update && !array_key_exists($link['linkid'], $db_maps[$map['sysmapid']]['links'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/linkid', _('object does not exist or belongs to another object')
					));
				}

				$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'indicator_type' =>	['type' => API_INT32, 'in' => implode(',', [MAP_INDICATOR_TYPE_STATIC_LINK, MAP_INDICATOR_TYPE_TRIGGER, MAP_INDICATOR_TYPE_ITEM_VALUE])] + ($is_update ? [] : ['default' => DB::getDefault('sysmaps_links', 'indicator_type')])
				]];

				if (!CApiInputValidator::validate($api_input_rules, $link, $path.'/'.($i2 + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				if ($is_update) {
					$link += [
						'indicator_type' => $db_maps[$map['sysmapid']]['links'][$link['linkid']]['indicator_type']
					];
				}
			}
			unset($link);
		}
		unset($map);
	}

	private static function addRequiredFieldsByLinkIndicatorType(array &$maps, array $db_maps): void {
		foreach ($maps as &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			foreach ($map['links'] as &$link) {
				if (!array_key_exists('linkid', $link)) {
					continue;
				}

				$db_link = $db_maps[$map['sysmapid']]['links'][$link['linkid']];

				if ($link['indicator_type'] != $db_link['indicator_type']
						&& $link['indicator_type'] == MAP_INDICATOR_TYPE_TRIGGER) {
					$link += ['linktriggers' => $db_link['linktriggers']];
				}
				elseif ($link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE) {
					$link += array_intersect_key($db_link, array_flip(['itemid', 'item_value_type']));
				}
			}
			unset($link);
		}
		unset($map);
	}

	private static function validateLinkItemids(array &$maps, ?array $db_maps): void {
		foreach ($maps as $i1 => &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			$path = '/'.($i1 + 1).'/links';

			foreach ($map['links'] as $i2 => &$link) {
				$is_update = array_key_exists('linkid', $link);

				$api_required = $is_update ? 0 : API_REQUIRED;

				$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'itemid' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'indicator_type', 'in' => MAP_INDICATOR_TYPE_ITEM_VALUE], 'type' => API_ID, 'flags' => $api_required],
									['else' => true, 'type' => API_ID, 'in' => '0']
					]]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $link, $path.'/'.($i2 + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				if ($is_update && $link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE) {
					$link += ['itemid' => $db_maps[$map['sysmapid']]['links'][$link['linkid']]['itemid']];
				}
			}
			unset($link);
		}
		unset($map);
	}

	private static function checkLinkItems(array $maps, ?array $db_maps, ?array &$db_items = null,
			?array &$link_indexes = null): void {
		$db_items = [];
		$link_indexes = [];

		foreach ($maps as $i1 => $map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			$db_links = $db_maps !== null ? $db_maps[$map['sysmapid']]['links'] : [];

			foreach ($map['links'] as $i2 => $link) {
				if ($link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE
						&& (!array_key_exists('linkid', $link)
							|| bccomp($link['itemid'], $db_links[$link['linkid']]['itemid']) != 0
							|| $link['itemid'] == 0)) {
					$link_indexes[$link['itemid']][$i1][] = $i2;
				}
			}
		}

		if (!$link_indexes) {
			return;
		}

		$db_items = API::Item()->get([
			'output' => ['value_type'],
			'selectHosts' => ['status'],
			'webitems' => true,
			'itemids' => array_keys($link_indexes),
			'preservekeys' => true
		]);

		$host_statuses = [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED];

		foreach ($link_indexes as $itemid => $indexes) {
			$i1 = key($indexes);
			$i2 = reset($indexes[$i1]);
			$path = '/'.($i1 + 1).'/links/'.($i2 + 1).'/itemid';

			if (!array_key_exists($itemid, $db_items)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', $path,
						_('object does not exist, or you have no permissions to it')
					)
				);
			}

			if (!in_array($db_items[$itemid]['hosts'][0]['status'], $host_statuses)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', $path, _('host item ID is expected'))
				);
			}

			if ($db_items[$itemid]['value_type'] == ITEM_VALUE_TYPE_BINARY) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', $path, _('binary item is not supported'))
				);
			}
		}
	}

	private static function addLinkItemValueType(array &$maps, array $db_items, array $link_indexes): void {
		foreach ($link_indexes as $itemid => $map_indexes) {
			foreach ($map_indexes as $i1 => $indexes) {
				foreach ($indexes as $i2) {
					$maps[$i1]['links'][$i2]['item_value_type'] = $db_items[$itemid]['value_type'];
				}
			}
		}
	}

	private static function addRequiredFieldsByItemValueType(array &$maps, array $db_maps): void {
		foreach ($maps as &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			foreach ($map['links'] as &$link) {
				if (!array_key_exists('linkid', $link) || $link['indicator_type'] != MAP_INDICATOR_TYPE_ITEM_VALUE) {
					continue;
				}

				$db_link = $db_maps[$map['sysmapid']]['links'][$link['linkid']];

				if (in_array($link['item_value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
					if (!array_key_exists('thresholds', $link)) {
						$link['thresholds'] = [];

						foreach ($db_link['thresholds'] as $db_threshold) {
							$link['thresholds'][] = array_intersect_key($db_threshold,
								array_flip(['threshold', 'drawtype', 'color'])
							);
						}
					}
				}
				elseif (!array_key_exists('highlights', $link)) {
					$link['highlights'] = [];

					foreach ($db_link['highlights'] as $db_highlight) {
						$link['highlights'][] = array_intersect_key($db_highlight,
							array_flip(['pattern', 'sortorder', 'drawtype', 'color'])
						);
					}
				}
			}
			unset($link);
		}
		unset($map);
	}

	private static function validateLinkFields(array &$maps): void {
		foreach ($maps as $i1 => &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			$path = '/'.($i1 + 1).'/links';

			foreach ($map['links'] as $i2 => &$link) {
				$is_update = array_key_exists('linkid', $link);

				$api_input_rules = ['type' => API_OBJECT, 'fields' => self::getLinkValidationFields($is_update)];

				if (!CApiInputValidator::validate($api_input_rules, $link, $path.'/'.($i2 + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
			unset($link);
		}
		unset($map);
	}

	private static function getLinkValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$specific_rules = $is_update
			? [
				'linkid' =>	['type' => API_ANY]
			]
			: [];

		return $specific_rules + [
			'selementid1' =>		['type' => API_SELEMENTID, 'flags' => $api_required],
			'selementid2' =>		['type' => API_SELEMENTID, 'flags' => $api_required],
			'label' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmaps_links', 'label')],
			'show_label' =>			['type' => API_INT32, 'in' => implode(',', [MAP_SHOW_LABEL_DEFAULT, MAP_SHOW_LABEL_AUTO_HIDE, MAP_SHOW_LABEL_ALWAYS])],
			'drawtype' =>			['type' => API_INT32, 'in' => implode(',', [DRAWTYPE_LINE, DRAWTYPE_BOLD_LINE, DRAWTYPE_DOT, DRAWTYPE_DASHED_LINE])],
			'color' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'indicator_type' =>		['type' => API_ANY],
			'linktriggers' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'indicator_type', 'in' => MAP_INDICATOR_TYPE_TRIGGER], 'type' => API_OBJECTS, 'flags' => $api_required | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['triggerid']], 'fields' => [
											'triggerid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
											'drawtype' =>	['type' => API_INT32, 'in' => implode(',', [DRAWTYPE_LINE, DRAWTYPE_BOLD_LINE, DRAWTYPE_DOT, DRAWTYPE_DASHED_LINE])],
											'color' =>		['type' => API_COLOR, 'flags' => API_NOT_EMPTY]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'itemid' =>				['type' => API_ANY],
			'item_value_type' =>	['type' => API_ANY],
			'thresholds' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn($data): bool => $data['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE && in_array($data['item_value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]), 'type' => API_OBJECTS, 'flags' => $api_required | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['threshold']], 'fields' => [
											'threshold' =>	['type' => API_NUMERIC, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sysmap_link_threshold', 'threshold')],
											'drawtype' =>	['type' => API_INT32, 'in' => implode(',', [DRAWTYPE_LINE, DRAWTYPE_BOLD_LINE, DRAWTYPE_DOT, DRAWTYPE_DASHED_LINE])],
											'color' =>		['type' => API_COLOR, 'flags' => API_NOT_EMPTY]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'highlights' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn($data): bool => $data['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE && in_array($data['item_value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]), 'type' => API_OBJECTS, 'flags' => $api_required | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['pattern'], ['sortorder']], 'fields' => [
											'pattern' =>	['type' => API_REGEX, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sysmap_link_threshold', 'pattern')],
											'sortorder' =>	['type' => API_INT32, 'flags' => API_REQUIRED],
											'drawtype' =>	['type' => API_INT32, 'in' => implode(',', [DRAWTYPE_LINE, DRAWTYPE_BOLD_LINE, DRAWTYPE_DOT, DRAWTYPE_DASHED_LINE])],
											'color' =>		['type' => API_COLOR, 'flags' => API_NOT_EMPTY]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]]
		];
	}

	private static function addLinkSelementids(array &$maps, array $db_maps): void {
		foreach ($maps as &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			foreach ($map['links'] as &$link) {
				if (array_key_exists('linkid', $link)) {
					$link += array_intersect_key($db_maps[$map['sysmapid']]['links'][$link['linkid']],
						array_flip(['selementid1', 'selementid2'])
					);
				}
			}
			unset($link);
		}
		unset($map);
	}

	private static function checkLinkSelementids(array $maps, ?array $db_maps): void {
		foreach ($maps as $i1 => $map) {
			if (!array_key_exists('links', $map) || !$map['links']) {
				continue;
			}

			$selementids = array_key_exists('selements', $map)
				? array_column($map['selements'], 'selementid')
				: array_column($db_maps[$map['sysmapid']]['selements'], 'selementid');
			$path = '/'.($i1 + 1).'/links';

			foreach ($map['links'] as $i2 => $link) {
				if (!in_array($link['selementid1'], $selementids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/selementid1', _('object does not exist or belongs to another object')
					));
				}

				if (!in_array($link['selementid2'], $selementids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/selementid2', _('object does not exist or belongs to another object')
					));
				}
			}
		}
	}

	private static function checkLinkTriggers(array $maps, ?array $db_maps): void {
		$link_indexes = [];

		foreach ($maps as $i1 => $map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			$db_links = $db_maps !== null ? $db_maps[$map['sysmapid']]['links'] : [];

			foreach ($map['links'] as $i2 => $link) {
				if ($link['indicator_type'] != MAP_INDICATOR_TYPE_TRIGGER) {
					continue;
				}

				$db_link_triggers = array_key_exists('linkid', $link)
					? array_column($db_links[$link['linkid']]['linktriggers'], null, 'triggerid')
					: [];

				foreach ($link['linktriggers'] as $i3 => $linktrigger) {
					if (!array_key_exists($linktrigger['triggerid'], $db_link_triggers)) {
						$link_indexes[$linktrigger['triggerid']][$i1][$i2] = $i3;
					}
				}
			}
		}

		if (!$link_indexes) {
			return;
		}

		$db_triggers = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['status'],
			'triggerids' => array_keys($link_indexes),
			'preservekeys' => true
		]);

		$host_statuses = [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED];

		foreach ($link_indexes as $triggerid => $indexes) {
			$i1 = key($indexes);
			$i2 = key($indexes[$i1]);
			$i3 = $indexes[$i1][$i2];
			$path = '/'.($i1 + 1).'/links/'.($i2 + 1).'/linktriggers/'.($i3 + 1).'/triggerid';

			if (!array_key_exists($triggerid, $db_triggers)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', $path,
						_('object does not exist, or you have no permissions to it')
					)
				);
			}

			if (!in_array($db_triggers[$triggerid]['hosts'][0]['status'], $host_statuses)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', $path, _('host trigger ID is expected'))
				);
			}
		}
	}

	/**
	 * Validate Map element tag properties.
	 *
	 * @param array  $selement['evaltype']
	 * @param array  $selement['tags']
	 * @param string $selement['tags'][]['tag']
	 * @param string $selement['tags'][]['value']
	 * @param int    $selement['tags'][]['operator']
	 * @param string $path
	 *
	 * @throws APIException if input is invalid.
	 */
	protected function validateSelementTags(array $selement, string $path): void {
		if (!array_key_exists('tags', $selement)) {
			return;
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'evaltype' =>	['type' => API_INT32, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]), 'default' => DB::getDefault('sysmaps_elements', 'evaltype')],
			'tags' =>		['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sysmaps_element_tag', 'tag')],
				'value' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmaps_element_tag', 'value'), 'default' => DB::getDefault('sysmaps_element_tag', 'value')],
				'operator' =>	['type' => API_STRING_UTF8, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS]), 'default' => DB::getDefault('sysmaps_element_tag', 'operator')]
			]]
		]];

		$data = array_intersect_key($selement, $api_input_rules['fields']);
		if (!CApiInputValidator::validate($api_input_rules, $data, $path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Hash of maps data for circular reference validation. Map id is used as key.
	 *
	 * @var array
	 */
	protected $cref_maps;

	/**
	 * Validate maps for circular reference.
	 *
	 * @param array $maps   Array of maps to be validated for circular reference.
	 *
	 * @throws APIException if input is invalid.
	 */
	protected function validateCircularReference(array $maps) {
		foreach ($maps as $map) {
			if (!array_key_exists('selements', $map) || !$map['selements']) {
				continue;
			}
			$cref_mapids = array_key_exists('sysmapid', $map) ? [$map['sysmapid']] : [];

			foreach ($map['selements'] as $selement) {
				if (!$this->validateCircularReferenceRecursive($selement, $cref_mapids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot add map element of the map "%1$s" due to circular reference.', $map['name'])
					);
				}
			}
		}
	}

	/**
	 * Recursive map element circular reference validation.
	 *
	 * @param array $selement       Map selement data array.
	 * @param array $cref_mapids    Array of map ids for current recursion step.
	 *
	 * @return bool
	 */
	protected function validateCircularReferenceRecursive(array $selement, &$cref_mapids) {
		if ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_MAP) {
			return true;
		}

		$sysmapid = $selement['elements'][0]['sysmapid'];

		if ($sysmapid !== null && !array_key_exists($sysmapid, $this->cref_maps)) {
			$db_maps = DB::select($this->tableName, [
				'output' => ['name'],
				'filter' => ['sysmapid' => $sysmapid]
			]);

			if ($db_maps) {
				$db_map = $db_maps[0];
				$db_map['selements'] = [];
				$selements = DB::select('sysmaps_elements', [
					'output' => ['elementid'],
					'filter' => [
						'sysmapid' => $sysmapid,
						'elementtype' => SYSMAP_ELEMENT_TYPE_MAP
					]
				]);

				foreach ($selements as $selement) {
					$db_map['selements'][] = [
						'elementtype' => SYSMAP_ELEMENT_TYPE_MAP,
						'elements' => [
							['sysmapid' => $selement['elementid']]
						]
					];
				}

				$this->cref_maps[$sysmapid] = $db_map;
				unset($selements);
			}
			else {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		if (in_array($sysmapid, $cref_mapids)) {
			$cref_mapids[] = $sysmapid;
			return false;
		}

		// Find maps that reference the current element, and if one has selements, check all of them recursively.
		if ($sysmapid !== null && array_key_exists('selements', $this->cref_maps[$sysmapid])
				&& is_array($this->cref_maps[$sysmapid]['selements'])) {
			$cref_mapids[] = $sysmapid;

			foreach ($this->cref_maps[$sysmapid]['selements'] as $selement) {
				if (!$this->validateCircularReferenceRecursive($selement, $cref_mapids)) {
					return false;
				}
			}

			array_pop($cref_mapids);
		}

		return true;
	}

	/**
	 * Add map.
	 *
	 * @param array  $maps
	 * @param string $maps['name']
	 * @param array  $maps['width']
	 * @param int    $maps['height']
	 * @param string $maps['backgroundid']
	 * @param string $maps['highlight']
	 * @param array  $maps['label_type']
	 * @param int    $maps['label_location']
	 * @param int    $maps['grid_size']			size of one grid cell. 100 refers to 100x100 and so on.
	 * @param int    $maps['grid_show']			does grid need to be shown. Constants: SYSMAP_GRID_SHOW_ON / SYSMAP_GRID_SHOW_OFF
	 * @param int    $maps['grid_align']		do elements need to be aligned to the grid. Constants: SYSMAP_GRID_ALIGN_ON / SYSMAP_GRID_ALIGN_OFF
	 *
	 * @return array
	 */
	public function create(array $maps): array {
		$this->validateCreate($maps);

		foreach ($maps as &$map) {
			$map['userid'] = array_key_exists('userid', $map) ? $map['userid'] : self::$userData['userid'];
		}
		unset($map);

		$sysmapids = DB::insert('sysmaps', $maps);

		foreach ($maps as $key => &$map) {
			$map['sysmapid'] = $sysmapids[$key];
		}
		unset($map);

		$shared_users = [];
		$shared_user_groups = [];
		$urls = [];
		$shapes = [];
		$api_shape_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
			'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SYSMAP_SHAPE_TYPE_RECTANGLE, SYSMAP_SHAPE_TYPE_ELLIPSE])],
			'x' =>					['type' => API_INT32],
			'y' =>					['type' => API_INT32],
			'width' =>				['type' => API_INT32],
			'height' =>				['type' => API_INT32],
			'font' =>				['type' => API_INT32, 'in' => '0:12'],
			'font_size' =>			['type' => API_INT32, 'in' => '1:250'],
			'text_halign' =>		['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_LABEL_HALIGN_CENTER, SYSMAP_SHAPE_LABEL_HALIGN_LEFT, SYSMAP_SHAPE_LABEL_HALIGN_RIGHT])],
			'text_valign' =>		['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_LABEL_VALIGN_MIDDLE, SYSMAP_SHAPE_LABEL_VALIGN_TOP, SYSMAP_SHAPE_LABEL_VALIGN_BOTTOM])],
			'border_type' =>		['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_BORDER_TYPE_NONE, SYSMAP_SHAPE_BORDER_TYPE_SOLID, SYSMAP_SHAPE_BORDER_TYPE_DOTTED, SYSMAP_SHAPE_BORDER_TYPE_DASHED])],
			'border_width' =>		['type' => API_INT32, 'in' => '0:50'],
			'border_color' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'border_color')],
			'background_color' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'background_color')],
			'font_color' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'font_color')],
			'text' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'text')],
			'zindex' =>				['type' => API_INT32]
		]];
		$api_line_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
			'x1' =>					['type' => API_INT32],
			'y1' =>					['type' => API_INT32],
			'x2' =>					['type' => API_INT32],
			'y2' =>					['type' => API_INT32],
			'line_type' =>			['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_BORDER_TYPE_NONE, SYSMAP_SHAPE_BORDER_TYPE_SOLID, SYSMAP_SHAPE_BORDER_TYPE_DOTTED, SYSMAP_SHAPE_BORDER_TYPE_DASHED])],
			'line_width' =>			['type' => API_INT32, 'in' => '0:50'],
			'line_color' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'border_color')],
			'zindex' =>				['type' => API_INT32]
		]];
		$default_shape_width = DB::getDefault('sysmap_shape', 'width');
		$default_shape_height = DB::getDefault('sysmap_shape', 'height');

		foreach ($sysmapids as $key => $sysmapid) {
			// Map user shares.
			if (array_key_exists('users', $maps[$key])) {
				foreach ($maps[$key]['users'] as $user) {
					$shared_users[] = [
						'sysmapid' => $sysmapid,
						'userid' => $user['userid'],
						'permission' => $user['permission']
					];
				}
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $maps[$key])) {
				foreach ($maps[$key]['userGroups'] as $user_group) {
					$shared_user_groups[] = [
						'sysmapid' => $sysmapid,
						'usrgrpid' => $user_group['usrgrpid'],
						'permission' => $user_group['permission']
					];
				}
			}

			if (array_key_exists('urls', $maps[$key])) {
				foreach ($maps[$key]['urls'] as $url) {
					$url['sysmapid'] = $sysmapid;
					$urls[] = $url;
				}
			}

			if (array_key_exists('shapes', $maps[$key])) {
				$path = '/'.($key + 1).'/shape';
				$api_shape_rules['fields']['x']['in'] = '0:'.$maps[$key]['width'];
				$api_shape_rules['fields']['y']['in'] = '0:'.$maps[$key]['height'];
				$api_shape_rules['fields']['width']['in'] = '1:'.$maps[$key]['width'];
				$api_shape_rules['fields']['height']['in'] = '1:'.$maps[$key]['height'];

				foreach ($maps[$key]['shapes'] as &$shape) {
					$shape['width'] = array_key_exists('width', $shape) ? $shape['width'] : $default_shape_width;
					$shape['height'] = array_key_exists('height', $shape) ? $shape['height'] : $default_shape_height;
				}
				unset($shape);

				if (!CApiInputValidator::validate($api_shape_rules, $maps[$key]['shapes'], $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				foreach ($maps[$key]['shapes'] as $snum => $shape) {
					$maps[$key]['shapes'][$snum]['sysmapid'] = $sysmapid;
				}

				$shapes = array_merge($shapes, $maps[$key]['shapes']);
			}

			if (array_key_exists('lines', $maps[$key])) {
				$path = '/'.($key + 1).'/line';
				$api_line_rules['fields']['x1']['in'] = '0:'.$maps[$key]['width'];
				$api_line_rules['fields']['y1']['in'] = '0:'.$maps[$key]['height'];
				$api_line_rules['fields']['x2']['in'] = '0:'.$maps[$key]['width'];
				$api_line_rules['fields']['y2']['in'] = '0:'.$maps[$key]['height'];

				foreach ($maps[$key]['lines'] as &$line) {
					$line['x2'] = array_key_exists('x2', $line) ? $line['x2'] : $default_shape_width;
					$line['y2'] = array_key_exists('y2', $line) ? $line['y2'] : $default_shape_height;
				}
				unset($line);

				if (!CApiInputValidator::validate($api_line_rules, $maps[$key]['lines'], $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				foreach ($maps[$key]['lines'] as $line) {
					$shape = CMapHelper::convertLineToShape($line);
					$shape['sysmapid'] = $sysmapid;
					$shapes[] = $shape;
				}
			}
		}

		DB::insert('sysmap_user', $shared_users);
		DB::insert('sysmap_usrgrp', $shared_user_groups);
		DB::insert('sysmap_url', $urls);

		if ($shapes) {
			$this->createShapes($shapes);
		}

		$this->updateSelements($maps);
		self::updateLinks($maps);

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_MAP, $maps);

		return ['sysmapids' => $sysmapids];
	}

	private function updateSelements(array &$maps, ?array $db_maps = null): void {
		$ins_selements = [];
		$upd_selements = [];
		$del_selementids = [];

		foreach ($maps as $map) {
			if (!array_key_exists('selements', $map)) {
				continue;
			}

			$db_selements = $db_maps !== null ? $db_maps[$map['sysmapid']]['selements'] : [];

			foreach ($map['selements'] as $selement) {
				if (array_key_exists('selementid', $selement)
						&& array_key_exists($selement['selementid'], $db_selements)) {
					unset($db_selements[$selement['selementid']]);

					$upd_selements[] = ['sysmapid' => $map['sysmapid']] + $selement;
				}
				else {
					$ins_selements[] = ['sysmapid' => $map['sysmapid']] + $selement;
				}
			}

			$del_selementids = array_merge($del_selementids, array_keys($db_selements));
		}

		if ($del_selementids) {
			DB::delete('sysmaps_elements', ['selementid' => $del_selementids]);
		}

		if ($upd_selements) {
			$this->updateSelementsOld($upd_selements);
		}

		$selementids = [];

		if ($ins_selements) {
			$selementids = $this->createSelementsOld($ins_selements);
		}

		$arbitrary_to_selementids = [];

		foreach ($selementids as $index => $selementid) {
			if (array_key_exists('selementid', $ins_selements[$index])) {
				$arbitrary_to_selementids[$ins_selements[$index]['selementid']] = $selementid;
			}
			else {
				$arbitrary_to_selementids[$selementid] = $selementid;
			}
		}

		foreach ($upd_selements as $selement) {
			$arbitrary_to_selementids[$selement['selementid']] = $selement['selementid'];
		}

		foreach ($maps as &$map) {
			if (!array_key_exists('selements', $map)) {
				continue;
			}

			foreach ($map['selements'] as &$selement) {
				if (!array_key_exists('selementid', $selement)) {
					$selement['selementid'] = array_shift($selementids);
				}
			}
			unset($selement);

			if (!array_key_exists('links', $map)) {
				continue;
			}

			foreach ($map['links'] as &$link) {
				foreach (['selementid1', 'selementid2'] as $field) {
					if (array_key_exists($link[$field], $arbitrary_to_selementids)) {
						$link[$field] = $arbitrary_to_selementids[$link[$field]];
					}
				}
			}
			unset($link);
		}
		unset($map);
	}

	private static function updateLinks(array &$maps, ?array $db_maps = null): void {
		$ins_links = [];
		$upd_links = [];
		$del_linkids = [];

		foreach ($maps as $map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			$db_links = $db_maps !== null ? $db_maps[$map['sysmapid']]['links'] : [];

			foreach ($map['links'] as $link) {
				if (array_key_exists('linkid', $link) && array_key_exists($link['linkid'], $db_links)) {
					$db_link = $db_links[$link['linkid']];
					unset($db_links[$link['linkid']]);

					$upd_link = DB::getUpdatedValues('sysmaps_links', $link, $db_link);

					if ($upd_link) {
						$upd_links[] = [
							'values' => $upd_link,
							'where' => ['linkid' => $db_link['linkid']]
						];
					}
				}
				else {
					$ins_links[] = ['sysmapid' => $map['sysmapid']] + $link;
				}
			}

			$del_linkids = array_merge($del_linkids, array_keys($db_links));
		}

		if ($del_linkids) {
			DB::delete('sysmaps_links', ['linkid' => $del_linkids]);
		}

		if ($upd_links) {
			DB::update('sysmaps_links', $upd_links);
		}

		$linkids = [];

		if ($ins_links) {
			$linkids = DB::insert('sysmaps_links', $ins_links);
		}

		$links = [];
		$db_links = null;

		if ($db_maps !== null) {
			$db_links = [];
		}

		foreach ($maps as &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			foreach ($map['links'] as &$link) {
				if (!array_key_exists('linkid', $link)) {
					$link['linkid'] = array_shift($linkids);

					if ($db_maps !== null) {
						$db_links[$link['linkid']] = [
							'linkid' => $link['linkid']
						];

						if (array_key_exists('linktriggers', $link)) {
							$db_links[$link['linkid']]['linktriggers'] = [];
						}

						if (array_key_exists('thresholds', $link)) {
							$db_links[$link['linkid']]['thresholds'] = [];
						}

						if (array_key_exists('highlights', $link)) {
							$db_links[$link['linkid']]['highlights'] = [];
						}
					}
				}
				else {
					$db_links[$link['linkid']] = $db_maps[$map['sysmapid']]['links'][$link['linkid']];
				}

				$links[] = &$link;
			}
			unset($link);
		}
		unset($map);

		if ($links) {
			self::updateLinkTriggers($links, $db_links);
			self::updateLinkThresholds($links, $db_links);
			self::updateLinkHighlights($links, $db_links);
		}
	}

	private static function updateLinkTriggers(array &$links, ?array $db_links): void {
		$ins_link_triggers = [];
		$upd_link_triggers = [];
		$del_linktriggerids = [];

		foreach ($links as &$link) {
			if (!array_key_exists('linktriggers', $link)) {
				continue;
			}

			$db_link_triggers = $db_links !== null
				? array_column($db_links[$link['linkid']]['linktriggers'], null, 'triggerid')
				: [];

			foreach ($link['linktriggers'] as &$link_trigger) {
				if (array_key_exists($link_trigger['triggerid'], $db_link_triggers)) {
					$db_link_trigger = $db_link_triggers[$link_trigger['triggerid']];
					$link_trigger['linktriggerid'] = $db_link_trigger['linktriggerid'];
					unset($db_link_triggers[$link_trigger['triggerid']]);

					$upd_linktrigger = DB::getUpdatedValues('sysmaps_link_triggers', $link_trigger, $db_link_trigger);

					if ($upd_linktrigger) {
						$upd_link_triggers[] = [
							'values' => $upd_linktrigger,
							'where' => ['linktriggerid' => $db_link_trigger['linktriggerid']]
						];
					}
				}
				else {
					$ins_link_triggers[] = ['linkid' => $link['linkid']] + $link_trigger;
				}
			}
			unset($link_trigger);

			$del_linktriggerids = array_merge($del_linktriggerids, array_column($db_link_triggers, 'linktriggerid'));
		}
		unset($link);

		if ($del_linktriggerids) {
			DB::delete('sysmaps_link_triggers', ['linktriggerid' => $del_linktriggerids]);
		}

		if ($upd_link_triggers) {
			DB::update('sysmaps_link_triggers', $upd_link_triggers);
		}

		$linktriggerids = [];

		if ($ins_link_triggers) {
			$linktriggerids = DB::insert('sysmaps_link_triggers', $ins_link_triggers);
		}

		foreach ($links as &$link) {
			if (!array_key_exists('linktriggers', $link)) {
				continue;
			}

			foreach ($link['linktriggers'] as &$link_trigger) {
				if (!array_key_exists('linktriggerid', $link_trigger)) {
					$link_trigger['linktriggerid'] = array_shift($linktriggerids);
				}
			}
			unset($link_trigger);
		}
		unset($link);
	}

	private static function updateLinkThresholds(array &$links, ?array $db_links): void {
		$ins_link_thresholds = [];
		$upd_link_thresholds = [];
		$del_linkthresholdids = [];

		foreach ($links as &$link) {
			if (!array_key_exists('thresholds', $link)) {
				continue;
			}

			$db_link_thresholds = $db_links !== null
				? array_column($db_links[$link['linkid']]['thresholds'], null, 'threshold')
				: [];

			foreach ($link['thresholds'] as &$link_threshold) {
				if (array_key_exists($link_threshold['threshold'], $db_link_thresholds)) {
					$db_link_threshold = $db_link_thresholds[$link_threshold['threshold']];
					$link_threshold['linkthresholdid'] = $db_link_threshold['linkthresholdid'];
					unset($db_link_thresholds[$link_threshold['threshold']]);

					$upd_link_threshold = DB::getUpdatedValues('sysmap_link_threshold', $link_threshold,
						$db_link_threshold
					);

					if ($upd_link_threshold) {
						$upd_link_thresholds[] = [
							'values' => $upd_link_threshold,
							'where' => ['linkthresholdid' => $db_link_threshold['linkthresholdid']]
						];
					}
				}
				else {
					$ins_link_thresholds[] = [
						'linkid' => $link['linkid'],
						'type' => self::LINK_THRESHOLD_TYPE_THRESHOLD
					] + $link_threshold;
				}
			}
			unset($link_threshold);

			$del_linkthresholdids = array_merge($del_linkthresholdids,
				array_column($db_link_thresholds, 'linkthresholdid')
			);
		}
		unset($link);

		if ($del_linkthresholdids) {
			DB::delete('sysmap_link_threshold', ['linkthresholdid' => $del_linkthresholdids]);
		}

		if ($upd_link_thresholds) {
			DB::update('sysmap_link_threshold', $upd_link_thresholds);
		}

		$linkthresholdids = [];

		if ($ins_link_thresholds) {
			$linkthresholdids = DB::insert('sysmap_link_threshold', $ins_link_thresholds);
		}

		foreach ($links as &$link) {
			if (!array_key_exists('thresholds', $link)) {
				continue;
			}

			foreach ($link['thresholds'] as &$link_threshold) {
				if (!array_key_exists('linkthresholdid', $link_threshold)) {
					$link_threshold['linkthresholdid'] = array_shift($linkthresholdids);
				}
			}
			unset($link_threshold);
		}
		unset($link);
	}

	private static function updateLinkHighlights(array &$links, ?array $db_links): void {
		$ins_link_thresholds = [];
		$upd_link_thresholds = [];
		$del_linkthresholdids = [];

		foreach ($links as &$link) {
			if (!array_key_exists('highlights', $link)) {
				continue;
			}

			$db_link_thresholds = $db_links !== null
				? array_column($db_links[$link['linkid']]['highlights'], null, 'pattern')
				: [];

			foreach ($link['highlights'] as &$link_threshold) {
				if (array_key_exists($link_threshold['pattern'], $db_link_thresholds)) {
					$db_link_threshold = $db_link_thresholds[$link_threshold['pattern']];
					$link_threshold['linkthresholdid'] = $db_link_threshold['linkthresholdid'];
					unset($db_link_thresholds[$link_threshold['pattern']]);

					$upd_link_threshold = DB::getUpdatedValues('sysmap_link_threshold', $link_threshold,
						$db_link_threshold
					);

					if ($upd_link_threshold) {
						$upd_link_thresholds[] = [
							'values' => $upd_link_threshold,
							'where' => ['linkthresholdid' => $db_link_threshold['linkthresholdid']]
						];
					}
				}
				else {
					$ins_link_thresholds[] = [
						'linkid' => $link['linkid'],
						'type' => self::LINK_THRESHOLD_TYPE_HIGHLIGHT
					] + $link_threshold;
				}
			}
			unset($link_threshold);

			$del_linkthresholdids = array_merge($del_linkthresholdids,
				array_column($db_link_thresholds, 'linkthresholdid')
			);
		}
		unset($link);

		if ($del_linkthresholdids) {
			DB::delete('sysmap_link_threshold', ['linkthresholdid' => $del_linkthresholdids]);
		}

		if ($upd_link_thresholds) {
			DB::update('sysmap_link_threshold', $upd_link_thresholds);
		}

		$linkthresholdids = [];

		if ($ins_link_thresholds) {
			$linkthresholdids = DB::insert('sysmap_link_threshold', $ins_link_thresholds);
		}

		foreach ($links as &$link) {
			if (!array_key_exists('highlights', $link)) {
				continue;
			}

			foreach ($link['highlights'] as &$link_threshold) {
				if (!array_key_exists('linkthresholdid', $link_threshold)) {
					$link_threshold['linkthresholdid'] = array_shift($linkthresholdids);
				}
			}
			unset($link_threshold);
		}
		unset($link);
	}

	/**
	 * Update map.
	 *
	 * @param array  $maps						multidimensional array with Hosts data
	 * @param string $maps['sysmapid']
	 * @param string $maps['name']
	 * @param array  $maps['width']
	 * @param int    $maps['height']
	 * @param string $maps['backgroundid']
	 * @param array  $maps['label_type']
	 * @param int    $maps['label_location']
	 * @param int    $maps['grid_size']			size of one grid cell. 100 refers to 100x100 and so on.
	 * @param int    $maps['grid_show']			does grid need to be shown. Constants: SYSMAP_GRID_SHOW_ON / SYSMAP_GRID_SHOW_OFF
	 * @param int    $maps['grid_align']		do elements need to be aligned to the grid. Constants: SYSMAP_GRID_ALIGN_ON / SYSMAP_GRID_ALIGN_OFF
	 *
	 * @return array
	 */
	public function update(array $maps): array {
		$this->validateUpdate($maps, $db_maps);

		$update_maps = [];
		$url_ids_to_delete = [];
		$urls_to_update = [];
		$urls_to_add = [];
		$shapes_to_delete = [];
		$shapes_to_update = [];
		$shapes_to_add = [];
		$shared_userids_to_delete = [];
		$shared_users_to_update = [];
		$shared_users_to_add = [];
		$shared_user_groupids_to_delete = [];
		$shared_user_groups_to_update = [];
		$shared_user_groups_to_add = [];
		$api_shape_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
			'sysmap_shapeid' =>		['type' => API_ID],
			'type' =>				['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_TYPE_RECTANGLE, SYSMAP_SHAPE_TYPE_ELLIPSE])],
			'x' =>					['type' => API_INT32],
			'y' =>					['type' => API_INT32],
			'width' =>				['type' => API_INT32],
			'height' =>				['type' => API_INT32],
			'font' =>				['type' => API_INT32, 'in' => '0:12'],
			'font_size' =>			['type' => API_INT32, 'in' => '1:250'],
			'text_halign' =>		['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_LABEL_HALIGN_CENTER, SYSMAP_SHAPE_LABEL_HALIGN_LEFT, SYSMAP_SHAPE_LABEL_HALIGN_RIGHT])],
			'text_valign' =>		['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_LABEL_VALIGN_MIDDLE, SYSMAP_SHAPE_LABEL_VALIGN_TOP, SYSMAP_SHAPE_LABEL_VALIGN_BOTTOM])],
			'border_type' =>		['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_BORDER_TYPE_NONE, SYSMAP_SHAPE_BORDER_TYPE_SOLID, SYSMAP_SHAPE_BORDER_TYPE_DOTTED, SYSMAP_SHAPE_BORDER_TYPE_DASHED])],
			'border_width' =>		['type' => API_INT32, 'in' => '0:50'],
			'border_color' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'border_color')],
			'background_color' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'background_color')],
			'font_color' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'font_color')],
			'text' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'text')],
			'zindex' =>				['type' => API_INT32]
		]];
		$api_line_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
			'sysmap_shapeid' =>		['type' => API_ID],
			'x1' =>					['type' => API_INT32],
			'y1' =>					['type' => API_INT32],
			'x2' =>					['type' => API_INT32],
			'y2' =>					['type' => API_INT32],
			'line_type' =>			['type' => API_INT32, 'in' => implode(',', [SYSMAP_SHAPE_BORDER_TYPE_NONE, SYSMAP_SHAPE_BORDER_TYPE_SOLID, SYSMAP_SHAPE_BORDER_TYPE_DOTTED, SYSMAP_SHAPE_BORDER_TYPE_DASHED])],
			'line_width' =>			['type' => API_INT32, 'in' => '0:50'],
			'line_color' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sysmap_shape', 'border_color')],
			'zindex' =>				['type' => API_INT32]
		]];
		$default_shape_width = DB::getDefault('sysmap_shape', 'width');
		$default_shape_height = DB::getDefault('sysmap_shape', 'height');

		foreach ($maps as $index => $map) {
			$update_maps[] = [
				'values' => $map,
				'where' => ['sysmapid' => $map['sysmapid']]
			];

			$db_map = $db_maps[$map['sysmapid']];

			// Map user shares.
			if (array_key_exists('users', $map)) {
				$user_shares_diff = zbx_array_diff($map['users'], $db_map['users'], 'userid');

				foreach ($user_shares_diff['both'] as $update_user_share) {
					$shared_users_to_update[] = [
						'values' => $update_user_share,
						'where' => ['userid' => $update_user_share['userid'], 'sysmapid' => $map['sysmapid']]
					];
				}

				foreach ($user_shares_diff['first'] as $new_shared_user) {
					$new_shared_user['sysmapid'] = $map['sysmapid'];
					$shared_users_to_add[] = $new_shared_user;
				}

				$shared_userids_to_delete = array_merge($shared_userids_to_delete,
					zbx_objectValues($user_shares_diff['second'], 'sysmapuserid')
				);
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $map)) {
				$user_group_shares_diff = zbx_array_diff($map['userGroups'], $db_map['userGroups'],
					'usrgrpid'
				);

				foreach ($user_group_shares_diff['both'] as $update_user_share) {
					$shared_user_groups_to_update[] = [
						'values' => $update_user_share,
						'where' => ['usrgrpid' => $update_user_share['usrgrpid'], 'sysmapid' => $map['sysmapid']]
					];
				}

				foreach ($user_group_shares_diff['first'] as $new_shared_user_group) {
					$new_shared_user_group['sysmapid'] = $map['sysmapid'];
					$shared_user_groups_to_add[] = $new_shared_user_group;
				}

				$shared_user_groupids_to_delete = array_merge($shared_user_groupids_to_delete,
					zbx_objectValues($user_group_shares_diff['second'], 'sysmapusrgrpid')
				);
			}

			// Urls.
			if (array_key_exists('urls', $map)) {
				foreach ($map['urls'] as &$url) {
					unset($url['sysmapurlid'], $url['sysmapid']);
				}
				unset($url);

				$url_diff = zbx_array_diff($map['urls'], $db_map['urls'], 'name');

				foreach ($url_diff['both'] as $updateUrl) {
					$urls_to_update[] = [
						'values' => $updateUrl,
						'where' => ['name' => $updateUrl['name'], 'sysmapid' => $map['sysmapid']]
					];
				}

				foreach ($url_diff['first'] as $new_url) {
					$new_url['sysmapid'] = $map['sysmapid'];
					$urls_to_add[] = $new_url;
				}

				$url_ids_to_delete = array_merge($url_ids_to_delete,
					zbx_objectValues($url_diff['second'], 'sysmapurlid')
				);
			}

			$map_width = array_key_exists('width', $map) ? $map['width'] : $db_map['width'];
			$map_height = array_key_exists('height', $map) ? $map['height'] : $db_map['height'];

			// Map shapes.
			if (array_key_exists('shapes', $map)) {
				$map['shapes'] = array_values($map['shapes']);

				foreach ($map['shapes'] as &$shape) {
					$shape['width'] = array_key_exists('width', $shape) ? $shape['width'] : $default_shape_width;
					$shape['height'] = array_key_exists('height', $shape) ? $shape['height'] : $default_shape_height;
				}
				unset($shape);

				$shape_diff = zbx_array_diff($map['shapes'], $db_map['shapes'], 'sysmap_shapeid');

				$path = '/'.($index + 1).'/shape';
				foreach ($shape_diff['first'] as $new_shape) {
					if (array_key_exists('sysmap_shapeid', $new_shape)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}

				unset($api_shape_rules['fields']['sysmap_shapeid']);
				$api_shape_rules['fields']['type']['flags'] = API_REQUIRED;
				$api_shape_rules['fields']['x']['in'] = '0:'.$map_width;
				$api_shape_rules['fields']['y']['in'] = '0:'.$map_height;
				$api_shape_rules['fields']['width']['in'] = '1:'.$map_width;
				$api_shape_rules['fields']['height']['in'] = '1:'.$map_height;

				if (!CApiInputValidator::validate($api_shape_rules, $shape_diff['first'], $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$api_shape_rules['fields']['sysmap_shapeid'] = ['type' => API_ID, 'flags' => API_REQUIRED];
				$api_shape_rules['fields']['type']['flags'] = 0;
				if (!CApiInputValidator::validate($api_shape_rules, $shape_diff['both'], $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$shapes_to_update = array_merge($shapes_to_update, $shape_diff['both']);
				$shapes_to_delete = array_merge($shapes_to_delete, $shape_diff['second']);

				// We need sysmapid for add operations.
				foreach ($shape_diff['first'] as $new_shape) {
					$new_shape['sysmapid'] = $map['sysmapid'];
					$shapes_to_add[] = $new_shape;
				}
			}

			if (array_key_exists('lines', $map)) {
				$map['lines'] = array_values($map['lines']);
				$shapes = [];

				$api_line_rules['fields']['x1']['in'] = '0:'.$map_width;
				$api_line_rules['fields']['y1']['in'] = '0:'.$map_height;
				$api_line_rules['fields']['x2']['in'] = '0:'.$map_width;
				$api_line_rules['fields']['y2']['in'] = '0:'.$map_height;

				$path = '/'.($index + 1).'/line';

				foreach ($map['lines'] as &$line) {
					$line['x2'] = array_key_exists('x2', $line) ? $line['x2'] : $default_shape_width;
					$line['y2'] = array_key_exists('y2', $line) ? $line['y2'] : $default_shape_height;
				}
				unset($line);

				if (!CApiInputValidator::validate($api_line_rules, $map['lines'], $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				foreach ($map['lines'] as $line) {
					$shapes[] = CMapHelper::convertLineToShape($line);
				}

				$line_diff = zbx_array_diff($shapes, $db_map['lines'], 'sysmap_shapeid');

				foreach ($line_diff['first'] as $new_line) {
					if (array_key_exists('sysmap_shapeid', $new_line)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}

				$shapes_to_update = array_merge($shapes_to_update, $line_diff['both']);
				$shapes_to_delete = array_merge($shapes_to_delete, $line_diff['second']);

				// We need sysmapid for add operations.
				foreach ($line_diff['first'] as $new_shape) {
					$new_shape['sysmapid'] = $map['sysmapid'];
					$shapes_to_add[] = $new_shape;
				}
			}
		}

		DB::update('sysmaps', $update_maps);

		// User shares.
		DB::insert('sysmap_user', $shared_users_to_add);
		DB::update('sysmap_user', $shared_users_to_update);

		if ($shared_userids_to_delete) {
			DB::delete('sysmap_user', ['sysmapuserid' => $shared_userids_to_delete]);
		}

		// User group shares.
		DB::insert('sysmap_usrgrp', $shared_user_groups_to_add);
		DB::update('sysmap_usrgrp', $shared_user_groups_to_update);

		if ($shared_user_groupids_to_delete) {
			DB::delete('sysmap_usrgrp', ['sysmapusrgrpid' => $shared_user_groupids_to_delete]);
		}

		// Urls.
		DB::insert('sysmap_url', $urls_to_add);
		DB::update('sysmap_url', $urls_to_update);

		if ($url_ids_to_delete) {
			DB::delete('sysmap_url', ['sysmapurlid' => $url_ids_to_delete]);
		}

		// Shapes.
		if ($shapes_to_add) {
			$this->createShapes($shapes_to_add);
		}

		if ($shapes_to_update) {
			$this->updateShapes($shapes_to_update);
		}

		if ($shapes_to_delete) {
			$this->deleteShapes($shapes_to_delete);
		}

		$this->updateSelements($maps, $db_maps);

		$this->updateForce($maps, $db_maps);

		return ['sysmapids' => array_column($maps, 'sysmapid')];
	}

	public function updateForce(array $maps, array $db_maps): void {
		self::addFieldDefaultsByLinkIndicatorType($maps, $db_maps);

		self::updateLinks($maps, $db_maps);

		$this->addAuditBulk(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MAP, $maps, $db_maps);
	}

	private static function addFieldDefaultsByLinkIndicatorType(array &$maps, array $db_maps): void {
		foreach ($maps as &$map) {
			if (!array_key_exists('links', $map)) {
				continue;
			}

			foreach ($map['links'] as &$link) {
				if (!array_key_exists('linkid', $link)) {
					continue;
				}

				$db_link = $db_maps[$map['sysmapid']]['links'][$link['linkid']];

				if ($link['indicator_type'] != $db_link['indicator_type']) {
					if ($db_link['indicator_type'] == MAP_INDICATOR_TYPE_TRIGGER) {
						$link += ['linktriggers' => []];
					}
					elseif ($db_link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE) {
						$link += ['itemid' => 0, 'thresholds' => [], 'highlights' => []];
					}
				}
				elseif ($link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE) {
					$link += in_array($link['item_value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
						? ['highlights' => []]
						: ['thresholds' => []];
				}
			}
			unset($link);
		}
		unset($map);
	}

	public function unlinkTriggers(array $triggerids): void {
		self::unlinkSelementTriggers($triggerids);
		$this->unlinkLinkTriggers($triggerids);
	}

	private static function unlinkSelementTriggers(array $triggerids): void {
		$selement_triggerids = [];
		$selementids = [];

		$db_selement_triggers = DBselect(
			'SELECT st.selement_triggerid,st.selementid'.
			' FROM sysmap_element_trigger st'.
			' WHERE '.dbConditionId('st.triggerid', $triggerids)
		);

		while ($db_selement_trigger = DBfetch($db_selement_triggers)) {
			$selement_triggerids[] = $db_selement_trigger['selement_triggerid'];
			$selementids[$db_selement_trigger['selementid']] = true;
		}

		if ($selement_triggerids) {
			DB::delete('sysmap_element_trigger', ['selement_triggerid' => $selement_triggerids]);

			// Remove map elements without triggers.
			$db_selement_triggers = DBselect(
				'SELECT DISTINCT st.selementid'.
				' FROM sysmap_element_trigger st'.
				' WHERE '.dbConditionId('st.selementid', array_keys($selementids))
			);
			while ($db_selement_trigger = DBfetch($db_selement_triggers)) {
				unset($selementids[$db_selement_trigger['selementid']]);
			}

			if ($selementids) {
				DB::delete('sysmaps_elements', ['selementid' => array_keys($selementids)]);
			}
		}
	}

	private function unlinkLinkTriggers(array $triggerids): void {
		$resource = DBselect(
			'SELECT slt.linktriggerid,slt.linkid,slt.triggerid,sl.indicator_type,sl.sysmapid,s.name'.
			' FROM sysmaps_link_triggers slt'.
			' LEFT JOIN sysmaps_links sl ON slt.linkid=sl.linkid'.
			' LEFT JOIN sysmaps s ON sl.sysmapid=s.sysmapid'.
			' WHERE '.dbConditionId('slt.triggerid', $triggerids)
		);

		$db_maps = [];
		$db_link_triggers = [];

		while ($row = DBfetch($resource)) {
			if (!array_key_exists($row['sysmapid'], $db_maps)) {
				$db_maps[$row['sysmapid']] = [
					'sysmapid' => $row['sysmapid'],
					'name' => $row['name'],
					'links' => []
				];
			}

			if (!array_key_exists($row['linkid'], $db_maps[$row['sysmapid']]['links'])) {
				$db_maps[$row['sysmapid']]['links'][$row['linkid']] = [
					'linkid' => $row['linkid'],
					'indicator_type' => $row['indicator_type'],
					'linktriggers' => []
				];

				$db_link_triggers[$row['linkid']] =
					&$db_maps[$row['sysmapid']]['links'][$row['linkid']]['linktriggers'];
			}

			$db_maps[$row['sysmapid']]['links'][$row['linkid']]['linktriggers'][$row['linktriggerid']] = [
				'linktriggerid' => $row['linktriggerid'],
				'triggerid' => $row['triggerid']
			];
		}

		$maps = [];
		$link_triggers = [];
		$i1 = 0;

		foreach ($db_maps as $db_map) {
			$maps[$i1] = ['sysmapid' => $db_map['sysmapid']];

			$i2 = 0;

			foreach ($db_map['links'] as $db_link) {
				$maps[$i1]['links'][$i2] = [
					'linkid' => $db_link['linkid'],
					'linktriggers' => []
				];

				$link_triggers[$db_link['linkid']] = &$maps[$i1]['links'][$i2]['linktriggers'];

				$i2++;
			}

			$i1++;
		}

		$resource = DBselect(
			'SELECT slt.linktriggerid,slt.linkid,slt.triggerid'.
			' FROM sysmaps_link_triggers slt'.
			' WHERE '.dbConditionId('slt.linkid', array_keys($db_link_triggers)).
				' AND '.dbConditionId('slt.triggerid', $triggerids, true)
		);

		while ($row = DBfetch($resource)) {
			$link_triggers[$row['linkid']][] = ['triggerid' => $row['triggerid']];
			$db_link_triggers[$row['linkid']][$row['linktriggerid']] = [
				'linktriggerid' => $row['linktriggerid'],
				'triggerid' => $row['triggerid']
			];
		}

		foreach ($maps as &$map) {
			foreach ($map['links'] as &$link) {
				$link['indicator_type'] = $link['linktriggers']
					? MAP_INDICATOR_TYPE_TRIGGER
					: MAP_INDICATOR_TYPE_STATIC_LINK;
			}
			unset($link);
		}
		unset($map);

		$this->updateForce($maps, $db_maps);
	}

	public function unlinkItems(array $itemids): void {
		$resource = DBselect(
			'SELECT sl.linkid,sl.sysmapid,sl.indicator_type,sl.itemid,s.name'.
			' FROM sysmaps_links sl'.
			' LEFT JOIN sysmaps s ON sl.sysmapid=s.sysmapid'.
			' WHERE '.dbConditionId('sl.itemid', $itemids)
		);

		$db_maps = [];
		$db_links = [];

		while ($row = DBfetch($resource)) {
			if (!array_key_exists($row['sysmapid'], $db_maps)) {
				$db_maps[$row['sysmapid']] = [
					'sysmapid' => $row['sysmapid'],
					'name' => $row['name'],
					'links' => []
				];
			}

			$db_maps[$row['sysmapid']]['links'][$row['linkid']] = [
				'linkid' => $row['linkid'],
				'indicator_type' => $row['indicator_type'],
				'itemid' => $row['itemid']
			];

			$db_links[$row['linkid']] = &$db_maps[$row['sysmapid']]['links'][$row['linkid']];
		}

		if (!$db_links) {
			return;
		}

		self::addAffectedLinkThresholds($db_links);
		self::addAffectedLinkHighlights($db_links);

		$maps = [];
		$i = 0;

		foreach ($db_maps as $db_map) {
			$maps[$i] = [
				'sysmapid' => $db_map['sysmapid'],
				'links' => []
			];

			foreach ($db_map['links'] as $db_link) {
				$maps[$i]['links'][] = [
					'linkid' => $db_link['linkid'],
					'indicator_type' => MAP_INDICATOR_TYPE_STATIC_LINK
				];
			}

			$i++;
		}

		$this->updateForce($maps, $db_maps);
	}

	/**
	 * Delete Map.
	 *
	 * @param array $sysmapids
	 *
	 * @return array
	 */
	public function delete(array $sysmapids) {
		$this->validateDelete($sysmapids, $db_maps);

		DB::delete('sysmaps_elements', [
			'elementid' => $sysmapids,
			'elementtype' => SYSMAP_ELEMENT_TYPE_MAP
		]);
		DB::delete('profiles', [
			'idx' => 'web.maps.sysmapid',
			'value_id' => $sysmapids
		]);
		DB::delete('profiles', [
			'idx' => 'web.favorite.sysmapids',
			'source' => 'sysmapid',
			'value_id' => $sysmapids
		]);
		DB::delete('sysmaps', ['sysmapid' => $sysmapids]);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_MAP, $db_maps);

		return ['sysmapids' => $sysmapids];
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$sysmapIds = array_keys($result);

		// adding elements
		if ($options['selectSelements'] !== null && $options['selectSelements'] != API_OUTPUT_COUNT) {
			$selements = API::getApiService()->select('sysmaps_elements', [
				'output' => $this->outputExtend($options['selectSelements'], ['selementid', 'sysmapid', 'elementtype',
					'elementid', 'elementsubtype'
				]),
				'filter' => ['sysmapid' => $sysmapIds],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($selements, 'sysmapid', 'selementid');

			if ($this->outputIsRequested('elements', $options['selectSelements']) && $selements) {
				foreach ($selements as &$selement) {
					$selement['elements'] = [];
				}
				unset($selement);

				$selement_triggers = DBselect(
					'SELECT st.selementid,st.triggerid,st.selement_triggerid'.
					' FROM sysmap_element_trigger st,triggers tr'.
					' WHERE '.dbConditionInt('st.selementid', array_keys($selements)).
						' AND st.triggerid=tr.triggerid'.
					' ORDER BY tr.priority DESC,st.selement_triggerid'
				);
				while ($selement_trigger = DBfetch($selement_triggers)) {
					$selements[$selement_trigger['selementid']]['elements'][] = [
						'triggerid' => $selement_trigger['triggerid']
					];
					if ($selements[$selement_trigger['selementid']]['elementid'] == 0) {
						$selements[$selement_trigger['selementid']]['elementid'] = $selement_trigger['triggerid'];
					}
				}

				$single_element_types = [SYSMAP_ELEMENT_TYPE_HOST, SYSMAP_ELEMENT_TYPE_MAP,
					SYSMAP_ELEMENT_TYPE_HOST_GROUP
				];

				foreach ($selements as &$selement) {
					if (in_array($selement['elementtype'], $single_element_types)) {
						switch ($selement['elementtype']) {
							case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
								$field = 'groupid';
								break;

							case SYSMAP_ELEMENT_TYPE_HOST:
								$field = 'hostid';
								break;

							case SYSMAP_ELEMENT_TYPE_MAP:
								$field = 'sysmapid';
								break;
						}
						$selement['elements'][] = [$field => $selement['elementid']];
					}
				}
				unset($selement);
			}

			// add selement URLs
			if ($this->outputIsRequested('urls', $options['selectSelements'])) {
				foreach ($selements as &$selement) {
					$selement['urls'] = [];
				}
				unset($selement);

				if (!is_null($options['expandUrls'])) {
					$dbMapUrls = DBselect(
						'SELECT su.sysmapurlid,su.sysmapid,su.name,su.url,su.elementtype'.
						' FROM sysmap_url su'.
						' WHERE '.dbConditionInt('su.sysmapid', $sysmapIds)
					);
					while ($mapUrl = DBfetch($dbMapUrls)) {
						foreach ($selements as $snum => $selement) {
							if (bccomp($selement['sysmapid'], $mapUrl['sysmapid']) == 0
									&& (($selement['elementtype'] == $mapUrl['elementtype']
											&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP
											)
											|| ($selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
													&& $mapUrl['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST)
										)) {
								$selements[$snum]['urls'][] = $mapUrl;
							}
						}
					}
				}

				$dbSelementUrls = DBselect(
					'SELECT seu.sysmapelementurlid,seu.selementid,seu.name,seu.url'.
					' FROM sysmap_element_url seu'.
					' WHERE '.dbConditionInt('seu.selementid', array_keys($selements))
				);
				while ($selementUrl = DBfetch($dbSelementUrls)) {
					$selements[$selementUrl['selementid']]['urls'][] = $selementUrl;
				}

				if (!is_null($options['expandUrls'])) {
					$resolve_opt = ['resolve_element_urls' => true];
					$selements = CMacrosResolverHelper::resolveMacrosInMapElements($selements, $resolve_opt);
				}
			}

			if ($this->outputIsRequested('tags', $options['selectSelements']) && $selements) {
				$db_tags = DBselect(
					'SELECT selementid,tag,value,operator'.
					' FROM sysmaps_element_tag'.
					' WHERE '.dbConditionInt('selementid', array_keys($selements))
				);

				array_walk($selements, function (&$selement) {
					$selement['tags'] = [];
				});

				while ($db_tag = DBfetch($db_tags)) {
					$selements[$db_tag['selementid']]['tags'][] = [
						'tag' => $db_tag['tag'],
						'value' => $db_tag['value'],
						'operator' => $db_tag['operator']
					];
				}
			}

			if ($this->outputIsRequested('permission', $options['selectSelements']) && $selements) {
				if ($options['editable']) {
					foreach ($selements as &$selement) {
						$selement['permission'] = PERM_READ_WRITE;
					}
					unset($selement);
				}
				elseif (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
					foreach ($selements as &$selement) {
						$selement['permission'] = PERM_READ;
					}
					unset($selement);
				}
				else {
					$ids = [
						SYSMAP_ELEMENT_TYPE_HOST_GROUP => [],
						SYSMAP_ELEMENT_TYPE_HOST => [],
						SYSMAP_ELEMENT_TYPE_TRIGGER => [],
						SYSMAP_ELEMENT_TYPE_MAP => []
					];
					$trigger_selementids = [];

					foreach ($selements as &$selement) {
						switch ($selement['elementtype']) {
							case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							case SYSMAP_ELEMENT_TYPE_HOST:
							case SYSMAP_ELEMENT_TYPE_MAP:
								$ids[$selement['elementtype']][$selement['elementid']][] = $selement['selementid'];
								$selement['permission'] = PERM_NONE;
								break;

							case SYSMAP_ELEMENT_TYPE_TRIGGER:
								$trigger_selementids[$selement['selementid']] = true;
								$selement['permission'] = PERM_NONE;
								break;

							case SYSMAP_ELEMENT_TYPE_IMAGE:
								$selement['permission'] = PERM_READ;
								break;
						}
					}
					unset($selement);

					$db[SYSMAP_ELEMENT_TYPE_HOST_GROUP] = $ids[SYSMAP_ELEMENT_TYPE_HOST_GROUP]
						? API::HostGroup()->get([
							'output' => [],
							'groupids' => array_keys($ids[SYSMAP_ELEMENT_TYPE_HOST_GROUP]),
							'preservekeys' => true
						])
						: [];

					$db[SYSMAP_ELEMENT_TYPE_HOST] = $ids[SYSMAP_ELEMENT_TYPE_HOST]
						? API::Host()->get([
							'output' => [],
							'hostids' => array_keys($ids[SYSMAP_ELEMENT_TYPE_HOST]),
							'preservekeys' => true
						])
					: [];

					$db[SYSMAP_ELEMENT_TYPE_MAP] = $ids[SYSMAP_ELEMENT_TYPE_MAP]
						? API::Map()->get([
							'output' => [],
							'sysmapids' => array_keys($ids[SYSMAP_ELEMENT_TYPE_MAP]),
							'preservekeys' => true
						])
						: [];

					if ($trigger_selementids) {
						$db_selement_triggers = DBselect(
							'SELECT st.selementid,st.triggerid'.
							' FROM sysmap_element_trigger st'.
							' WHERE '.dbConditionInt('st.selementid', array_keys($trigger_selementids))
						);

						while ($db_selement_trigger = DBfetch($db_selement_triggers)) {
							$ids[SYSMAP_ELEMENT_TYPE_TRIGGER][$db_selement_trigger['triggerid']][] =
								$db_selement_trigger['selementid'];
						}
					}

					$db[SYSMAP_ELEMENT_TYPE_TRIGGER] = $ids[SYSMAP_ELEMENT_TYPE_TRIGGER]
						? API::Trigger()->get([
							'output' => [],
							'triggerids' => array_keys($ids[SYSMAP_ELEMENT_TYPE_TRIGGER]),
							'preservekeys' => true
						])
						: [];

					foreach ($ids as $elementtype => $elementids) {
						foreach ($elementids as $elementid => $selementids) {
							if (array_key_exists($elementid, $db[$elementtype])) {
								foreach ($selementids as $selementid) {
									$selements[$selementid]['permission'] = PERM_READ;
								}
							}
						}
					}
				}
			}

			foreach ($selements as &$selement) {
				unset($selement['elementid']);
			}
			unset($selement);

			$selements = $this->unsetExtraFields($selements,
				['sysmapid', 'selementid', 'elementtype', 'elementsubtype'],
				$options['selectSelements']
			);
			$result = $relation_map->mapMany($result, $selements, 'selements');
		}

		$shape_types = [];
		if ($options['selectShapes'] !== null && $options['selectShapes'] != API_OUTPUT_COUNT) {
			$shape_types = [SYSMAP_SHAPE_TYPE_RECTANGLE, SYSMAP_SHAPE_TYPE_ELLIPSE];
		}

		if ($options['selectLines'] !== null && $options['selectLines'] != API_OUTPUT_COUNT) {
			$shape_types[] = SYSMAP_SHAPE_TYPE_LINE;
		}

		// Adding shapes.
		if ($shape_types) {
			$fields = API_OUTPUT_EXTEND;

			if ($options['selectShapes'] != API_OUTPUT_EXTEND && $options['selectLines'] != API_OUTPUT_EXTEND) {
				$fields = ['sysmap_shapeid', 'sysmapid', 'type'];
				$mapping = [
					'x1' => 'x',
					'y1' => 'y',
					'x2' => 'width',
					'y2' =>	'height',
					'line_type' => 'border_type',
					'line_width' => 'border_width',
					'line_color' => 'border_color'
				];

				if (is_array($options['selectLines'])) {
					foreach ($mapping as $source_field => $target_field) {
						if (in_array($source_field, $options['selectLines'])) {
							$fields[] = $target_field;
						}
					}
				}

				if (is_array($options['selectShapes'])) {
					$fields = array_merge($fields, $options['selectShapes']);
				}
			}

			$db_shapes = API::getApiService()->select('sysmap_shape', [
				'output' => $fields,
				'filter' => ['sysmapid' => $sysmapIds, 'type' => $shape_types],
				'preservekeys' => true
			]);

			$shapes = [];
			$lines = [];
			foreach ($db_shapes as $key => $db_shape) {
				if ($db_shape['type'] == SYSMAP_SHAPE_TYPE_LINE) {
					$lines[$key] = CMapHelper::convertShapeToLine($db_shape);
				}
				else {
					$shapes[$key] = $db_shape;
				}
			}

			$relation_map = $this->createRelationMap($db_shapes, 'sysmapid', 'sysmap_shapeid');

			if ($options['selectShapes'] !== null && $options['selectShapes'] != API_OUTPUT_COUNT) {
				$shapes = $this->unsetExtraFields($shapes, ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height',
					'text', 'font', 'font_size', 'font_color', 'text_halign', 'text_valign', 'border_type',
					'border_width', 'border_color', 'background_color', 'zindex'
				], $options['selectShapes']);
				$shapes = $this->unsetExtraFields($shapes, ['sysmapid'], null);

				$result = $relation_map->mapMany($result, $shapes, 'shapes');
			}

			if ($options['selectLines'] !== null && $options['selectLines'] != API_OUTPUT_COUNT) {
				$lines = $this->unsetExtraFields($lines, ['sysmap_shapeid', 'x1', 'x2', 'y1', 'y2', 'line_type',
					'line_width', 'line_color', 'zindex'
				], $options['selectLines']);
				$lines = $this->unsetExtraFields($lines, ['sysmapid', 'type'], null);

				$result = $relation_map->mapMany($result, $lines, 'lines');
			}
		}

		// adding icon maps
		if ($options['selectIconMap'] !== null && $options['selectIconMap'] != API_OUTPUT_COUNT) {
			$iconMaps = API::getApiService()->select($this->tableName(), [
				'output' => ['sysmapid', 'iconmapid'],
				'filter' => ['sysmapid' => $sysmapIds]
			]);

			$relation_map = $this->createRelationMap($iconMaps, 'sysmapid', 'iconmapid');

			$iconMaps = API::IconMap()->get([
				'output' => $this->outputExtend($options['selectIconMap'], ['iconmapid']),
				'iconmapids' => zbx_objectValues($iconMaps, 'iconmapid'),
				'preservekeys' => true
			]);

			$iconMaps = $this->unsetExtraFields($iconMaps, ['iconmapid'], $options['selectIconMap']);

			$result = $relation_map->mapOne($result, $iconMaps, 'iconmap');
		}

		// adding links
		if ($options['selectLinks'] !== null && $options['selectLinks'] != API_OUTPUT_COUNT) {
			$links = API::getApiService()->select('sysmaps_links', [
				'output' => $this->outputExtend($options['selectLinks'], ['sysmapid', 'linkid', 'indicator_type',
					'itemid'
				]),
				'filter' => ['sysmapid' => $sysmapIds],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($links, 'sysmapid', 'linkid');

			// add link triggers
			if ($this->outputIsRequested('linktriggers', $options['selectLinks'])) {
				$link_triggers = DBFetchArrayAssoc(DBselect(
					'SELECT slt.linktriggerid,slt.linkid,slt.triggerid,slt.drawtype,slt.color'.
					' FROM sysmaps_link_triggers slt'.
					' WHERE '.dbConditionId('slt.linkid', $relation_map->getRelatedIds())
				), 'linktriggerid');
				$link_trigger_relation_map = $this->createRelationMap($link_triggers, 'linkid', 'linktriggerid');
				$link_triggers = $this->unsetExtraFields($link_triggers, ['linktriggerid', 'linkid']);
				$links = $link_trigger_relation_map->mapMany($links, $link_triggers, 'linktriggers');
			}

			if ($this->outputIsRequested('thresholds', $options['selectLinks'])) {
				$link_thresholds = DBFetchArrayAssoc(DBselect(
					'SELECT slt.linkthresholdid,slt.linkid,slt.threshold,slt.drawtype,slt.color'.
					' FROM sysmap_link_threshold slt'.
					' WHERE slt.type='.self::LINK_THRESHOLD_TYPE_THRESHOLD.
						' AND '.dbConditionId('slt.linkid', $relation_map->getRelatedIds())
				), 'linkthresholdid');

				if ($link_thresholds) {
					$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

					foreach ($link_thresholds as &$link_threshold) {
						$number_parser->parse($link_threshold['threshold']);
						$link_threshold['order_threshold'] = $number_parser->calcValue();
					}
					unset($link_threshold);

					CArrayHelper::sort($link_thresholds, ['order_threshold']);

					foreach ($link_thresholds as &$link_threshold) {
						unset($link_threshold['order_threshold']);
					}
					unset($link_threshold);
				}

				$link_threshold_relation_map = $this->createRelationMap($link_thresholds, 'linkid', 'linkthresholdid');
				$link_thresholds = $this->unsetExtraFields($link_thresholds, ['linkthresholdid', 'linkid']);
				$links = $link_threshold_relation_map->mapMany($links, $link_thresholds, 'thresholds');
			}

			if ($this->outputIsRequested('highlights', $options['selectLinks'])) {
				$link_highlights = DBFetchArrayAssoc(DBselect(
					'SELECT slt.linkthresholdid,slt.linkid,slt.pattern,slt.sortorder,slt.drawtype,slt.color'.
					' FROM sysmap_link_threshold slt'.
					' WHERE slt.type='.self::LINK_THRESHOLD_TYPE_HIGHLIGHT.
						' AND '.dbConditionId('slt.linkid', $relation_map->getRelatedIds()).
					' ORDER BY slt.sortorder'
				), 'linkthresholdid');
				$link_highlight_relation_map = $this->createRelationMap($link_highlights, 'linkid', 'linkthresholdid');
				$link_highlights = $this->unsetExtraFields($link_highlights, ['linkthresholdid', 'linkid']);
				$links = $link_highlight_relation_map->mapMany($links, $link_highlights, 'highlights');
			}

			if ($this->outputIsRequested('permission', $options['selectLinks']) && $links) {
				if ($options['editable']) {
					foreach ($links as &$link) {
						$link['permission'] = PERM_READ_WRITE;
					}
					unset($link);
				}
				elseif (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
					foreach ($links as &$link) {
						$link['permission'] = PERM_READ;
					}
					unset($link);
				}
				else {
					$trigger_linkids = [];
					$item_linkids = [];

					foreach ($links as &$link) {
						if ($link['indicator_type'] == MAP_INDICATOR_TYPE_TRIGGER) {
							$link['permission'] = PERM_NONE;
							$trigger_linkids[$link['linkid']] = true;
						}
						elseif ($link['indicator_type'] == MAP_INDICATOR_TYPE_ITEM_VALUE) {
							$link['permission'] = PERM_NONE;
							$item_linkids[$link['itemid']][] = $link['linkid'];
						}
						else {
							$link['permission'] = PERM_READ;
						}
					}
					unset($link);

					if ($trigger_linkids) {
						$resource = DBselect(
							'SELECT slt.linkid,slt.triggerid'.
							' FROM sysmaps_link_triggers slt'.
							' WHERE '.dbConditionId('slt.linkid', array_keys($trigger_linkids))
						);

						$triggerids = [];

						while ($db_link_trigger = DBfetch($resource)) {
							$triggerids[$db_link_trigger['triggerid']][] = $db_link_trigger['linkid'];
						}

						$db_triggers = $triggerids
							? API::Trigger()->get([
								'output' => [],
								'triggerids' => array_keys($triggerids),
								'preservekeys' => true
							])
							: [];

						foreach ($triggerids as $triggerid => $linkids) {
							if (array_key_exists($triggerid, $db_triggers)) {
								foreach ($linkids as $linkid) {
									$links[$linkid]['permission'] = PERM_READ;
								}
							}
						}
					}

					if ($item_linkids) {
						$db_items = API::Item()->get([
							'output' => [],
							'itemids' => array_keys($item_linkids),
							'preservekeys' => true
						]);

						foreach ($item_linkids as $itemid => $linkids) {
							if (array_key_exists($itemid, $db_items)) {
								foreach ($linkids as $linkid) {
									$links[$linkid]['permission'] = PERM_READ;
								}
							}
						}
					}
				}
			}

			$links = $this->unsetExtraFields($links, ['sysmapid']);
			$links = $this->unsetExtraFields($links, ['linkid', 'indicator_type', 'itemid'], $options['selectLinks']);
			$result = $relation_map->mapMany($result, $links, 'links');
		}

		// adding urls
		if ($options['selectUrls'] !== null && $options['selectUrls'] != API_OUTPUT_COUNT) {
			$links = API::getApiService()->select('sysmap_url', [
				'output' => $this->outputExtend($options['selectUrls'], ['sysmapid', 'sysmapurlid']),
				'filter' => ['sysmapid' => $sysmapIds],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($links, 'sysmapid', 'sysmapurlid');

			$links = $this->unsetExtraFields($links, ['sysmapid', 'sysmapurlid'], $options['selectUrls']);
			$result = $relation_map->mapMany($result, $links, 'urls');
		}

		// Adding user shares.
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$userids = [];
			$relation_map = $this->createRelationMap($result, 'sysmapid', 'userid', 'sysmap_user');
			$related_ids = $relation_map->getRelatedIds();

			if ($related_ids) {
				// Get all allowed users.
				$users = API::User()->get([
					'output' => ['userid'],
					'userids' => $related_ids,
					'preservekeys' => true
				]);

				$userids = zbx_objectValues($users, 'userid');
			}

			if ($userids) {
				$users = API::getApiService()->select('sysmap_user', [
					'output' => $this->outputExtend($options['selectUsers'], ['sysmapid', 'userid']),
					'filter' => ['sysmapid' => $sysmapIds, 'userid' => $userids],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($users, 'sysmapid', 'sysmapuserid');

				$users = $this->unsetExtraFields($users, ['sysmapuserid', 'userid', 'permission'],
					$options['selectUsers']
				);

				foreach ($users as &$user) {
					unset($user['sysmapid']);
				}
				unset($user);

				$result = $relation_map->mapMany($result, $users, 'users');
			}
			else {
				foreach ($result as &$row) {
					$row['users'] = [];
				}
				unset($row);
			}
		}

		// Adding user group shares.
		if ($options['selectUserGroups'] !== null && $options['selectUserGroups'] != API_OUTPUT_COUNT) {
			$groupids = [];
			$relation_map = $this->createRelationMap($result, 'sysmapid', 'usrgrpid', 'sysmap_usrgrp');
			$related_ids = $relation_map->getRelatedIds();

			if ($related_ids) {
				// Get all allowed groups.
				$groups = API::UserGroup()->get([
					'output' => ['usrgrpid'],
					'usrgrpids' => $related_ids,
					'preservekeys' => true
				]);

				$groupids = zbx_objectValues($groups, 'usrgrpid');
			}

			if ($groupids) {
				$user_groups = API::getApiService()->select('sysmap_usrgrp', [
					'output' => $this->outputExtend($options['selectUserGroups'], ['sysmapid', 'usrgrpid']),
					'filter' => ['sysmapid' => $sysmapIds, 'usrgrpid' => $groupids],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($user_groups, 'sysmapid', 'sysmapusrgrpid');

				$user_groups = $this->unsetExtraFields($user_groups, ['sysmapusrgrpid', 'usrgrpid', 'permission'],
					$options['selectUserGroups']
				);

				foreach ($user_groups as &$user_group) {
					unset($user_group['sysmapid']);
				}
				unset($user_group);

				$result = $relation_map->mapMany($result, $user_groups, 'userGroups');
			}
			else {
				foreach ($result as &$row) {
					$row['userGroups'] = [];
				}
				unset($row);
			}
		}

		return $result;
	}
}

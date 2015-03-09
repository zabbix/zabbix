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
 * Class containing methods for operations with maps.
 *
 * @package API
 */
class CMap extends CMapElement {

	protected $tableName = 'sysmaps';
	protected $tableAlias = 's';
	protected $sortColumns = array('name', 'width', 'height');

	/**
	 * Get map data.
	 *
	 * @param array  $options
	 * @param array  $options['nodeids']					Node IDs
	 * @param array  $options['groupids']					HostGroup IDs
	 * @param array  $options['hostids']					Host IDs
	 * @param bool   $options['monitored_hosts']			only monitored Hosts
	 * @param bool   $options['templated_hosts']			include templates in result
	 * @param bool   $options['with_items']					only with items
	 * @param bool   $options['with_monitored_items']		only with monitored items
	 * @param bool   $options['with_triggers'] only with	triggers
	 * @param bool   $options['with_monitored_triggers']	only with monitored triggers
	 * @param bool   $options['with_httptests'] only with	http tests
	 * @param bool   $options['with_monitored_httptests']	only with monitored http tests
	 * @param bool   $options['with_graphs']				only with graphs
	 * @param bool   $options['editable']					only with read-write permission. Ignored for SuperAdmins
	 * @param int    $options['count']						count Hosts, returned column name is rowscount
	 * @param string $options['pattern']					search hosts by pattern in host names
	 * @param int    $options['limit']						limit selection
	 * @param string $options['sortorder']
	 * @param string $options['sortfield']
	 *
	 * @return array|boolean Host data as array or false if error
	 */
	public function get(array $options = array()) {
		$result = array();
		$userType = self::$userData['type'];

		$sqlParts = array(
			'select'	=> array('sysmaps' => 's.sysmapid'),
			'from'		=> array('sysmaps' => 'sysmaps s'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'sysmapids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectSelements'			=> null,
			'selectLinks'				=> null,
			'selectIconMap'				=> null,
			'selectUrls'				=> null,
			'countOutput'				=> null,
			'expandUrls' 				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// sysmapids
		if (!is_null($options['sysmapids'])) {
			zbx_value2array($options['sysmapids']);
			$sqlParts['where']['sysmapid'] = dbConditionInt('s.sysmapid', $options['sysmapids']);
		}

		// search
		if (!is_null($options['search'])) {
			zbx_db_search('sysmaps s', $options, $sqlParts);
		}

		// filter
		if (!is_null($options['filter'])) {
			$this->dbFilter('sysmaps s', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sysmapids = array();

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($sysmap = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $sysmap['rowscount'];
			}
			else {
				$sysmapids[$sysmap['sysmapid']] = $sysmap['sysmapid'];

				if (!isset($result[$sysmap['sysmapid']])) {
					$result[$sysmap['sysmapid']] = array();
				}

				// originally we intended not to pass those parameters if advanced labels are off, but they might be useful
				// leaving this block commented
				// if (isset($sysmap['label_format']) && ($sysmap['label_format'] == SYSMAP_LABEL_ADVANCED_OFF)) {
				// 	unset($sysmap['label_string_hostgroup'], $sysmap['label_string_host'], $sysmap['label_string_trigger'], $sysmap['label_string_map'], $sysmap['label_string_image']);
				// }

				$result[$sysmap['sysmapid']] += $sysmap;
			}
		}

		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if ($result) {
				$linkTriggers = array();

				$dbLinkTriggers = DBselect(
					'SELECT slt.triggerid,sl.sysmapid'.
					' FROM sysmaps_link_triggers slt,sysmaps_links sl'.
					' WHERE '.dbConditionInt('sl.sysmapid', $sysmapids).
						' AND sl.linkid=slt.linkid'
				);
				while ($linkTrigger = DBfetch($dbLinkTriggers)) {
					$linkTriggers[$linkTrigger['sysmapid']] = $linkTrigger['triggerid'];
				}

				if ($linkTriggers) {
					$trigOptions = array(
						'triggerids' => $linkTriggers,
						'editable' => $options['editable'],
						'output' => array('triggerid'),
						'preservekeys' => true
					);
					$allTriggers = API::Trigger()->get($trigOptions);
					foreach ($linkTriggers as $id => $triggerid) {
						if (!isset($allTriggers[$triggerid])) {
							unset($result[$id], $sysmapids[$id]);
						}
					}
				}

				$hostsToCheck = array();
				$mapsToCheck = array();
				$triggersToCheck = array();
				$hostGroupsToCheck = array();

				$selements = array();
				$dbSelements = DBselect('SELECT se.* FROM sysmaps_elements se WHERE '.dbConditionInt('se.sysmapid', $sysmapids));
				while ($selement = DBfetch($dbSelements)) {
					$selements[$selement['selementid']] = $selement;

					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST:
							$hostsToCheck[$selement['elementid']] = $selement['elementid'];
							break;
						case SYSMAP_ELEMENT_TYPE_MAP:
							$mapsToCheck[$selement['elementid']] = $selement['elementid'];
							break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$triggersToCheck[$selement['elementid']] = $selement['elementid'];
							break;
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$hostGroupsToCheck[$selement['elementid']] = $selement['elementid'];
							break;
					}
				}

				$nodeIds = get_current_nodeid(true);

				if ($hostsToCheck) {
					$allowedHosts = API::Host()->get(array(
						'hostids' => $hostsToCheck,
						'nodeids' => $nodeIds,
						'editable' => $options['editable'],
						'preservekeys' => true,
						'output' => array('hostid')
					));

					foreach ($hostsToCheck as $elementid) {
						if (!isset($allowedHosts[$elementid])) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if ($mapsToCheck) {
					$allowedMaps = $this->get(array(
						'sysmapids' => $mapsToCheck,
						'nodeids' => $nodeIds,
						'editable' => $options['editable'],
						'preservekeys' => true,
						'output' => array('sysmapid')
					));

					foreach ($mapsToCheck as $elementid) {
						if (!isset($allowedMaps[$elementid])) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if ($triggersToCheck) {
					$allowedTriggers = API::Trigger()->get(array(
						'triggerids' => $triggersToCheck,
						'nodeids' => $nodeIds,
						'editable' => $options['editable'],
						'preservekeys' => true,
						'output' => array('triggerid')
					));

					foreach ($triggersToCheck as $elementid) {
						if (!isset($allowedTriggers[$elementid])) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if ($hostGroupsToCheck) {
					$allowedHostGroups = API::HostGroup()->get(array(
						'groupids' => $hostGroupsToCheck,
						'nodeids' => $nodeIds,
						'editable' => $options['editable'],
						'preservekeys' => true,
						'output' => array('groupid')
					));

					foreach ($hostGroupsToCheck as $elementid) {
						if (!isset($allowedHostGroups[$elementid])) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}
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
	 * Get Sysmap IDs by Sysmap params.
	 *
	 * @param array $sysmapData
	 * @param array $sysmapData['name']
	 * @param array $sysmapData['sysmapid']
	 *
	 * @return string sysmapid
	 */
	public function getObjects(array $sysmapData) {
		$options = array(
			'filter' => $sysmapData,
			'output' => API_OUTPUT_EXTEND
		);

		if (isset($sysmapData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($sysmapData['node']);
		}
		elseif (isset($sysmapData['nodeids'])) {
			$options['nodeids'] = $sysmapData['nodeids'];
		}

		return $this->get($options);
	}

	public function exists($object) {
		$keyFields = array(array('sysmapid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => array('sysmapid'),
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

	public function checkInput(&$maps, $method) {
		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

		// permissions
		if ($update || $delete) {
			$mapDbFields = array('sysmapid' => null);

			$dbMaps = $this->get(array(
				'sysmapids' => zbx_objectValues($maps, 'sysmapid'),
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'preservekeys' => true,
				'selectLinks' => API_OUTPUT_EXTEND,
				'selectSelements' => API_OUTPUT_EXTEND,
				'selectUrls' => API_OUTPUT_EXTEND
			));
		}
		else {
			$mapDbFields = array(
				'name' => null,
				'width' => null,
				'height' => null,
				'urls' => array(),
				'selements' => array(),
				'links' => array()
			);
		}

		$mapNames = array();

		foreach ($maps as &$map) {
			if (!check_db_fields($mapDbFields, $map)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect fields for sysmap.'));
			}

			if ($update || $delete) {
				if (!isset($dbMaps[$map['sysmapid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				$dbMap = array_merge($dbMaps[$map['sysmapid']], $map);
			}
			else {
				$dbMap = $map;
			}

			if (isset($map['name'])) {
				if (isset($mapNames[$map['name']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate map name for map "%s".', $dbMap['name']));
				}
				else {
					$mapNames[$map['name']] = $update ? $map['sysmapid'] : 1;
				}
			}

			if (isset($map['width']) && ($map['width'] > 65535 || $map['width'] < 1)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect map width value for map "%s".', $dbMap['name']));
			}

			if (isset($map['height']) && ($map['height'] > 65535 || $map['height'] < 1)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect map height value for map "%s".', $dbMap['name']));
			}

			// labels
			$mapLabels = array('label_type' => array('typeName' => _('icon')));

			if ($dbMap['label_format'] == SYSMAP_LABEL_ADVANCED_ON) {
				$mapLabels['label_type_hostgroup'] = array('string' => 'label_string_hostgroup', 'typeName' => _('host group'));
				$mapLabels['label_type_host'] = array('string' => 'label_string_host', 'typeName' => _('host'));
				$mapLabels['label_type_trigger'] = array('string' => 'label_string_trigger', 'typeName' => _('trigger'));
				$mapLabels['label_type_map'] = array('string' => 'label_string_map', 'typeName' => _('map'));
				$mapLabels['label_type_image'] = array('string' => 'label_string_image', 'typeName' => _('image'));
			}

			foreach ($mapLabels as $labelName => $labelData) {
				if (!isset($map[$labelName])) {
					continue;
				}

				if (sysmapElementLabel($map[$labelName]) === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));
				}

				if ($map[$labelName] == MAP_LABEL_TYPE_CUSTOM) {
					if (!isset($labelData['string'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));
					}

					if (!isset($map[$labelData['string']]) || zbx_empty($map[$labelData['string']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Custom label for map "%2$s" elements of type "%1$s" may not be empty.', $labelData['typeName'], $dbMap['name']));
					}
				}

				if ($labelName == 'label_type_image' && $map[$labelName] == MAP_LABEL_TYPE_STATUS) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));
				}

				if ($labelName == 'label_type' || $labelName == 'label_type_host') {
					continue;
				}

				if ($map[$labelName] == MAP_LABEL_TYPE_IP) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));
				}
			}

			// validating grid options
			$possibleGridSizes = array(20, 40, 50, 75, 100);

			if ($update || $create) {
				// grid size
				if (isset($map['grid_size']) && !in_array($map['grid_size'], $possibleGridSizes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s".', $map['grid_size'], implode('", "', $possibleGridSizes)));
				}

				// grid auto align
				if (isset($map['grid_align']) && $map['grid_align'] != SYSMAP_GRID_ALIGN_ON && $map['grid_align'] != SYSMAP_GRID_ALIGN_OFF) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Value "%1$s" is invalid for parameter "grid_align". Choices are: "%2$s" and "%3$s"', $map['grid_align'], SYSMAP_GRID_ALIGN_ON, SYSMAP_GRID_ALIGN_OFF));
				}

				// grid show
				if (isset($map['grid_show']) && $map['grid_show'] != SYSMAP_GRID_SHOW_ON && $map['grid_show'] != SYSMAP_GRID_SHOW_OFF) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s" and "%3$s".', $map['grid_show'], SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_SHOW_OFF));
				}
			}

			// urls
			if (isset($map['urls']) && !empty($map['urls'])) {
				$urlNames = zbx_toHash($map['urls'], 'name');

				foreach ($map['urls'] as $url) {
					if ($url['name'] === '' || $url['url'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Link should have both "name" and "url" fields for map "%s".', $dbMap['name']));
					}

					if (!isset($urlNames[$url['name']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Link name should be unique for map "%s".', $dbMap['name']));
					}

					unset($urlNames[$url['name']]);
				}
			}

			// map selement links
			if (!empty($map['links'])) {
				$selementIds = zbx_objectValues($dbMap['selements'], 'selementid');

				foreach ($map['links'] as $link) {
					if (!in_array($link['selementid1'], $selementIds)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Link selementid1 field is pointing to a nonexistent map selement ID "%1$s" for map "%2$s".',
								$link['selementid1'], $dbMap['name']
						));
					}

					if (!in_array($link['selementid2'], $selementIds)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Link selementid2 field is pointing to a nonexistent map selement ID "%1$s" for map "%2$s".',
								$link['selementid2'], $dbMap['name']
						));
					}
				}
			}
		}
		unset($map);

		// exists
		if (($create || $update) && $mapNames) {
			$existDbMaps = $this->get(array(
				'filter' => array('name' => array_keys($mapNames)),
				'output' => array('sysmapid', 'name'),
				'nopermissions' => true
			));
			foreach ($existDbMaps as $dbMap) {
				if ($create || bccomp($mapNames[$dbMap['name']], $dbMap['sysmapid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Map with name "%s" already exists.', $dbMap['name']));
				}
			}
		}

		return ($update || $delete) ? $dbMaps : true;
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
	public function create($maps) {
		$maps = zbx_toArray($maps);

		$this->checkInput($maps, __FUNCTION__);

		$sysmapIds = DB::insert('sysmaps', $maps);

		$newUrls = array();
		$newSelements = array();
		$newLinks = array();

		foreach ($sysmapIds as $key => $sysmapId) {
			foreach ($maps[$key]['urls'] as $url) {
				$url['sysmapid'] = $sysmapId;
				$newUrls[] = $url;
			}

			foreach ($maps[$key]['selements'] as $snum => $selement) {
				$maps[$key]['selements'][$snum]['sysmapid'] = $sysmapId;
			}

			$newSelements = array_merge($newSelements, $maps[$key]['selements']);

			foreach ($maps[$key]['links'] as $lnum => $link) {
				$maps[$key]['links'][$lnum]['sysmapid'] = $sysmapId;
			}

			$newLinks = array_merge($newLinks, $maps[$key]['links']);
		}

		DB::insert('sysmap_url', $newUrls);

		if ($newSelements) {
			$selementids = $this->createSelements($newSelements);

			if ($newLinks) {
				// links
				$mapVirtSelements = array();
				foreach ($selementids['selementids'] as $key => $selementid) {
					$mapVirtSelements[$newSelements[$key]['selementid']] = $selementid;
				}

				foreach ($newLinks as $key => $link) {
					$newLinks[$key]['selementid1'] = $mapVirtSelements[$link['selementid1']];
					$newLinks[$key]['selementid2'] = $mapVirtSelements[$link['selementid2']];
				}
				unset($mapVirtSelements);

				$linkIds = $this->createLinks($newLinks);

				// linkTriggers
				$newLinkTriggers = array();
				foreach ($linkIds['linkids'] as $key => $linkId) {
					if (!isset($newLinks[$key]['linktriggers'])) {
						continue;
					}

					foreach ($newLinks[$key]['linktriggers'] as $linktrigger) {
						$linktrigger['linkid'] = $linkId;
						$newLinkTriggers[] = $linktrigger;
					}
				}

				if ($newLinkTriggers) {
					$this->createLinkTriggers($newLinkTriggers);
				}
			}
		}

		return array('sysmapids' => $sysmapIds);
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
	public function update(array $maps) {
		$maps = zbx_toArray($maps);
		$sysmapIds = zbx_objectValues($maps, 'sysmapid');

		$dbMaps = $this->checkInput($maps, __FUNCTION__);

		$updateMaps = array();
		$urlIdsToDelete = $urlsToUpdate = $urlsToAdd = array();
		$selementsToDelete = $selementsToUpdate = $selementsToAdd = array();
		$linksToDelete = $linksToUpdate = $linksToAdd = array();

		foreach ($maps as $map) {
			$updateMaps[] = array(
				'values' => $map,
				'where' => array('sysmapid' => $map['sysmapid']),
			);

			$dbMap = $dbMaps[$map['sysmapid']];

			// urls
			if (isset($map['urls'])) {
				$urlDiff = zbx_array_diff($map['urls'], $dbMap['urls'], 'name');

				foreach ($urlDiff['both'] as $updateUrl) {
					$urlsToUpdate[] = array(
						'values' => $updateUrl,
						'where' => array('name' => $updateUrl['name'], 'sysmapid' => $map['sysmapid'])
					);
				}

				foreach ($urlDiff['first'] as $newUrl) {
					$newUrl['sysmapid'] = $map['sysmapid'];
					$urlsToAdd[] = $newUrl;
				}

				$urlIdsToDelete = array_merge($urlIdsToDelete, zbx_objectValues($urlDiff['second'], 'sysmapurlid'));
			}

			// elements
			if (isset($map['selements'])) {
				$selementDiff = zbx_array_diff($map['selements'], $dbMap['selements'], 'selementid');

				// we need sysmapid for add operations
				foreach ($selementDiff['first'] as $newSelement) {
					$newSelement['sysmapid'] = $map['sysmapid'];
					$selementsToAdd[] = $newSelement;
				}

				$selementsToUpdate = array_merge($selementsToUpdate, $selementDiff['both']);
				$selementsToDelete = array_merge($selementsToDelete, $selementDiff['second']);
			}

			// links
			if (isset($map['links'])) {
				$linkDiff = zbx_array_diff($map['links'], $dbMap['links'], 'linkid');

				// we need sysmapId for add operations
				foreach ($linkDiff['first'] as $newLink) {
					$newLink['sysmapid'] = $map['sysmapid'];

					$linksToAdd[] = $newLink;
				}

				$linksToUpdate = array_merge($linksToUpdate, $linkDiff['both']);
				$linksToDelete = array_merge($linksToDelete, $linkDiff['second']);
			}
		}

		DB::update('sysmaps', $updateMaps);

		// urls
		DB::insert('sysmap_url', $urlsToAdd);
		DB::update('sysmap_url', $urlsToUpdate);

		if ($urlIdsToDelete) {
			DB::delete('sysmap_url', array('sysmapurlid' => $urlIdsToDelete));
		}

		// selements
		$newSelementIds = array('selementids' => array());
		if ($selementsToAdd) {
			$newSelementIds = $this->createSelements($selementsToAdd);
		}

		if ($selementsToUpdate) {
			$this->updateSelements($selementsToUpdate);
		}

		if ($selementsToDelete) {
			$this->deleteSelements($selementsToDelete);
		}

		// links
		if ($linksToAdd || $linksToUpdate) {
			$selementsNames = array();
			foreach ($newSelementIds['selementids'] as $key => $selementId) {
				$selementsNames[$selementsToAdd[$key]['selementid']] = $selementId;
			}

			foreach ($selementsToUpdate as $selement) {
				$selementsNames[$selement['selementid']] = $selement['selementid'];
			}

			foreach ($linksToAdd as $key => $link) {
				if (isset($selementsNames[$link['selementid1']])) {
					$linksToAdd[$key]['selementid1'] = $selementsNames[$link['selementid1']];
				}
				if (isset($selementsNames[$link['selementid2']])) {
					$linksToAdd[$key]['selementid2'] = $selementsNames[$link['selementid2']];
				}
			}

			foreach ($linksToUpdate as $key => $link) {
				if (isset($selementsNames[$link['selementid1']])) {
					$linksToUpdate[$key]['selementid1'] = $selementsNames[$link['selementid1']];
				}
				if (isset($selementsNames[$link['selementid2']])) {
					$linksToUpdate[$key]['selementid2'] = $selementsNames[$link['selementid2']];
				}
			}

			unset($selementsNames);
		}

		$newLinkIds = $updateLinkIds = array('linkids' => array());

		if ($linksToAdd) {
			$newLinkIds = $this->createLinks($linksToAdd);
		}

		if ($linksToUpdate) {
			$updateLinkIds = $this->updateLinks($linksToUpdate);
		}

		if ($linksToDelete) {
			$this->deleteLinks($linksToDelete);
		}

		// link triggers
		$linkTriggersToDelete = $linkTriggersToUpdate = $linkTriggersToAdd = array();

		foreach ($newLinkIds['linkids'] as $key => $linkId) {
			if (!isset($linksToAdd[$key]['linktriggers'])) {
				continue;
			}

			foreach ($linksToAdd[$key]['linktriggers'] as $linkTrigger) {
				$linkTrigger['linkid'] = $linkId;
				$linkTriggersToAdd[] = $linkTrigger;
			}
		}

		$dbLinks = array();

		$linkTriggerResource = DBselect(
			'SELECT slt.* FROM sysmaps_link_triggers slt WHERE '.dbConditionInt('slt.linkid', $updateLinkIds['linkids'])
		);
		while ($dbLinkTrigger = DBfetch($linkTriggerResource)) {
			zbx_subarray_push($dbLinks, $dbLinkTrigger['linkid'], $dbLinkTrigger);
		}

		foreach ($updateLinkIds['linkids'] as $key => $linkId) {
			if (!isset($linksToUpdate[$key]['linktriggers'])) {
				continue;
			}

			$dbLinkTriggers = isset($dbLinks[$linkId]) ? $dbLinks[$linkId] : array();
			$dbLinkTriggersDiff = zbx_array_diff($linksToUpdate[$key]['linktriggers'], $dbLinkTriggers, 'linktriggerid');

			foreach ($dbLinkTriggersDiff['first'] as $newLinkTrigger) {
				$newLinkTrigger['linkid'] = $linkId;
				$linkTriggersToAdd[] = $newLinkTrigger;
			}

			$linkTriggersToUpdate = array_merge($linkTriggersToUpdate, $dbLinkTriggersDiff['both']);
			$linkTriggersToDelete = array_merge($linkTriggersToDelete, $dbLinkTriggersDiff['second']);
		}

		if ($linkTriggersToDelete) {
			$this->deleteLinkTriggers($linkTriggersToDelete);
		}

		if ($linkTriggersToAdd) {
			$this->createLinkTriggers($linkTriggersToAdd);
		}

		if ($linkTriggersToUpdate) {
			$this->updateLinkTriggers($linkTriggersToUpdate);
		}

		return array('sysmapids' => $sysmapIds);
	}

	/**
	 * Delete Map.
	 *
	 * @param array $sysmapIds
	 *
	 * @return array
	 */
	public function delete($sysmapIds) {
		$maps = zbx_toObject($sysmapIds, 'sysmapid');

		$this->checkInput($maps, __FUNCTION__);

		DB::delete('sysmaps_elements', array(
			'elementid' => $sysmapIds,
			'elementtype' => SYSMAP_ELEMENT_TYPE_MAP
		));
		DB::delete('screens_items', array(
			'resourceid' => $sysmapIds,
			'resourcetype' => SCREEN_RESOURCE_MAP
		));
		DB::delete('profiles', array(
			'idx' => 'web.maps.sysmapid',
			'value_id' => $sysmapIds
		));
		DB::delete('profiles', array(
			'idx' => 'web.favorite.sysmapids',
			'source' => 'sysmapid',
			'value_id' => $sysmapIds
		));
		DB::delete('sysmaps', array('sysmapid' => $sysmapIds));

		return array('sysmapids' => $sysmapIds);
	}

	private function expandUrlMacro($url, $selement) {
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$macro = '{HOSTGROUP.ID}';
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$macro = '{TRIGGER.ID}';
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$macro = '{MAP.ID}';
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				$macro = '{HOST.ID}';
				break;

			default:
				$macro = false;
		}

		if ($macro) {
			$url['url'] = str_replace($macro, $selement['elementid'], $url['url']);
		}

		return $url;
	}

	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'sysmapids' => $ids,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'sysmapids' => $ids,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific ids are given
		if ($options['sysmapids'] === null) {
			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$sysmapIds = array_keys($result);

		// adding elements
		if ($options['selectSelements'] !== null && $options['selectSelements'] != API_OUTPUT_COUNT) {
			$selements = API::getApi()->select('sysmaps_elements', array(
				'output' => $this->outputExtend('sysmaps_elements', array('selementid', 'sysmapid'), $options['selectSelements']),
				'filter' => array('sysmapid' => $sysmapIds),
				'preservekeys' => true,
				'nodeids' => get_current_nodeid(true)
			));
			$relationMap = $this->createRelationMap($selements, 'sysmapid', 'selementid');

			// add selement URLs
			if ($this->outputIsRequested('urls', $options['selectSelements'])) {
				foreach ($selements as &$selement) {
					$selement['urls'] = array();
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
								$selements[$snum]['urls'][] = $this->expandUrlMacro($mapUrl, $selement);
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
					$selements[$selementUrl['selementid']]['urls'][] = is_null($options['expandUrls'])
						? $selementUrl
						: $this->expandUrlMacro($selementUrl, $selements[$selementUrl['selementid']]);
				}
			}

			$selements = $this->unsetExtraFields($selements, array('sysmapid', 'selementid'), $options['selectSelements']);
			$result = $relationMap->mapMany($result, $selements, 'selements');
		}

		// adding icon maps
		if ($options['selectIconMap'] !== null && $options['selectIconMap'] != API_OUTPUT_COUNT) {
			$iconMaps = API::IconMap()->get(array(
				'output' => $this->outputExtend('icon_map', array('sysmapid', 'iconmapid'), $options['selectIconMap']),
				'sysmapids' => $sysmapIds,
				'preservekeys' => true,
				'nopermissions' => true
			));
			$relationMap = $this->createRelationMap($iconMaps, 'sysmapid', 'iconmapid');

			$iconMaps = $this->unsetExtraFields($iconMaps, array('sysmapid', 'iconmapid'), $options['selectIconMap']);
			$result = $relationMap->mapOne($result, $iconMaps, 'iconmap');
		}

		// adding links
		if ($options['selectLinks'] !== null && $options['selectLinks'] != API_OUTPUT_COUNT) {
			$links = API::getApi()->select('sysmaps_links', array(
				'output' => $this->outputExtend('sysmaps_links', array('sysmapid', 'linkid'), $options['selectLinks']),
				'filter' => array('sysmapid' => $sysmapIds),
				'preservekeys' => true,
				'nodeids' => get_current_nodeid(true)
			));
			$relationMap = $this->createRelationMap($links, 'sysmapid', 'linkid');

			// add link triggers
			if ($this->outputIsRequested('linktriggers', $options['selectLinks'])) {
				$linkTriggers = DBFetchArrayAssoc(DBselect(
					'SELECT DISTINCT slt.*'.
					' FROM sysmaps_link_triggers slt'.
					' WHERE '.dbConditionInt('slt.linkid', $relationMap->getRelatedIds())
				), 'linktriggerid');
				$linkTriggerRelationMap = $this->createRelationMap($linkTriggers, 'linkid', 'linktriggerid');
				$links = $linkTriggerRelationMap->mapMany($links, $linkTriggers, 'linktriggers');
			}

			$links = $this->unsetExtraFields($links, array('sysmapid', 'linkid'), $options['selectLinks']);
			$result = $relationMap->mapMany($result, $links, 'links');
		}

		// adding urls
		if ($options['selectUrls'] !== null && $options['selectUrls'] != API_OUTPUT_COUNT) {
			$links = API::getApi()->select('sysmap_url', array(
				'output' => $this->outputExtend('sysmap_url', array('sysmapid', 'sysmapurlid'), $options['selectUrls']),
				'filter' => array('sysmapid' => $sysmapIds),
				'preservekeys' => true,
				'nodeids' => get_current_nodeid(true)
			));
			$relationMap = $this->createRelationMap($links, 'sysmapid', 'sysmapurlid');

			$links = $this->unsetExtraFields($links, array('sysmapid', 'sysmapurlid'), $options['selectUrls']);
			$result = $relationMap->mapMany($result, $links, 'urls');
		}

		return $result;
	}
}

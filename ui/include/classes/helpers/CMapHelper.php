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


class CMapHelper {

	/**
	 * Get map data with resolved element / link states.
	 *
	 * @param array  $sysmapids					Map IDs.
	 * @param array  $options					Options used to retrieve actions.
	 * @param int    $options['severity_min']	Minimum severity.
	 * @param int    $options['unique_id']
	 *
	 * @return array
	 */
	public static function get($sysmapids, array $options = []) {
		$maps = API::Map()->get([
			'output' => ['sysmapid', 'name', 'width', 'height', 'backgroundid', 'label_type', 'label_location',
				'highlight', 'expandproblem', 'markelements', 'show_unack', 'label_format', 'label_type_host',
				'label_type_hostgroup', 'label_type_trigger', 'label_type_map', 'label_type_image', 'label_string_host',
				'label_string_hostgroup', 'label_string_trigger', 'label_string_map', 'label_string_image', 'iconmapid',
				'severity_min', 'show_suppressed'
			],
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font', 'font_size',
				'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width', 'border_color',
				'background_color', 'zindex'
			],
			'selectLines' => ['sysmap_shapeid', 'x1', 'x2', 'y1', 'y2', 'line_type', 'line_width', 'line_color',
				'zindex'
			],
			'selectSelements' => ['selementid', 'elements', 'elementtype', 'iconid_off', 'iconid_on', 'label',
				'label_location', 'x', 'y', 'iconid_disabled', 'iconid_maintenance', 'elementsubtype', 'areatype',
				'width', 'height', 'viewtype', 'use_iconmap', 'permission', 'evaltype', 'tags'
			],
			'selectLinks' => ['linkid', 'selementid1', 'selementid2', 'drawtype', 'color', 'label', 'linktriggers',
				'permission'
			],
			'sysmapids' => $sysmapids,
			'preservekeys' => true
		]);
		$map = reset($maps);

		$theme = getUserGraphTheme();

		if (!$map) {
			$map = [
				'sysmapid' => -1,
				'width' => 320,
				'height' => 150,
				'backgroundid' => null,
				'severity_min' => 0,
				'show_unack' => EXTACK_OPTION_ALL,
				'label_location' => MAP_LABEL_LOC_BOTTOM,
				'selements' => [],
				'links' => [],
				'shapes' => [[
					'type' => SYSMAP_SHAPE_TYPE_RECTANGLE,
					'x' => 0,
					'y' => 0,
					'width' => 320,
					'height' => 150,
					'font' => 9,
					'font_size' => 11,
					'font_color' => 'FF0000',
					'text' => _('No permissions to referred object or it does not exist!')
				]],
				'aria_label' => ''
			];
		}
		else {
			if (array_key_exists('severity_min', $options)) {
				$map['severity_min'] = $options['severity_min'];
			}
			else {
				$options['severity_min'] = $map['severity_min'];
			}

			// Populate host group elements of subtype 'SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS' with hosts.
			$areas = self::populateHostGroupsWithHosts($map, $theme);

			// Apply inherited label options.
			$map = self::applyMapElementLabelProperties($map);

			// Resolve macros in map element labels.
			$resolve_opt = ['resolve_element_label' => true];
			$map['selements'] = CMacrosResolverHelper::resolveMacrosInMapElements($map['selements'], $resolve_opt);

			self::resolveMapState($map, $areas, $options);
		}

		return [
			'id' => $map['sysmapid'],
			'theme' => $theme,
			'canvas' => [
				'width' => $map['width'],
				'height' => $map['height']
			],
			'refresh' => 'map.php?sysmapid='.$map['sysmapid'].'&severity_min='.$map['severity_min']
				.(array_key_exists('unique_id', $options) ? '&unique_id='.$options['unique_id'] : ''),
			'background' => $map['backgroundid'],
			'label_location' => $map['label_location'],
			'elements' => array_values($map['selements']),
			'links' => array_values($map['links']),
			'shapes' => array_values($map['shapes']),
			'aria_label' => $map['aria_label'],
			'timestamp' => zbx_date2str(DATE_TIME_FORMAT_SECONDS)
		];
	}

	/**
	 * Function applies element labels inherited from map properties.
	 *
	 * @param array  $sysmap                             Map data.
	 * @param array  $sysmap[selements]                  Array of map elements.
	 * @param int    $sysmap[selements][][elementtype]   Map element type.
	 * @param int    $sysmap[label_format]               Map label format property.
	 * @param int    $sysmap[label_type]                 Map label type property.
	 * @param int    $sysmap[label_type_hostgroup]       Map host group element label type.
	 * @param string $sysmap[label_string_hostgroup]     Map host group element custom label.
	 * @param int    $sysmap[label_type_host]            Map host element label type.
	 * @param string $sysmap[label_string_host]          Map host element custom label.
	 * @param int    $sysmap[label_type_trigger]         Map trigger element label type.
	 * @param string $sysmap[label_string_trigger]       Map trigger element custom label.
	 * @param int    $sysmap[label_type_map]             Map submap element label type.
	 * @param string $sysmap[label_string_map]           Map submap element custom label.
	 * @param int    $sysmap[label_type_image]           Map image element label type.
	 * @param string $sysmap[label_string_image]         Map image element custom label.
	 *
	 * @return array
	 */
	public static function applyMapElementLabelProperties(array $sysmap) {
		// Define which $sysmap property holds value for each type of element.
		$label_properties = [
			SYSMAP_ELEMENT_TYPE_HOST_GROUP => [
				'field_label_type' => 'label_type_hostgroup',
				'field_custom_label' => 'label_string_hostgroup'
			],
			SYSMAP_ELEMENT_TYPE_HOST => [
				'field_label_type' => 'label_type_host',
				'field_custom_label' => 'label_string_host'
			],
			SYSMAP_ELEMENT_TYPE_TRIGGER => [
				'field_label_type' => 'label_type_trigger',
				'field_custom_label' => 'label_string_trigger'
			],
			SYSMAP_ELEMENT_TYPE_MAP => [
				'field_label_type' => 'label_type_map',
				'field_custom_label' => 'label_string_map'
			],
			SYSMAP_ELEMENT_TYPE_IMAGE => [
				'field_label_type' => 'label_type_image',
				'field_custom_label' => 'label_string_image'
			]
		];

		// Apply properties to each sysmap element.
		foreach ($sysmap['selements'] as &$selement) {
			$prop = $label_properties[$selement['elementtype']];
			$elmnt_label_type = ($sysmap['label_format'] == SYSMAP_LABEL_ADVANCED_ON)
				? $sysmap[$prop['field_label_type']]
				: $sysmap['label_type'];
			$inherited_label = null;

			if ($elmnt_label_type == MAP_LABEL_TYPE_NOTHING) {
				$inherited_label = '';
			}
			elseif ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST && $elmnt_label_type == MAP_LABEL_TYPE_IP) {
				$inherited_label = '{HOST.IP}';
			}
			elseif ($sysmap['label_format'] == SYSMAP_LABEL_ADVANCED_ON
					&& $sysmap[$prop['field_label_type']] == MAP_LABEL_TYPE_CUSTOM) {
				$inherited_label = $sysmap[$prop['field_custom_label']];
			}

			$selement['label_type'] = $elmnt_label_type;
			if ($inherited_label !== null) {
				$selement['label'] = $inherited_label;
			}
		}
		unset($selement);

		return $sysmap;
	}

	/**
	 * Resolve map element (selements and links) state.
	 *
	 * @param array  $sysmap                   Map data.
	 * @param array  $areas                    Areas representing array containing host group element IDs and dimension
	 *                                         properties of area.
	 * @param array  $options                  Options used to retrieve actions.
	 * @param int    $options['severity_min']  Minimum severity.
	 * @param int    $options['unique_id']
	 */
	protected static function resolveMapState(array &$sysmap, array $areas, array $options) {
		$map_info = getSelementsInfo($sysmap, ['severity_min' => $options['severity_min']]);
		$sysmap['selements'] = array_column($sysmap['selements'], null, 'selementid');

		processAreasCoordinates($sysmap, $areas, $map_info);

		// Adding element names and removing inaccessible triggers from readable elements.
		addElementNames($sysmap['selements']);

		foreach ($sysmap['selements'] as $id => &$element) {
			if ($element['permission'] < PERM_READ) {
				continue;
			}

			switch ($element['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_IMAGE:
					$map_info[$id]['name'] = _('Image');
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					// Move the trigger with problem and highest priority to the beginning of the trigger list.
					if (array_key_exists('triggerid', $map_info[$id])) {
						$trigger_pos = 0;

						foreach ($element['elements'] as $i => $trigger) {
							if ($trigger['triggerid'] == $map_info[$id]['triggerid']) {
								$trigger_pos = $i;
								break;
							}
						}

						if ($trigger_pos > 0) {
							$trigger = $element['elements'][$trigger_pos];
							unset($element['elements'][$trigger_pos]);
							array_unshift($element['elements'], $trigger);
						}
					}
					// break; is not missing here

				default:
					$map_info[$id]['name'] = $element['elements'][0]['elementName'];
			}
		}
		unset($element);

		$labels = getMapLabels($sysmap, $map_info);
		$highlights = getMapHighligts($sysmap, $map_info);
		$actions = getActionsBySysmap($sysmap, $options);
		$link_triggers_info = getMapLinkTriggerInfo($sysmap, $options);

		$problems_total = 0;
		$status_problems = [];
		$status_other = [];

		foreach ($sysmap['selements'] as $id => &$element) {
			$element['icon'] = (array_key_exists($id, $map_info) && array_key_exists('iconid', $map_info[$id]))
				? $map_info[$id]['iconid']
				: null;

			unset($element['width'], $element['height']);

			if ($element['permission'] >= PERM_READ) {
				$label = str_replace(['.', ','], ' ', $element['label']);

				if ($map_info[$id]['problems_total'] > 0) {
					$problems_total += $map_info[$id]['problems_total'];
					$problem_desc = str_replace(['.', ','], ' ', $map_info[$id]['aria_label']);
					$status_problems[] = sprintf('%1$s, %2$s, %3$s, %4$s. ',
						sysmap_element_types($element['elementtype']), _('Status problem'), $label, $problem_desc
					);
				}
				else {
					$element_status = _('Status ok');

					if (array_key_exists('info', $map_info[$id])
							&& array_key_exists('maintenance', $map_info[$id]['info'])) {
						$element_status = _('Status maintenance');
					}
					elseif (array_key_exists('info', $map_info[$id])
							&& array_key_exists('status', $map_info[$id]['info'])) {
						$element_status = _('Status disabled');
					}

					$status_other[] = sprintf('%1$s, %2$s, %3$s. ', sysmap_element_types($element['elementtype']),
						$element_status, $label
					);
				}

				$element['highlight'] = $highlights[$id];
				$element['actions'] = $actions[$id];
				$element['label'] = $labels[$id];
			}
			else {
				$element['highlight'] = '';
				$element['actions'] = null;
				$element['label'] = '';
			}

			if ($sysmap['markelements']) {
				$element['latelyChanged'] = $map_info[$id]['latelyChanged'];
			}
		}
		unset($element);

		$sysmap['aria_label'] = str_replace(['.', ','], ' ', $sysmap['name']).', '.
			_n('%1$s of %2$s element in problem state', '%1$s of %2$s elements in problem state',
				count($status_problems), count($sysmap['selements'])).
			', '.
			_n('%1$s problem in total', '%1$s problems in total', $problems_total).
			'. '.
			implode('', array_merge($status_problems, $status_other));

		$sysmap['shapes'] = CMacrosResolverHelper::resolveMapShapeLabelMacros($sysmap['name'], $sysmap['shapes']);
		$sysmap['links'] = CMacrosResolverHelper::resolveMapLinkLabelMacros($sysmap['links']);

		foreach ($sysmap['lines'] as $line) {
			$sysmap['shapes'][] = self::convertLineToShape($line);
		}

		foreach ($sysmap['links'] as &$link) {
			if ($link['permission'] >= PERM_READ && $link['linktriggers']) {
				$link_triggers = array_filter($link['linktriggers'],
					function ($link_trigger) use ($link_triggers_info, $options) {
						return (array_key_exists($link_trigger['triggerid'], $link_triggers_info)
							&& $link_triggers_info[$link_trigger['triggerid']]['status'] == TRIGGER_STATUS_ENABLED
							&& $link_triggers_info[$link_trigger['triggerid']]['value'] == TRIGGER_VALUE_TRUE
							&& $link_triggers_info[$link_trigger['triggerid']]['priority'] >= $options['severity_min']
						);
					}
				);

				// Link-trigger with highest severity or lower triggerid defines link color and drawtype.
				if ($link_triggers) {
					$link_triggers = array_map(function ($link_trigger) use ($link_triggers_info) {
						return [
							'priority' => $link_triggers_info[$link_trigger['triggerid']]['priority']
						] + $link_trigger;
					}, $link_triggers);

					CArrayHelper::sort($link_triggers, [
						['field' => 'priority', 'order' => ZBX_SORT_DOWN],
						['field' => 'triggerid', 'order' => ZBX_SORT_UP]
					]);

					$styling_link_triggers = reset($link_triggers);

					$link['color'] = $styling_link_triggers['color'];
					$link['drawtype'] = $styling_link_triggers['drawtype'];
				}
			}
		}
		unset($link);
	}

	/**
	 * Convert map shape to line (apply mapping to attribute set).
	 *
	 * @param array $shape				Map shape.
	 *
	 * @return array
	 */
	public static function convertShapeToLine($shape) {
		$mapping = [
			'sysmap_shapeid',
			'zindex',
			'x' => 'x1',
			'y' => 'y1',
			'width' => 'x2',
			'height' =>	'y2',
			'border_type' => 'line_type',
			'border_width' => 'line_width',
			'border_color' => 'line_color'
		];

		$line = [];

		foreach ($mapping as $source_key => $target_key) {
			$source_key = (is_numeric($source_key)) ? $target_key : $source_key;

			if (array_key_exists($source_key, $shape)) {
				$line[$target_key] = $shape[$source_key];
			}
		}

		return $line;
	}

	/**
	 * Convert map line to shape (apply mapping to attribute set).
	 *
	 * @param array $line				Map line.
	 *
	 * @return array
	 */
	public static function convertLineToShape($line) {
		$mapping = [
			'sysmap_shapeid',
			'zindex',
			'x1' => 'x',
			'y1' => 'y',
			'x2' => 'width',
			'y2' =>	'height',
			'line_type' => 'border_type',
			'line_width' => 'border_width',
			'line_color' => 'border_color'
		];

		$shape = [];

		foreach ($mapping as $source_key => $target_key) {
			$source_key = (is_numeric($source_key)) ? $target_key : $source_key;

			if (array_key_exists($source_key, $line)) {
				$shape[$target_key] = $line[$source_key];
			}
		}

		$shape['type'] = SYSMAP_SHAPE_TYPE_LINE;

		return $shape;
	}

	/**
	 * Checks that the user has read permissions to objects used in the map elements.
	 *
	 * @param array $selements    selements to check
	 *
	 * @return boolean
	 */
	public static function checkSelementPermissions(array $selements) {
		$groupids = [];
		$hostids = [];
		$triggerids = [];
		$sysmapids = [];

		foreach ($selements as $selement) {
			switch ($selement['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$groupids[$selement['elements'][0]['groupid']] = true;
					break;

				case SYSMAP_ELEMENT_TYPE_HOST:
					$hostids[$selement['elements'][0]['hostid']] = true;
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					foreach ($selement['elements'] as $element) {
						$triggerids[$element['triggerid']] = true;
					}
					break;

				case SYSMAP_ELEMENT_TYPE_MAP:
					$sysmapids[$selement['elements'][0]['sysmapid']] = true;
					break;
			}
		}

		return self::checkHostGroupsPermissions(array_keys($groupids))
				&& self::checkHostsPermissions(array_keys($hostids))
				&& self::checkTriggersPermissions(array_keys($triggerids))
				&& self::checkMapsPermissions(array_keys($sysmapids));
	}

	/**
	 * Checks if the current user has access to the given host groups.
	 *
	 * @param array $groupids
	 *
	 * @return boolean
	 */
	private static function checkHostGroupsPermissions(array $groupids) {
		if ($groupids) {
			$count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => $groupids
			]);

			return ($count == count($groupids));
		}

		return true;
	}

	/**
	 * Checks if the current user has access to the given hosts.
	 *
	 * @param array $hostids
	 *
	 * @return boolean
	 */
	private static function checkHostsPermissions(array $hostids) {
		if ($hostids) {
			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids
			]);

			return ($count == count($hostids));
		}

		return true;
	}

	/**
	 * Checks if the current user has access to the given triggers.
	 *
	 * @param array $triggerids
	 *
	 * @return boolean
	 */
	private static function checkTriggersPermissions(array $triggerids) {
		if ($triggerids) {
			$count = API::Trigger()->get([
				'countOutput' => true,
				'triggerids' => $triggerids
			]);

			return ($count == count($triggerids));
		}

		return true;
	}

	/**
	 * Checks if the current user has access to the given maps.
	 *
	 * @param array $sysmapids
	 *
	 * @return boolean
	 */
	private static function checkMapsPermissions(array $sysmapids) {
		if ($sysmapids) {
			$count = API::Map()->get([
				'countOutput' => true,
				'sysmapids' => $sysmapids
			]);

			return ($count == count($sysmapids));
		}

		return true;
	}

	/**
	 * Replace host groups of subtype = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP by hosts that belongs to group.
	 *
	 * @param array $sysmap  Map data.
	 * @param array $theme   Theme used to create missing elements (like hostgroup frame).
	 *
	 * @return array         Array representing areas with area coordinates and selementids.
	 */
	protected static function populateHostGroupsWithHosts(array &$sysmap, array $theme) {
		// Collect host groups to populate with hosts.
		$groupids = [];
		foreach ($sysmap['selements'] as &$selement) {
			$selement['selementid_orig'] = $selement['selementid'];
			$selement['elementtype_orig'] = $selement['elementtype'];
			$selement['elementsubtype_orig'] = $selement['elementsubtype'];

			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
					&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
				if ($selement['permission'] >= PERM_READ) {
					$groupids[$selement['elements'][0]['groupid']] = true;
				}
				else {
					// If user has no access to whole host group, always show it as a SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP.
					$selement['elementsubtype'] = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
				}
			}
		}
		unset($selement);

		$areas = [];

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => [],
				'selectHosts' => ['hostid', 'name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			]);

			$new_selementid = (count($sysmap['selements']) > 0)
				? (int) max(zbx_objectValues($sysmap['selements'], 'selementid'))
				: 0;

			$new_linkid = (count($sysmap['links']) > 0) ? (int) max(array_keys($sysmap['links'])) : 0;

			foreach ($sysmap['selements'] as $selement_key => &$selement) {
				if ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST_GROUP
						|| $selement['elementsubtype'] != SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
					continue;
				}

				$groupid = $selement['elements'][0]['groupid'];
				$group = $groups[$groupid];
				$original_selement = $selement;

				// Jump to the next host group if current host group doesn't contain accessible hosts.
				if (!$group['hosts']) {
					continue;
				}

				// Sort hosts by name.
				CArrayHelper::sort($group['hosts'], ['field' => 'name', 'order' => ZBX_SORT_UP]);

				// Define area in which to locate hosts.
				if ($selement['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
					$area = [
						'selementids' => [],
						'width' => $selement['width'],
						'height' => $selement['height'],
						'x' => $selement['x'],
						'y' => $selement['y']
					];

					$sysmap['shapes'][] = [
						'sysmap_shapeid' => 'e-'.$selement['selementid'],
						'type' => SYSMAP_SHAPE_TYPE_RECTANGLE,
						'x' => $selement['x'],
						'y' => $selement['y'],
						'width' => $selement['width'],
						'height' => $selement['height'],
						'border_type' => SYSMAP_SHAPE_BORDER_TYPE_SOLID,
						'border_width' => 3,
						'border_color' => $theme['maingridcolor'],
						'background_color' => '',
						'text' => '',
						'zindex' => -1
					];
				}
				else {
					$area = [
						'selementids' => [],
						'width' => $sysmap['width'],
						'height' => $sysmap['height'],
						'x' => 0,
						'y' => 0
					];
				}

				// Add selected hosts as map selements.
				foreach ($group['hosts'] as $host) {
					$new_selementid++;

					$area['selementids'][] = $new_selementid;
					$sysmap['selements'][$new_selementid] = [
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'elementsubtype' => SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP,
						'elements' => [
							['hostid' => $host['hostid']]
						],
						'selementid' => $new_selementid
					] + $selement;
				}

				$areas[] = $area;

				// Make links.
				$selements = zbx_toHash($sysmap['selements'], 'selementid');

				foreach ($sysmap['links'] as $link) {
					// Do not multiply links between two areas.
					$id1 = $link['selementid1'];
					$id2 = $link['selementid2'];

					if ($selements[$id1]['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
							&& $selements[$id1]['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
							&& $selements[$id2]['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
							&& $selements[$id2]['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
						continue;
					}

					if ($id1 == $original_selement['selementid']) {
						$id_number = 'selementid1';
					}
					elseif ($id2 == $original_selement['selementid']) {
						$id_number = 'selementid2';
					}
					else {
						continue;
					}

					foreach ($area['selementids'] as $selement_id) {
						$new_linkid++;
						$link['linkid'] = -$new_linkid;
						$link[$id_number] = $selement_id;
						$sysmap['links'][$new_linkid] = $link;
					}
				}
			}
		}

		// Remove host group elements that are replaced by hosts.
		$selements = [];
		foreach ($sysmap['selements'] as $key => $element) {
			if ($element['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST_GROUP
					|| $element['elementsubtype'] != SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
				$selements[$key] = $element;
			}
		}
		$sysmap['selements'] = $selements;

		return $areas;
	}
}

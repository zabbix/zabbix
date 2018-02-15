<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CMapHelper {

	/**
	 * Get map data with resolved element / link states.
	 *
	 * @param array $sysmapids					Map IDs.
	 * @param array $options					Options used to retrieve actions.
	 * @param int   $options['severity_min']	Minimum severity.
	 * @param int   $options['fullscreen']		Fullscreen flag.
	 *
	 * @return array
	 */
	public static function get($sysmapids, array $options = []) {
		$maps = API::Map()->get([
			'output' => ['sysmapid', 'name', 'width', 'height', 'backgroundid', 'label_type', 'label_location',
				'highlight', 'expandproblem', 'markelements', 'show_unack', 'label_format', 'label_type_host',
				'label_type_hostgroup', 'label_type_trigger', 'label_type_map', 'label_type_image', 'label_string_host',
				'label_string_hostgroup', 'label_string_trigger', 'label_string_map', 'label_string_image', 'iconmapid',
				'severity_min'
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
				'width', 'height', 'viewtype', 'use_iconmap', 'application', 'urls', 'permission'
			],
			'selectLinks' => ['linkid', 'selementid1', 'selementid2', 'drawtype', 'color', 'label', 'linktriggers',
				'permission'],
			'selectUrls' => ['sysmapurlid', 'name', 'url'],
			'sysmapids' => $sysmapids,
			'expandUrls' => true,
			'nopermissions' => true,
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

			self::resolveMapState($map, $options, $theme);
		}

		return [
			'id' => $map['sysmapid'],
			'theme' => $theme,
			'canvas' => [
				'width' => $map['width'],
				'height' => $map['height'],
			],
			'refresh' => 'map.php?sysmapid='.$map['sysmapid'].'&severity_min='.$map['severity_min'],
			'background' => $map['backgroundid'],
			'label_location' => $map['label_location'],
			'shapes' => array_values($map['shapes']),
			'elements' => array_values($map['selements']),
			'links' => array_values($map['links']),
			'shapes' => array_values($map['shapes']),
			'aria_label' => $map['aria_label'],
			'timestamp' => zbx_date2str(DATE_TIME_FORMAT_SECONDS)
		];
	}

	/**
	 * Resolve map element (selements and links) state.
	 *
	 * @param array $sysmap				Map data.
	 * @param array $options					Options used to retrieve actions.
	 * @param int   $options['severity_min']	Minimum severity.
	 * @param int   $options['fullscreen']		Fullscreen flag.
	 * @param int   $theme				Theme used to create missing elements (like hostgroup frame).
	 */
	protected static function resolveMapState(&$sysmap, $options, $theme) {
		$map_info_options = [
			'severity_min' => array_key_exists('severity_min', $options) ? $options['severity_min'] : null
		];

		foreach ($sysmap['selements'] as &$selement) {
			// If user has no access to whole host group, always show it as a SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP.
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP && $selement['permission'] < PERM_READ
					&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
				$selement['elementsubtype'] = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
			}
		}
		unset($selement);

		$areas = populateFromMapAreas($sysmap, $theme);
		$map_info = getSelementsInfo($sysmap, $map_info_options);
		processAreasCoordinates($sysmap, $areas, $map_info);
		// Adding element names and removing inaccessible triggers from readable elements.
		add_elementNames($sysmap['selements']);

		foreach ($sysmap['selements'] as $id => &$element) {
			if ($element['permission'] < PERM_READ) {
				continue;
			}

			switch ($element['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_IMAGE:
					$map_info[$id]['name'] = _('Image');
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					// Move the trigger with problem and highiest priority to the beginning of the trigger list.
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
		$linktrigger_info = getMapLinktriggerInfo($sysmap, $options);

		$problems_total = 0;
		$status_problems = [];
		$status_other = [];

		foreach ($sysmap['selements'] as $id => &$element) {
			$icon = null;

			if (array_key_exists($id, $map_info) && array_key_exists('iconid', $map_info[$id])) {
				$icon = $map_info[$id]['iconid'];
			}

			unset($element['width'], $element['height']);

			$element['icon'] = $icon;
			if ($element['permission'] >= PERM_READ) {
				$label = str_replace(['.', ','], ' ', CMacrosResolverHelper::resolveMapLabelMacrosAll($element));

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

		foreach ($sysmap['shapes'] as &$shape) {
			if (array_key_exists('text', $shape)) {
				$shape['text'] = CMacrosResolverHelper::resolveMapLabelMacros($shape['text']);
				$shape['text'] = str_replace('{MAP.NAME}', $sysmap['name'], $shape['text']);
			}
		}
		unset($shape);

		foreach ($sysmap['lines'] as $line) {
			$sysmap['shapes'][] = self::convertLineToShape($line);
		}

		foreach ($sysmap['links'] as &$link) {
			if ($link['permission'] >= PERM_READ) {
				$link['label'] = CMacrosResolverHelper::resolveMapLabelMacros($link['label']);

				if (empty($link['linktriggers'])) {
					continue;
				}

				$drawtype = $link['drawtype'];
				$color = $link['color'];
				$linktriggers = $link['linktriggers'];
				order_result($linktriggers, 'triggerid');
				$max_severity = 0;
				$triggers = [];

				foreach ($linktriggers as $link_trigger) {
					if ($link_trigger['triggerid'] == 0
							|| !array_key_exists($link_trigger['triggerid'], $linktrigger_info)) {
						continue;
					}

					$id = $link_trigger['linktriggerid'];

					$triggers[$id] = zbx_array_merge($link_trigger, $linktrigger_info[$link_trigger['triggerid']]);

					if ($triggers[$id]['status'] == TRIGGER_STATUS_ENABLED && $triggers[$id]['value'] == TRIGGER_VALUE_TRUE
							&& $triggers[$id]['priority'] >= $max_severity) {
						$drawtype = $triggers[$id]['drawtype'];
						$color = $triggers[$id]['color'];
						$max_severity = $triggers[$id]['priority'];
					}
				}

				$link['color'] = $color;
				$link['drawtype'] = $drawtype;
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
	 * Set labels inherited from map configuration data.
	 *
	 * @param array $map
	 *
	 * @return array map with inherited labels set for elements.
	 */
	public static function setElementInheritedLabels(array $map) {
		foreach ($map['selements'] as &$selement) {
			$selement['inherited_label'] = null;
			$selement['label_type'] = $map['label_type'];

			if ($map['label_format'] != SYSMAP_LABEL_ADVANCED_OFF) {
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$selement['label_type'] = $map['label_type_hostgroup'];
						if ($map['label_type_hostgroup'] == MAP_LABEL_TYPE_CUSTOM) {
							$selement['inherited_label'] = $map['label_string_hostgroup'];
						}
						break;

					case SYSMAP_ELEMENT_TYPE_HOST:
						$selement['label_type'] = $map['label_type_host'];
						if ($map['label_type_host'] == MAP_LABEL_TYPE_CUSTOM) {
							$selement['inherited_label'] = $map['label_string_host'];
						}
						break;

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$selement['label_type'] = $map['label_type_trigger'];
						if ($map['label_type_trigger'] == MAP_LABEL_TYPE_CUSTOM) {
							$selement['inherited_label'] = $map['label_string_trigger'];
						}
						break;

					case SYSMAP_ELEMENT_TYPE_MAP:
						$selement['label_type'] = $map['label_type_map'];
						if ($map['label_type_map'] == MAP_LABEL_TYPE_CUSTOM) {
							$selement['inherited_label'] = $map['label_string_map'];
						}
						break;

					case SYSMAP_ELEMENT_TYPE_IMAGE:
						$selement['label_type'] = $map['label_type_image'];
						if ($map['label_type_image'] == MAP_LABEL_TYPE_CUSTOM) {
							$selement['inherited_label'] = $map['label_string_image'];
						}
						break;
				}
			}

			if ($selement['label_type'] == MAP_LABEL_TYPE_NOTHING) {
				$selement['inherited_label'] = '';
			}
			elseif ($selement['label_type'] == MAP_LABEL_TYPE_IP
					&& $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
				$selement['inherited_label'] = '{HOST.IP}';
			}
		}
		unset($selement);

		return $map;
	}
}

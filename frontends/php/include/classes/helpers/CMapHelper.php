<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
				]]
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
			'timestamp' => zbx_date2str(DATE_TIME_FORMAT_SECONDS),
			'homepage' => ZABBIX_HOMEPAGE
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

		if ($sysmap['selements']) {
			foreach ($sysmap['selements'] as &$selement) {
				// If user has no access to whole host group, always show it as a SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP.
				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP && $selement['permission'] < PERM_READ
						&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
					$selement['elementsubtype'] = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
				}
			}
			unset($selement);
		}

		$areas = populateFromMapAreas($sysmap, $theme);
		$map_info = getSelementsInfo($sysmap, $map_info_options);
		processAreasCoordinates($sysmap, $areas, $map_info);
		add_elementNames($sysmap['selements']);

		foreach ($sysmap['selements'] as $id => $element) {
			switch ($element['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_IMAGE:
					$map_info[$id]['name'] = _('Image');
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					// Skip inaccessible elements.
					$selements_accessible = array_filter($element['elements'], function($elmn) {
						return array_key_exists('elementName', $elmn);
					});
					if (($selements_accessible = reset($selements_accessible)) !== false) {
						$map_info[$id]['name'] = $selements_accessible['elementName'];
					} else {
						$map_info[$id]['name'] = '';
					}
					break;

				default:
					$map_info[$id]['name'] = $element['elements'][0]['elementName'];
					break;
			}
		}

		$labels = getMapLabels($sysmap, $map_info, true);
		$highlights = getMapHighligts($sysmap, $map_info);
		$actions = getActionsBySysmap($sysmap, $options);
		$linktrigger_info = getMapLinktriggerInfo($sysmap, $options);

		foreach ($sysmap['selements'] as $id => &$element) {
			$icon = null;

			if (array_key_exists($id, $map_info) && array_key_exists('iconid', $map_info[$id])) {
				$icon = $map_info[$id]['iconid'];
			}

			unset($element['width'], $element['height']);

			$element['icon'] = $icon;
			if ($element['permission'] >= PERM_READ) {
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

		foreach ($sysmap['shapes'] as &$shape) {
			if (array_key_exists('text', $shape)) {
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
}

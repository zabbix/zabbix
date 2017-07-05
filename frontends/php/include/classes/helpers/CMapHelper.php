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
	 * @param array $sysmapids				Map IDs.
	 * @param int   $min_severity			Minimum severity.
	 *
	 * @return array
	 */
	public static function get($sysmapids, $min_severity = null) {
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
				'width', 'height', 'viewtype', 'use_iconmap', 'application', 'urls'
			],
			'selectLinks' => ['linkid', 'selementid1', 'selementid2', 'drawtype', 'color', 'label', 'linktriggers'],
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
			if ($min_severity !== null) {
				$map['severity_min'] = $min_severity;
			}

			self::resolveMapState($map, $map['severity_min'], $theme);
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
	 * @param int   $min_severity		Minimum severity.
	 * @param int   $theme				Theme used to create missing elements (like hostgroup frame).
	 */
	protected static function resolveMapState(&$sysmap, $min_severity, $theme) {
		$severity = ['severity_min' => $min_severity];
		$areas = populateFromMapAreas($sysmap, $theme);
		$map_info = getSelementsInfo($sysmap, $severity);
		processAreasCoordinates($sysmap, $areas, $map_info);
		add_elementNames($sysmap['selements']);

		foreach ($sysmap['selements'] as $id => $element) {
			$map_info[$id]['name'] = ($element['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE)
				? _('Image')
				: $element['elements'][0]['elementName'];
		}

		$labels = getMapLabels($sysmap, $map_info, true);
		$highlights = getMapHighligts($sysmap, $map_info);
		$actions = getActionsBySysmap($sysmap, $severity);

		foreach ($sysmap['selements'] as $id => &$element) {
			$icon = null;

			if (array_key_exists($id, $map_info) && array_key_exists('iconid', $map_info[$id])) {
				$icon = $map_info[$id]['iconid'];
			}

			unset($element['width'], $element['height']);

			$element['icon'] = $icon;
			$element['label'] = $labels[$id];
			$element['highlight'] = $highlights[$id];
			$element['actions'] = $actions[$id];

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
				if ($link_trigger['triggerid'] == 0) {
					continue;
				}

				$id = $link_trigger['linktriggerid'];

				$triggers[$id] = zbx_array_merge($link_trigger, get_trigger_by_triggerid($link_trigger['triggerid']));

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

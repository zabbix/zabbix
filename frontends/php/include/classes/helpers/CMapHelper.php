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
	public static function get($ids, $minSeverity = null) {
		$maps = API::Map()->get([
			'sysmapids' => $ids,
			'output' => API_OUTPUT_EXTEND,
			'selectShapes' => API_OUTPUT_EXTEND,
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectLinks' => API_OUTPUT_EXTEND,
			'expandUrls' => true,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		$map = reset($maps);
		if (empty($map)) {
			access_deny();
		}

		if (!is_null($minSeverity)) {
			$map['severity_min'] = $minSeverity;
		}

		$theme = self::getGraphTheme();
		self::resolveMapState($map, $map['severity_min'], $theme);

		return [
			'id' => $map['sysmapid'],
			'theme' => $theme,
			'canvas' => [
				'width' => $map['width'],
				'height' => $map['height'],
			],
			'refresh' => "map.php?sysmapid={$map['sysmapid']}&severity_min={$map['severity_min']}",
			'background' => $map['backgroundid'],
			'shapes' => array_values($map['shapes']),
			'elements' => array_values($map['selements']),
			'links' => array_values($map['links']),
			'shapes' => array_values($map['shapes']),
			'timestamp' => zbx_date2str(DATE_TIME_FORMAT_SECONDS),
			'homepage' => ZABBIX_HOMEPAGE
		];
	}

	protected static function getGraphTheme() {
		$themes = DB::find('graph_theme', [
			'theme' => getUserTheme(CWebUser::$data)
		]);
		if ($themes) {
			return $themes[0];
		}

		return [
			'theme' => 'blue-theme',
			'textcolor' => '1F2C33',
			'highlightcolor' => 'E33734',
			'backgroundcolor' => 'FFFFFF',
			'graphcolor' => 'FFFFFF',
			'gridcolor' => 'CCD5D9',
			'maingridcolor' => 'ACBBC2',
			'gridbordercolor' => 'ACBBC2',
			'nonworktimecolor' => 'EBEBEB',
			'leftpercentilecolor' => '429E47',
			'righttpercentilecolor' => 'E33734'
		];
	}

	/* TODO: refactor */
	protected static function resolveMapState(&$sysmap, $minSeverity, $theme) {
		$severity = ['severity_min' => $minSeverity];
		$areas = populateFromMapAreas($sysmap, $theme);
		$mapInfo = getSelementsInfo($sysmap, $severity);
		processAreasCoordinates($sysmap, $areas, $mapInfo);

		add_elementNames($sysmap['selements']);
		foreach ($sysmap['selements'] as $id => $element) {
			$mapInfo[$id]['name'] = ($element['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE)
				? _('Image')
				: $element['elementName'];
		}

		$labels = getMapLabels($sysmap, $mapInfo, true);
		$highlights = getMapHighligts($sysmap, $mapInfo);
		$actions = getActionsBySysmap($sysmap, $severity);

		foreach ($sysmap['selements'] as $id => &$element) {
			$icon = null;
			if (array_key_exists($id, $mapInfo) && array_key_exists('iconid', $mapInfo[$id])) {
				$icon = $mapInfo[$id]['iconid'];
			}

			unset($element['width']);
			unset($element['height']);

			$element['icon'] = $icon;
			$element['label'] = $labels[$id];
			$element['highlight'] = $highlights[$id];
			$element['actions'] = $actions[$id];
			if ($sysmap['markelements']) {
				$element['latelyChanged'] = $mapInfo[$id]['latelyChanged'];
			}
		}
		unset($element);

		foreach ($sysmap['shapes'] as &$shape) {
			if (array_key_exists('text', $shape)) {
				$shape['text'] = str_replace('{MAP.NAME}', $sysmap['name'], $shape['text']);
			}
		}
		unset($shape);

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
				if ($triggers[$id]['status'] == TRIGGER_STATUS_ENABLED && $triggers[$id]['value'] == TRIGGER_VALUE_TRUE) {
					if ($triggers[$id]['priority'] >= $max_severity) {
						$drawtype = $triggers[$id]['drawtype'];
						$color = $triggers[$id]['color'];
						$max_severity = $triggers[$id]['priority'];
					}
				}
			}

			$link['color'] = $color;
			$link['drawtype'] = $drawtype;
		}
		unset($link);
	}
}

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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

$page['title'] = _('Map');
$page['file'] = 'map.php';
$page['type'] = PAGE_TYPE_JSON;

require_once dirname(__FILE__).'/include/page_header.php';

$severity_min = getRequest('severity_min');
if (!zbx_ctype_digit($severity_min)) {
	$severity_min = null;
}
$map_data = CMapHelper::get(getRequest('sysmapid'), ['severity_min' => $severity_min]);

if (getRequest('used_in_widget', 0) && hasRequest('uniqueid')) {
	$uniqueid = getRequest('uniqueid');

	// Rewrite actions to force Submaps be opened in same widget, instead of separate window.
	foreach ($map_data['elements'] as &$element) {
		$actions = CJs::decodeJson($element['actions']);
		if ($actions && array_key_exists('gotos', $actions) && array_key_exists('submap', $actions['gotos'])) {
			$actions['navigatetos']['submap'] = $actions['gotos']['submap'];
			$actions['navigatetos']['submap']['widget_uniqueid'] = $uniqueid;
			unset($actions['gotos']['submap']);
		}

		$element['actions'] = CJs::encodeJson($actions);
	}
	unset($element);
}

// No need to get all data.
$options = [
	'mapid' => $map_data['id'],
	'canvas' => $map_data['canvas'],
	'background' => $map_data['background'],
	'elements' => $map_data['elements'],
	'links' => $map_data['links'],
	'shapes' => $map_data['shapes'],
	'aria_label' => $map_data['aria_label'],
	'label_location' => $map_data['label_location'],
	'timestamp' => $map_data['timestamp']
];

if (getRequest('used_in_widget', 0)) {
	$options['map_widget_footer'] = (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString();
}

if ($map_data['id'] == -1) {
	$options['timestamp'] = null;
}

echo CJs::encodeJson($options);

require_once dirname(__FILE__).'/include/page_footer.php';

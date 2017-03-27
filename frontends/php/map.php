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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

$page['title'] = _('Map');
$page['file'] = 'map.php';
$page['type'] = PAGE_TYPE_JSON;

require_once dirname(__FILE__).'/include/page_header.php';

$map_data = CMapHelper::get(getRequest('sysmapid'), getRequest('severity_min'));

/* no need to get all data */
$options = [
	'canvas' => $map_data['canvas'],
	'background' => $map_data['background'],
	'elements' => $map_data['elements'],
	'links' => $map_data['links'],
	'shapes' => $map_data['shapes'],
	'timestamp' => $map_data['timestamp']
];

echo CJs::encodeJson($options);

require_once dirname(__FILE__).'/include/page_footer.php';

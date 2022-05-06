<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

$item = new CNavigationTree([
	'problems' => $data['problems'],
	'severity_config' => $data['severity_config'],
	'initial_load' => $data['initial_load'],
	'maps_accessible' => $data['maps_accessible'],
	'navtree' => $data['navtree'],
	'navtree_item_selected' => $data['navtree_item_selected'],
	'navtree_items_opened' => $data['navtree_items_opened'],
	'show_unavailable' => $data['show_unavailable']
]);

if ($data['error'] !== null) {
	$item->setError($data['error']);
}

$output = [
	'name' => $data['name'],
	'body' => $item->toString(),
	'navtree_data' => $item->getScriptData()
];

if ($messages = get_and_clear_messages()) {
	$output['messages'] = array_column($messages, 'message');
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

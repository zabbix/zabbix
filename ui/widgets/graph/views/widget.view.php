<?php declare(strict_types = 0);
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
 * Graph (classic) widget view.
 *
 * @var CView $this
 * @var array $data
 */

$output = [
	'name' => $data['name']
];

if ($data['is_resource_available']) {
	$link_url = $data['widget']['graph_url'] ?? 'javascript:void(0)';

	$output['body'] = (new CDiv())
		->addClass('flickerfreescreen')
		->addItem((new CLink(null, $link_url))->addClass(ZBX_STYLE_DASHBOARD_WIDGET_GRAPH_LINK))
		->toString();

	$output['async_data'] = $data['widget'];
}
else {
	$output['body'] = (new CTableInfo())
		->setNoDataMessage(_('No permissions to referred object or it does not exist!'))
		->toString();
}

if ($messages = get_and_clear_messages()) {
	$output['messages'] = array_column($messages, 'message');
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output, JSON_THROW_ON_ERROR);

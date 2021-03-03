<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$output = [
	'header' => $data['name']
];

if ($data['is_resource_available']) {
	$output['body'] = (new CDiv())
		->addClass('flickerfreescreen')
		->addItem(($data['widget']['graph_url'] !== null)
			? (new CLink(null, $data['widget']['graph_url']))->addClass(ZBX_STYLE_DASHBRD_WIDGET_GRAPH_LINK)
			: (new CSpan())->addClass(ZBX_STYLE_DASHBRD_WIDGET_GRAPH_LINK)
		)
		->toString();

	$output['scripts'] = $this->readJsFile('monitoring.widget.graph.view.js.php');
	$output['scripts_data'] = [
		'graph_url' => $data['widget']['graph_url'],
		'time_control_data' => $data['widget']['time_control_data'],
		'flickerfreescreen_data' => $data['widget']['flickerfreescreen_data']
	];
}
else {
	$output['body'] = (new CTableInfo())
		->setNoDataMessage(_('No permissions to referred object or it does not exist!'))
		->toString();
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

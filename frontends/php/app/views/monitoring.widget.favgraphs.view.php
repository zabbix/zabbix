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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$table = (new CTableInfo())->setNoDataMessage(_('No graphs added.'));

foreach ($data['graphs'] as $graph) {
	$url = $graph['simple']
		? (new CUrl('history.php'))
			->setArgument('action', HISTORY_GRAPH)
			->setArgument('itemids', [$graph['itemid']])
		: (new CUrl('charts.php'))->setArgument('graphid', $graph['graphid']);
	$on_click = $graph['simple']
		? "rm4favorites('itemid','".$graph['itemid']."')"
		: "rm4favorites('graphid','".$graph['graphid']."')";

	$table->addRow([
		new CLink($graph['label'], $url->setArgument('fullscreen', $data['fullscreen'] ? '1' : null)),
		(new CButton())
			->onClick($on_click)
			->addClass(ZBX_STYLE_REMOVE_BTN)
			->setAttribute('aria-label', _x('Remove, %1$s', ZBX_CONTEXT_SCREEN_READER, $graph['label']))
	]);
}

$output = [
	'header' => $data['name'],
	'body' => $table->toString(),
	'footer' => (new CList([
		_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))
	]))->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);

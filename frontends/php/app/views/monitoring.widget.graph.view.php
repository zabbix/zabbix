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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

$item = ($data['is_default'])
	? (new CLink(null, 'charts.php?graphid='.$data['graph']['graphid'].'&period='.$data['timeline']['period'].
		'&stime='.$data['timeline']['stimeNow']))
	: (new CDiv());
$item->setId($data['graph']['containerid']);

$flickerfree_item = (new CDiv($item))
	->addClass('flickerfreescreen')
	->setAttribute('data-timestamp', $data['graph']['timestamp'])
	->setId('flickerfreescreen_'.$data['graph']['dataid']);

$script = 'timeControl.addObject("'.$data['graph']['dataid'].'", '.CJs::encodeJson($data['timeline']).', '
		.CJs::encodeJson($data['time_control_data']).');'
	. 'timeControl.processObjects();'
	. 'window.flickerfreeScreen.add('.zbx_jsvalue($data['fs_data']).');';

$output = [
	'header' => $data['name'],
	'body' => $flickerfree_item->toString(),
	'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString(),
	'script_inline' => $script
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);

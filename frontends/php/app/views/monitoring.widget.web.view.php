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


// indicator of sort field
$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);

$table = (new CTableInfo())->setHeader([[_('Host group'), $sort_div], _('Ok'), _('Failed'), _('Unknown')]);

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'web.view')
	->setArgument('groupid', '')
	->setArgument('hostid', '0')
	->setArgument('fullscreen', $data['fullscreen'] ? '1' : null);

foreach ($data['groups'] as $group) {
	$url->setArgument('groupid', $group['groupid']);

	$table->addRow([
		new CLink($group['name'], $url->getUrl()),
		($group['ok'] != 0) ? (new CSpan($group['ok']))->addClass(ZBX_STYLE_GREEN) : '',
		($group['failed'] != 0) ? (new CSpan($group['failed']))->addClass(ZBX_STYLE_RED) : '',
		($group['unknown'] != 0) ? (new CSpan($group['unknown']))->addClass(ZBX_STYLE_GREY) : ''
	]);
}

$output = [
	'header' => $data['name'],
	'body' => $table->toString(),
	'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);

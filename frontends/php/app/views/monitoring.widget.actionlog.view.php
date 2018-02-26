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
$sort_div = (new CSpan())
	->addClass(($data['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

// create alert table
$table = (new CTableInfo())
	->setHeader([
		($data['sortfield'] === 'clock') ? [_('Time'), $sort_div] : _('Time'),
		_('Action'),
		($data['sortfield'] === 'description') ? [_('Type'), $sort_div] : _('Type'),
		($data['sortfield'] === 'sendto') ? [_('Recipient'), $sort_div] : _('Recipient'),
		_('Message'),
		($data['sortfield'] === 'status') ? [_('Status'), $sort_div] : _('Status'),
		_('Info')
	]);

foreach ($data['alerts'] as $alert) {
	if ($alert['status'] == ALERT_STATUS_SENT) {
		$status = (new CSpan(_('Sent')))->addClass(ZBX_STYLE_GREEN);
	}
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW) {
		$status = (new CSpan([
			_('In progress').':',
			BR(),
			_n('%1$s retry left', '%1$s retries left', $alert['maxattempts'] - $alert['retries'])])
		)
			->addClass(ZBX_STYLE_YELLOW);
	}
	else {
		$status = (new CSpan(_('Failed')))->addClass(ZBX_STYLE_RED);
	}

	$recipient = ($alert['userid'] != 0 && array_key_exists($alert['userid'], $data['db_users']))
		? [bold(getUserFullname($data['db_users'][$alert['userid']])), BR(), zbx_nl2br($alert['sendto'])]
		: zbx_nl2br($alert['sendto']);

	$info_icons = [];
	if ($alert['error'] !== '') {
		$info_icons[] = makeErrorIcon($alert['error']);
	}

	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
		array_key_exists($alert['actionid'], $data['actions']) ? $data['actions'][$alert['actionid']]['name'] : '',
		$alert['mediatypeid'] == 0 ? '' : $alert['description'],
		$recipient,
		[bold($alert['subject']), BR(), BR(), zbx_nl2br($alert['message'])],
		$status,
		makeInformationList($info_icons)
	]);
}

$footer = (new CList())
	->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
	->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

$output = [
	'header' => $data['name'],
	'body' => $table->toString(),
	'footer' => (new CList())
		->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
		->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT)
		->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);

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

// indicator of sort field
$sort_div = (new CSpan())
	->addClass(($data['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

// create alert table
$table = (new CTableInfo())
	->setHeader([
		($data['sortfield'] === 'clock') ? [_('Time'), $sort_div] : _('Time'),
		_('Action'),
		($data['sortfield'] === 'mediatypeid') ? [_('Type'), $sort_div] : _('Type'),
		($data['sortfield'] === 'sendto') ? [_('Recipient'), $sort_div] : _('Recipient'),
		_('Message'),
		($data['sortfield'] === 'status') ? [_('Status'), $sort_div] : _('Status'),
		_('Info')
	]);

foreach ($data['alerts'] as $alert) {
	if ($alert['alerttype'] == ALERT_TYPE_MESSAGE && array_key_exists('maxattempts', $alert)
			&& ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW)) {
		$info_icons = makeWarningIcon(_n('%1$s retry left', '%1$s retries left',
			$alert['maxattempts'] - $alert['retries'])
		);
	}
	elseif ($alert['error'] !== '') {
		$info_icons = makeErrorIcon($alert['error']);
	}
	else {
		$info_icons = null;
	}

	$message = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
		? [
			bold($alert['subject']),
			BR(),
			BR(),
			zbx_nl2br($alert['message'])
		]
		: [
			zbx_nl2br($alert['message'])
		];

	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
		array_key_exists($alert['actionid'], $data['actions']) ? $data['actions'][$alert['actionid']]['name'] : '',
		$alert['description'],
		makeEventDetailsTableUser($alert, $data['db_users']),
		$message,
		makeActionTableStatus($alert),
		makeInformationList($info_icons)
	]);
}

$output = [
	'name' => $data['name'],
	'body' => $table->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

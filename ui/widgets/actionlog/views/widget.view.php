<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Action log widget view.
 *
 * @var CView $this
 * @var array $data
 */

// indicator of sort field
$sort_div = (new CSpan())->addClass($data['sortorder'] === ZBX_SORT_DOWN ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

// create alert table
$table = (new CTableInfo())
	->setHeader([
		($data['sortfield'] === 'clock')
			? [_x('Time', 'compact table header'), $sort_div]
			: _x('Time', 'compact table header'),
		_x('Action', 'compact table header'),
		($data['sortfield'] === 'mediatypeid')
			? [_x('Media type', 'compact table header'), $sort_div]
			: _x('Media type', 'compact table header'),
		($data['sortfield'] === 'sendto')
			? [_x('Recipient', 'compact table header'), $sort_div]
			: _x('Recipient', 'compact table header'),
		_x('Message', 'compact table header'),
		($data['sortfield'] === 'status')
			? [_x('Status', 'compact table header'), $sort_div]
			: _x('Status', 'compact table header'),
		_x('Info', 'compact table header')
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
		$info_icons = [];
	}

	$message = $alert['alerttype'] == ALERT_TYPE_MESSAGE
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

$view = new CWidgetView($data);

if ($data['info']) {
	$view->setVar('info', $data['info']);
}

$view
	->addItem($table)
	->show();

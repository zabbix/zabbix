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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var array $data
 */

/*
 * Search limit performs +1 selection to know if limit was exceeded, this will assure that csv has
 * "search_limit" records at most.
 */

array_splice($data['alerts'], CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT));

$csv = [];

$csv[] = array_filter([
	_('Time'),
	_('Action'),
	_('Media type'),
	_('Recipient'),
	_('Message'),
	_('Status'),
	_('Info')
]);

foreach ($data['alerts'] as $alert) {
	$row = [];

	$mediatype = array_pop($alert['mediatypes']);

	$recipients_full_name = (isset($alert['userid']) && $alert['userid'])
		? getUserFullname($data['users'][$alert['userid']])
		: '';

	$recipient = (isset($alert['userid']) && $alert['userid'])
		? $recipients_full_name.PHP_EOL.$alert['sendto']
		: $alert['sendto'];

	$message = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
		? _('Subject').':'.PHP_EOL.$alert['subject'].PHP_EOL._('Message').':'.PHP_EOL.$alert['message']
		: _('Command').':'.PHP_EOL.$alert['message'];

	if ($alert['status'] == ALERT_STATUS_SENT) {
		$status = ($alert['alerttype'] == ALERT_TYPE_MESSAGE) ? _('Sent') : _('Executed');
	}
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW) {
		$status = _('In progress').': '
			._n('%1$s retry left', '%1$s retries left', $mediatype['maxattempts'] - $alert['retries']);
	}
	else {
		$status = _('Failed');
	}

	$row[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']);
	$row[] = $data['actions'][$alert['actionid']]['name'];
	$row[] = $mediatype ? $mediatype['name'] : '';
	$row[] = $recipient;
	$row[] = $message;
	$row[] = $status;
	$row[] = $alert['error'];

	$csv[] = $row;
}

echo zbx_toCSV($csv);

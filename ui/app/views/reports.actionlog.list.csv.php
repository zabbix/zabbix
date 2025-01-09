<?php
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
	_("Recipient's Zabbix username"),
	_("Recipient's name"),
	_("Recipient's surname"),
	_('Recipient'),
	_('Subject'),
	_('Message'),
	_('Command'),
	_('Status'),
	_('Info')
]);

foreach ($data['alerts'] as $alert) {
	$row = [];

	$mediatype = array_pop($alert['mediatypes']);

	$recipients_username = '';
	$recipients_name = '';
	$recipients_surname = '';

	if (isset($alert['userid']) && $alert['userid']) {
		$recipients_username = $data['users'][$alert['userid']]['username'];
		$recipients_name = $data['users'][$alert['userid']]['name'];
		$recipients_surname = $data['users'][$alert['userid']]['surname'];
	}

	if ($alert['status'] == ALERT_STATUS_SENT) {
		$status = ($alert['alerttype'] == ALERT_TYPE_MESSAGE) ? _('Sent') : _('Executed');
	}
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW) {
		$status = $alert['alerttype'] == ALERT_TYPE_MESSAGE
			? _('In progress').': '.
				_n('%1$s retry left', '%1$s retries left', $mediatype['maxattempts'] - $alert['retries'])
			: _('In progress');
	}
	else {
		$status = _('Failed');
	}

	$row[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']);
	$row[] = $data['actions'][$alert['actionid']]['name'];
	$row[] = $mediatype ? $mediatype['name'] : '';
	$row[] = $recipients_username;
	$row[] = $recipients_name;
	$row[] = $recipients_surname;
	$row[] = $alert['sendto'];
	$row[] = ($alert['alerttype'] == ALERT_TYPE_MESSAGE) ? $alert['subject'] : '';
	$row[] = ($alert['alerttype'] == ALERT_TYPE_MESSAGE) ? $alert['message'] : '';
	$row[] = ($alert['alerttype'] == ALERT_TYPE_COMMAND) ? $alert['message'] : '';
	$row[] = $status;
	$row[] = $alert['error'];

	$csv[] = $row;
}

echo zbx_toCSV($csv);

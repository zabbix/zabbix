<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

array_splice($data['auditlogs'], CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT));

$csv = [];

$csv[] = array_filter([
	_('Time'),
	_('User'),
	_('IP'),
	_('Resource'),
	_('ID'),
	_('Action'),
	_('Recordset ID'),
	_('Details')
]);

foreach ($data['auditlogs'] as $auditlog) {
	$row = [];

	$row[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $auditlog['clock']);
	$row[] = in_array($auditlog['userid'], $data['non_existent_userids'])
		? $auditlog['username']
		: $data['users'][$auditlog['userid']];
	$row[] = $auditlog['ip'];
	$row[] = array_key_exists($auditlog['resourcetype'], $data['resources'])
		? $data['resources'][$auditlog['resourcetype']]
		: _('Unknown resource');
	$row[] = $auditlog['resourceid'];
	$row[] = array_key_exists($auditlog['action'], $data['actions'])
		? $data['actions'][$auditlog['action']]
		: _('Unknown action');
	$row[] = $auditlog['recordsetid'];
	$row[] = $auditlog['full_details'];

	$csv[] = $row;
}

echo zbx_toCSV($csv);

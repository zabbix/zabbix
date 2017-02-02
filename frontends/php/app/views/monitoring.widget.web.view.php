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


$groups = API::HostGroup()->get([
	'output' => ['groupid', 'name'],
	'groupids' => $data['filter']['groupids'],
	'hostids' => isset($data['filter']['hostids']) ? $data['filter']['hostids'] : null,
	'monitored_hosts' => true,
	'with_monitored_httptests' => true,
	'preservekeys' => true
]);
CArrayHelper::sort($groups, ['name']);

$groupIds = array_keys($groups);

$availableHosts = API::Host()->get([
	'output' => ['hostid'],
	'groupids' => $groupIds,
	'hostids' => isset($data['filter']['hostids']) ? $data['filter']['hostids'] : null,
	'filter' => ['maintenance_status' => $data['filter']['maintenance']],
	'monitored_hosts' => true,
	'preservekeys' => true
]);
$availableHostIds = array_keys($availableHosts);

$table_data = [];

// fetch links between HTTP tests and host groups
$result = DbFetchArray(DBselect(
	'SELECT DISTINCT ht.httptestid,hg.groupid'.
	' FROM httptest ht,hosts_groups hg'.
	' WHERE ht.hostid=hg.hostid'.
		' AND '.dbConditionInt('hg.hostid', $availableHostIds).
		' AND '.dbConditionInt('hg.groupid', $groupIds).
		' AND ht.status='.HTTPTEST_STATUS_ACTIVE
));

// fetch HTTP test execution data
$httpTestData = Manager::HttpTest()->getLastData(zbx_objectValues($result, 'httptestid'));

foreach ($result as $row) {
	if (!array_key_exists($row['groupid'], $table_data)) {
		$table_data[$row['groupid']] = [
			'ok' => 0,
			'failed' => 0,
			'unknown' => 0
		];
	}

	if (isset($httpTestData[$row['httptestid']]) && $httpTestData[$row['httptestid']]['lastfailedstep'] !== null) {
		$table_data[$row['groupid']][$httpTestData[$row['httptestid']]['lastfailedstep'] != 0 ? 'failed' : 'ok']++;
	}
	else {
		$table_data[$row['groupid']]['unknown']++;
	}
}

$table = (new CTableInfo())->setHeader([_('Host group'), _('Ok'), _('Failed'), _('Unknown')]);

foreach ($groups as $group) {
	if (array_key_exists($group['groupid'], $table_data)) {
		$table->addRow([
			new CLink($group['name'], 'zabbix.php?action=web.view&groupid='.$group['groupid'].'&hostid=0'),
			(new CSpan($table_data[$group['groupid']]['ok']))->addClass(ZBX_STYLE_GREEN),
			(new CSpan($table_data[$group['groupid']]['failed']))
				->addClass($table_data[$group['groupid']]['failed'] == 0 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED),
			(new CSpan($table_data[$group['groupid']]['unknown']))->addClass(ZBX_STYLE_GREY)
		]);
	}
}

$output = [
	'header' => _('Web monitoring'),
	'body' => (new CDiv([getMessages(), $table]))->toString(),
	'footer' => (new CListItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))))->toString()
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);

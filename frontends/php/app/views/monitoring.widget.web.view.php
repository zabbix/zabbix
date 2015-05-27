<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$table = new CTableInfo();
$table->setHeader([
	_('Host group'),
	_('Ok'),
	_('Failed'),
	_('Unknown')
]);

$data = [];

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
	if (isset($httpTestData[$row['httptestid']]) && $httpTestData[$row['httptestid']]['lastfailedstep'] !== null) {
		if ($httpTestData[$row['httptestid']]['lastfailedstep'] != 0) {
			$data[$row['groupid']]['failed'] = isset($data[$row['groupid']]['failed'])
				? ++$data[$row['groupid']]['failed']
				: 1;
		}
		else {
			$data[$row['groupid']]['ok'] = isset($data[$row['groupid']]['ok'])
				? ++$data[$row['groupid']]['ok']
				: 1;
		}
	}
	else {
		$data[$row['groupid']]['unknown'] = isset($data[$row['groupid']]['unknown'])
			? ++$data[$row['groupid']]['unknown']
			: 1;
	}
}

foreach ($groups as $group) {
	if (!empty($data[$group['groupid']])) {
		$table->addRow([
			new CLink($group['name'], 'httpmon.php?groupid='.$group['groupid'].'&hostid=0'),
			new CSpan(empty($data[$group['groupid']]['ok']) ? 0 : $data[$group['groupid']]['ok'], ZBX_STYLE_GREEN),
			new CSpan(
				empty($data[$group['groupid']]['failed']) ? 0 : $data[$group['groupid']]['failed'],
				empty($data[$group['groupid']]['failed']) ? ZBX_STYLE_GREEN : ZBX_STYLE_RED
			),
			new CSpan(empty($data[$group['groupid']]['unknown']) ? 0 : $data[$group['groupid']]['unknown'], ZBX_STYLE_GREY)
		]);
	}
}

$script = new CJsScript(get_js(
	'jQuery("#'.WIDGET_WEB_OVERVIEW.'_footer").html("'._s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)).'");'
));

$widget = new CDiv([$table, $script]);
$widget->show();

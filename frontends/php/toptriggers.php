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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['title'] = _('Most busy triggers top 100');
$page['file'] = 'toptriggers.php';
$page['hist_arg'] = array('period');
$page['scripts'] = array('multiselect.js', 'class.calendar.js');

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR					TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupids' =>		array(T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,	null),
	'hostids' =>		array(T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,	null),
	'severities'=>		array(T_ZBX_INT,	O_OPT,	P_SYS,			null,	null),
	'filter_from' =>	array(T_ZBX_STR,	O_OPT,	P_UNSET_EMPTY,	null,	null),
	'filter_till' =>	array(T_ZBX_STR,	O_OPT,	P_UNSET_EMPTY,	null,	null),
	'filter_rst' =>		array(T_ZBX_STR,	O_OPT,	P_SYS,			null,	null),
	'filter_set' =>		array(T_ZBX_STR,	O_OPT,	P_SYS,			null,	null),
	'filterState' =>	array(T_ZBX_INT,	O_OPT,	P_ACT,			null,	null)
);
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.toptriggers.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

$data['config'] = select_config();

/*
 * Filter
 */
$today = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
$tomorrow = $today + SEC_PER_DAY;

$timeFrom = hasRequest('filter_from') ? zbxDateToTime(getRequest('filter_from')) : $today;
$timeTill = hasRequest('filter_till') ? zbxDateToTime(getRequest('filter_till')) : $tomorrow;

if (hasRequest('filter_set')) {
	// prepare severity array
	$severities = hasRequest('severities') ? array_keys(getRequest('severities')) : array();

	CProfile::updateArray('web.toptriggers.filter.severities', $severities, PROFILE_TYPE_STR);
	CProfile::updateArray('web.toptriggers.filter.groupids', getRequest('groupids', array()), PROFILE_TYPE_STR);
	CProfile::updateArray('web.toptriggers.filter.hostids', getRequest('hostids', array()), PROFILE_TYPE_STR);
	CProfile::update('web.toptriggers.filter.from', $timeFrom, PROFILE_TYPE_STR);
	CProfile::update('web.toptriggers.filter.till', $timeTill, PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBstart();
	CProfile::deleteIdx('web.toptriggers.filter.severities');
	CProfile::deleteIdx('web.toptriggers.filter.groupids');
	CProfile::deleteIdx('web.toptriggers.filter.hostids');
	CProfile::delete('web.toptriggers.filter.from');
	CProfile::delete('web.toptriggers.filter.till');
	DBend();
}

if (!hasRequest('filter_set')) {
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$defaultSeverities[$severity] = $severity;
	}
}
else {
	$defaultSeverities = array();
}

$data['filter'] = array(
	'severities' => CProfile::getArray('web.toptriggers.filter.severities', $defaultSeverities),
	'groupids' => CProfile::getArray('web.toptriggers.filter.groupids'),
	'hostids' => CProfile::getArray('web.toptriggers.filter.hostids'),
	'filter_from' => CProfile::get('web.toptriggers.filter.from', $today),
	'filter_till' => CProfile::get('web.toptriggers.filter.till', $tomorrow)
);


// multiselect host groups
$data['multiSelectHostGroupData'] = array();
if ($data['filter']['groupids'] !== null) {
	$filterGroups = API::HostGroup()->get(array(
		'output' => array('groupid', 'name'),
		'groupids' => $data['filter']['groupids']
	));

	foreach ($filterGroups as $filterGroup) {
		$data['multiSelectHostGroupData'][] = array(
			'id' => $filterGroup['groupid'],
			'name' => $filterGroup['name']
		);
	}
}

// multiselect hosts
$data['multiSelectHostData'] = array();
if ($data['filter']['hostids']) {
	$filterHosts = API::Host()->get(array(
		'output' => array('hostid', 'name'),
		'hostids' => $data['filter']['hostids']
	));

	foreach ($filterHosts as $filterHost) {
		$data['multiSelectHostData'][] = array(
			'id' => $filterHost['hostid'],
			'name' => $filterHost['name']
		);
	}
}

// data generation
$triggersEventCount = array();

// get 100 triggerids with max event count
$sql = 'SELECT e.objectid,count(distinct e.eventid) AS cnt_event'.
		' FROM triggers t,events e'.
		' WHERE t.triggerid=e.objectid'.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock>='.zbx_dbstr($data['filter']['filter_from']).
			' AND e.clock<='.zbx_dbstr($data['filter']['filter_till']).
			' AND '.dbConditionInt('t.priority', $data['filter']['severities']);

if ($data['filter']['hostids']) {
	$inHosts = ' AND '.dbConditionInt('i.hostid', $data['filter']['hostids']);
}
if ($data['filter']['groupids']) {
	$inGroups = ' AND '.dbConditionInt('hgg.groupid', $data['filter']['groupids']);
}

if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN && ($data['filter']['groupids'] || $data['filter']['hostids'])) {
	$sql .= ' AND EXISTS ('.
				'SELECT NULL'.
				' FROM functions f,items i,hosts_groups hgg'.
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
					($data['filter']['hostids'] ? $inHosts : '').
					($data['filter']['groupids'] ? $inGroups : '').
			')';
}
elseif (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
	// add permission filter
	$userId = CWebUser::$data['userid'];
	$userGroups = getUserGroupsByUserId($userId);
	$sql .= ' AND EXISTS ('.
				'SELECT NULL'.
				' FROM functions f,items i,hosts_groups hgg'.
				' JOIN rights r'.
					' ON r.id=hgg.groupid'.
						' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
					($data['filter']['hostids'] ? $inHosts : '').
					($data['filter']['groupids'] ? $inGroups : '').
				' GROUP BY f.triggerid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
			')';
}
$sql .= ' AND '.dbConditionInt('t.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
		' GROUP BY e.objectid'.
		' ORDER BY cnt_event DESC';
$result = DBselect($sql, 100);
while ($row = DBfetch($result)) {
	$triggersEventCount[$row['objectid']] = $row['cnt_event'];
}

$data['triggers'] = API::Trigger()->get(array(
	'output' => array('triggerid', 'description', 'expression', 'priority', 'flags', 'url', 'lastchange'),
	'selectItems' => array('hostid', 'name', 'value_type', 'key_'),
	'selectHosts' => array('hostid', 'status', 'name'),
	'triggerids' => array_keys($triggersEventCount),
	'expandDescription' => true,
	'preservekeys' => true
));

$data['triggers'] = CMacrosResolverHelper::resolveTriggerUrl($data['triggers']);

$hostIds = array();

foreach ($data['triggers'] as $triggerId => $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];
	$hostIds[$hostId] = $hostId;

	$triggerItems = array();

	$trigger['items'] = CMacrosResolverHelper::resolveItemNames($trigger['items']);

	foreach ($trigger['items'] as $item) {
		$triggerItems[] = array(
			'name' => $item['name_expanded'],
			'params' => array(
				'itemid' => $item['itemid'],
				'action' => in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
					? HISTORY_GRAPH
					: HISTORY_VALUES
			)
		);
	}

	$data['triggers'][$triggerId]['items'] = $triggerItems;
	$data['triggers'][$triggerId]['cnt_event'] = $triggersEventCount[$triggerId];
}

CArrayHelper::sort($data['triggers'], array(
	array('field' => 'cnt_event', 'order' => ZBX_SORT_DOWN),
	'host', 'description', 'priority'
));

$data['hosts'] = API::Host()->get(array(
	'output' => array('hostid', 'status'),
	'selectGraphs' => API_OUTPUT_COUNT,
	'selectScreens' => API_OUTPUT_COUNT,
	'hostids' => $hostIds,
	'preservekeys' => true
));

$data['scripts'] = API::Script()->getScriptsByHosts($hostIds);

// render view
$historyView = new CView('reports.toptriggers', $data);
$historyView->render();
$historyView->show();

require_once dirname(__FILE__).'/include/page_footer.php';

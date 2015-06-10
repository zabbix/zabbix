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

$page['file'] = 'tr_status.php';
$page['title'] = _('Status of triggers');
$page['scripts'] = ['class.cswitcher.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if ($page['type'] == PAGE_TYPE_HTML) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'fullscreen' =>			[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null],
	'btnSelect' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	// filter
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'show_triggers' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'show_events' =>		[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'ack_status' =>			[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'show_severity' =>		[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'show_details' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'show_maintenance' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'status_change_days' =>	[T_ZBX_INT, O_OPT, null,	BETWEEN(1, DAY_IN_YEAR * 2), null],
	'status_change' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'txt_select' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'application' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'inventory' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	// ajax
	'filterState' =>		[T_ZBX_INT, O_OPT, P_ACT,	null,		null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"description","lastchange","priority"'),	null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isReadable([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isReadable([getRequest('hostid')])) {
	access_deny();
}

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.tr_status.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Filter
 */
$config = select_config();

$pageFilter = new CPageFilter([
	'groups' => [
		'monitored_hosts' => true,
		'with_monitored_triggers' => true
	],
	'hosts' => [
		'monitored_hosts' => true,
		'with_monitored_triggers' => true
	],
	'hostid' => getRequest('hostid'),
	'groupid' => getRequest('groupid')
]);
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = $pageFilter->hostid;

// filter set
if (hasRequest('filter_set')) {
	CProfile::update('web.tr_status.filter.show_details', getRequest('show_details', 0), PROFILE_TYPE_INT);
	CProfile::update('web.tr_status.filter.show_maintenance', getRequest('show_maintenance', 0), PROFILE_TYPE_INT);
	CProfile::update('web.tr_status.filter.show_severity',
		getRequest('show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED), PROFILE_TYPE_INT
	);
	CProfile::update('web.tr_status.filter.txt_select', getRequest('txt_select', ''), PROFILE_TYPE_STR);
	CProfile::update('web.tr_status.filter.status_change', getRequest('status_change', 0), PROFILE_TYPE_INT);
	CProfile::update('web.tr_status.filter.status_change_days', getRequest('status_change_days', 14),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.tr_status.filter.application', getRequest('application'), PROFILE_TYPE_STR);

	// show triggers
	// when this filter is set to "All" it must not be remembered in the profiles because it may render the
	// whole page inaccessible on large installations.
	if (getRequest('show_triggers') != TRIGGERS_OPTION_ALL) {
		CProfile::update('web.tr_status.filter.show_triggers', getRequest('show_triggers'), PROFILE_TYPE_INT);
	}

	// show events
	$showEvents = getRequest('show_events', EVENTS_OPTION_NOEVENT);
	if ($config['event_ack_enable'] == EVENT_ACK_ENABLED || $showEvents != EVENTS_OPTION_NOT_ACK) {
		CProfile::update('web.tr_status.filter.show_events', $showEvents, PROFILE_TYPE_INT);
	}

	// ack status
	if ($config['event_ack_enable'] == EVENT_ACK_ENABLED) {
		CProfile::update('web.tr_status.filter.ack_status', getRequest('ack_status', ZBX_ACK_STS_ANY), PROFILE_TYPE_INT);
	}

	// update host inventory filter
	$inventoryFields = [];
	$inventoryValues = [];
	foreach (getRequest('inventory', []) as $field) {
		if ($field['value'] === '') {
			continue;
		}

		$inventoryFields[] = $field['field'];
		$inventoryValues[] = $field['value'];
	}
	CProfile::updateArray('web.tr_status.filter.inventory.field', $inventoryFields, PROFILE_TYPE_STR);
	CProfile::updateArray('web.tr_status.filter.inventory.value', $inventoryValues, PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.tr_status.filter.show_triggers');
	CProfile::delete('web.tr_status.filter.show_details');
	CProfile::delete('web.tr_status.filter.show_maintenance');
	CProfile::delete('web.tr_status.filter.show_events');
	CProfile::delete('web.tr_status.filter.ack_status');
	CProfile::delete('web.tr_status.filter.show_severity');
	CProfile::delete('web.tr_status.filter.txt_select');
	CProfile::delete('web.tr_status.filter.status_change');
	CProfile::delete('web.tr_status.filter.status_change_days');
	CProfile::delete('web.tr_status.filter.application');
	CProfile::deleteIdx('web.tr_status.filter.inventory.field');
	CProfile::deleteIdx('web.tr_status.filter.inventory.value');
	DBend();
}

if (hasRequest('filter_set') && getRequest('show_triggers') == TRIGGERS_OPTION_ALL) {
	$showTriggers = TRIGGERS_OPTION_ALL;
}
else {
	$showTriggers = CProfile::get('web.tr_status.filter.show_triggers', TRIGGERS_OPTION_RECENT_PROBLEM);
}
$showDetails = CProfile::get('web.tr_status.filter.show_details', 0);
$showMaintenance = CProfile::get('web.tr_status.filter.show_maintenance', 1);
$showSeverity = CProfile::get('web.tr_status.filter.show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED);
$txtSelect = CProfile::get('web.tr_status.filter.txt_select', '');
$showChange = CProfile::get('web.tr_status.filter.status_change', 0);
$statusChangeBydays = CProfile::get('web.tr_status.filter.status_change_days', 14);
$ackStatus = ($config['event_ack_enable'] == EVENT_ACK_DISABLED)
	? ZBX_ACK_STS_ANY : CProfile::get('web.tr_status.filter.ack_status', ZBX_ACK_STS_ANY);
$showEvents = CProfile::get('web.tr_status.filter.show_events', EVENTS_OPTION_NOEVENT);

// check event acknowledges
if ($config['event_ack_enable'] == EVENT_ACK_DISABLED && $showEvents == EVENTS_OPTION_NOT_ACK) {
	$showEvents = EVENTS_OPTION_NOEVENT;
}

// fetch filter from profiles
$filter = [
	'application' => CProfile::get('web.tr_status.filter.application', ''),
	'inventory' => []
];

foreach (CProfile::getArray('web.tr_status.filter.inventory.field', []) as $i => $field) {
	$filter['inventory'][] = [
		'field' => $field,
		'value' => CProfile::get('web.tr_status.filter.inventory.value', null, $i)
	];
}

/*
 * Page sorting
 */
$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'lastchange'));
$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_DOWN));

CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

/*
 * Display
 */
$triggerWidget = (new CWidget())->setTitle(_('Status of triggers'));

$rightForm = new CForm('get');
$rightForm->addVar('fullscreen', $_REQUEST['fullscreen']);

$controls = new CList();
$controls->addItem([_('Group').SPACE, $pageFilter->getGroupsCB()]);
$controls->addItem([_('Host').SPACE, $pageFilter->getHostsCB()]);
$controls->addItem(get_icon('fullscreen', ['fullscreen' => $_REQUEST['fullscreen']]));

$rightForm->addItem($controls);

$triggerWidget->setControls($rightForm);

// filter
$filterFormView = new CView('common.filter.trigger', [
	'overview' => false,
	'filter' => [
		'showTriggers' => $showTriggers,
		'ackStatus' => $ackStatus,
		'showEvents' => $showEvents,
		'showSeverity' => $showSeverity,
		'statusChange' => $showChange,
		'statusChangeDays' => $statusChangeBydays,
		'showDetails' => $showDetails,
		'txtSelect' => $txtSelect,
		'application' => $filter['application'],
		'inventory' => $filter['inventory'],
		'showMaintenance' => $showMaintenance,
		'hostId' => getRequest('hostid'),
		'groupId' => getRequest('groupid'),
		'fullScreen' => getRequest('fullscreen')
	],
	'config' => $config
]);

$filterForm = $filterFormView->render();
$triggerWidget->addItem($filterForm);

/*
 * Form
 */
$triggerForm = new CForm('get', 'acknow.php');
$triggerForm->setName('tr_status');
$triggerForm->addVar('backurl', $page['file']);

/*
 * Table
 */
$showEventColumn = ($config['event_ack_enable'] && $showEvents != EVENTS_OPTION_NOEVENT);

$switcherName = 'trigger_switchers';

$headerCheckBox = ($showEventColumn)
	? (new CCheckBox('all_events'))->onClick("checkAll('".$triggerForm->GetName()."', 'all_events', 'events');")
	: (new CCheckBox('all_triggers'))->onClick("checkAll('".$triggerForm->GetName()."', 'all_triggers', 'triggers');");

if ($showEvents != EVENTS_OPTION_NOEVENT) {
	$showHideAllDiv = (new CDiv(SPACE))
		->addClass('filterclosed')
		->setId($switcherName);
}
else {
	$showHideAllDiv = null;
}

$triggerTable = new CTableInfo();
$triggerTable->setHeader([
	$showHideAllDiv,
	$config['event_ack_enable'] ? $headerCheckBox : null,
	make_sorting_header(_('Severity'), 'priority', $sortField, $sortOrder),
	_('Status'),
	_('Info'),
	make_sorting_header(_('Last change'), 'lastchange', $sortField, $sortOrder),
	_('Age'),
	$showEventColumn ? _('Duration') : null,
	$config['event_ack_enable'] ? _('Ack') : null,
	_('Host'),
	make_sorting_header(_('Name'), 'description', $sortField, $sortOrder),
	_('Description')
]);

// get triggers
$options = [
	'output' => ['triggerid', $sortField],
	'monitored' => true,
	'skipDependent' => true,
	'sortfield' => $sortField,
	'limit' => $config['search_limit'] + 1
];

if ($pageFilter->hostsSelected) {
	if ($pageFilter->hostid > 0) {
		$options['hostids'] = $pageFilter->hostid;
	}
	elseif ($pageFilter->groupid > 0) {
		$options['groupids'] = $pageFilter->groupid;
	}
}
else {
	$options['hostids'] = [];
}

// inventory filter
if ($filter['inventory']) {
	$inventoryFilter = [];
	foreach ($filter['inventory'] as $field) {
		$inventoryFilter[$field['field']][] = $field['value'];
	}

	$hosts = API::Host()->get([
		'output' => ['hostid'],
		'hostids' => isset($options['hostids']) ? $options['hostids'] : null,
		'searchInventory' => $inventoryFilter
	]);
	$options['hostids'] = zbx_objectValues($hosts, 'hostid');
}

// application filter
if ($filter['application'] !== '') {
	$applications = API::Application()->get([
		'output' => ['applicationid'],
		'hostids' => isset($options['hostids']) ? $options['hostids'] : null,
		'search' => ['name' => $filter['application']]
	]);
	$options['applicationids'] = zbx_objectValues($applications, 'applicationid');
}

if (!zbx_empty($txtSelect)) {
	$options['search'] = ['description' => $txtSelect];
}
if ($showTriggers == TRIGGERS_OPTION_RECENT_PROBLEM) {
	$options['only_true'] = 1;
}
elseif ($showTriggers == TRIGGERS_OPTION_IN_PROBLEM) {
	$options['filter'] = ['value' => TRIGGER_VALUE_TRUE];
}
if ($ackStatus == ZBX_ACK_STS_WITH_UNACK) {
	$options['withUnacknowledgedEvents'] = 1;
}
if ($ackStatus == ZBX_ACK_STS_WITH_LAST_UNACK) {
	$options['withLastEventUnacknowledged'] = 1;
}
if ($showSeverity > TRIGGER_SEVERITY_NOT_CLASSIFIED) {
	$options['min_severity'] = $showSeverity;
}
if ($showChange) {
	$options['lastChangeSince'] = time() - $statusChangeBydays * SEC_PER_DAY;
}
if (!$showMaintenance) {
	$options['maintenance'] = false;
}
$triggers = API::Trigger()->get($options);

order_result($triggers, $sortField, $sortOrder);
$paging = getPagingLine($triggers, $sortOrder);


$triggers = API::Trigger()->get([
	'triggerids' => zbx_objectValues($triggers, 'triggerid'),
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => [
		'hostid',
		'name',
		'description',
		'status',
		'maintenanceid',
		'maintenance_status',
		'maintenance_type'
	],
	'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
	'selectDependencies' => API_OUTPUT_EXTEND,
	'selectLastEvent' => true,
	'expandDescription' => true,
	'preservekeys' => true
]);

$triggers = CMacrosResolverHelper::resolveTriggerUrl($triggers);

order_result($triggers, $sortField, $sortOrder);

// sort trigger hosts by name
foreach ($triggers as &$trigger) {
	if (count($trigger['hosts']) > 1) {
		order_result($trigger['hosts'], 'name', ZBX_SORT_UP);
	}
}
unset($trigger);

$triggerIds = zbx_objectValues($triggers, 'triggerid');

// get editable triggers
$triggerEditable = API::Trigger()->get([
	'triggerids' => $triggerIds,
	'output' => ['triggerid'],
	'editable' => true,
	'preservekeys' => true
]);

// get events
if ($config['event_ack_enable']) {
	// get all unacknowledged events, if trigger has unacknowledged event => it has events
	$eventCounts = API::Event()->get([
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'countOutput' => true,
		'groupCount' => true,
		'objectids' => $triggerIds,
		'filter' => [
			'acknowledged' => 0,
			'value' => TRIGGER_VALUE_TRUE
		]
	]);
	foreach ($eventCounts as $eventCount) {
		$triggers[$eventCount['objectid']]['hasEvents'] = true;
		$triggers[$eventCount['objectid']]['event_count'] = $eventCount['rowscount'];
	}

	// gather ids of triggers which don't have unack. events
	$triggerIdsWithoutUnackEvents = [];
	foreach ($triggers as $tnum => $trigger) {
		if (!isset($trigger['hasEvents'])) {
			$triggerIdsWithoutUnackEvents[] = $trigger['triggerid'];
		}
		if (!isset($trigger['event_count'])) {
			$triggers[$tnum]['event_count'] = 0;
		}
	}
	if (!empty($triggerIdsWithoutUnackEvents)) {
		// for triggers without unack. events we try to select any event
		$allEventCounts = API::Event()->get([
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'countOutput' => true,
			'groupCount' => true,
			'objectids' => $triggerIdsWithoutUnackEvents
		]);
		$allEventCounts = zbx_toHash($allEventCounts, 'objectid');

		foreach ($triggers as $tnum => $trigger) {
			if (!isset($trigger['hasEvents'])) {
				$triggers[$tnum]['hasEvents'] = isset($allEventCounts[$trigger['triggerid']]);
			}
		}
	}
}

if ($showEvents != EVENTS_OPTION_NOEVENT) {
	$options = [
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'objectids' => zbx_objectValues($triggers, 'triggerid'),
		'output' => API_OUTPUT_EXTEND,
		'select_acknowledges' => API_OUTPUT_COUNT,
		'time_from' => time() - $config['event_expire'] * SEC_PER_DAY,
		'time_till' => time(),
		'sortfield' => ['clock', 'eventid'],
		'sortorder' => ZBX_SORT_DOWN
	];

	switch ($showEvents) {
		case EVENTS_OPTION_ALL:
			break;
		case EVENTS_OPTION_NOT_ACK:
			$options['acknowledged'] = false;
			$options['value'] = TRIGGER_VALUE_TRUE;
			break;
	}
	$events = API::Event()->get($options);

	foreach ($events as $event) {
		$triggers[$event['objectid']]['events'][] = $event;
	}
}

// get host ids
$hostIds = [];
foreach ($triggers as $tnum => $trigger) {
	foreach ($trigger['hosts'] as $host) {
		$hostIds[$host['hostid']] = $host['hostid'];
	}
}

// get hosts
$hosts = API::Host()->get([
	'output' => ['hostid', 'status'],
	'hostids' => $hostIds,
	'preservekeys' => true,
	'selectGraphs' => API_OUTPUT_COUNT,
	'selectScreens' => API_OUTPUT_COUNT
]);

// get host scripts
$scriptsByHosts = API::Script()->getScriptsByHosts($hostIds);

// get trigger dependencies
$dbTriggerDependencies = DBselect(
	'SELECT triggerid_down,triggerid_up'.
	' FROM trigger_depends'.
	' WHERE '.dbConditionInt('triggerid_up', $triggerIds)
);
$triggerIdsDown = [];
while ($row = DBfetch($dbTriggerDependencies)) {
	$triggerIdsDown[$row['triggerid_up']][] = intval($row['triggerid_down']);
}

foreach ($triggers as $trigger) {
	$description = (new CSpan($trigger['description']))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->setMenuPopup(CMenuPopupHelper::getTrigger($trigger));

	if ($showDetails) {
		$description = [
			$description,
			BR(),
			(new CDiv(explode_exp($trigger['expression'], true, true)))->addClass('trigger-expression')
		];
	}

	if (!empty($trigger['dependencies'])) {
		$dependenciesTable = new CTableInfo();
		$dependenciesTable->setAttribute('style', 'width: 200px;');
		$dependenciesTable->addRow(bold(_('Depends on').':'));

		foreach ($trigger['dependencies'] as $dependency) {
			$dependenciesTable->addRow(' - '.CMacrosResolverHelper::resolveTriggerNameById($dependency['triggerid']));
		}

		$img = new CImg('images/general/arrow_down2.png', 'DEP_UP');
		$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
		$img->setHint($dependenciesTable);

		$description = [$img, SPACE, $description];
	}

	$dependency = false;
	$dependenciesTable = new CTableInfo();
	$dependenciesTable->setAttribute('style', 'width: 200px;');
	$dependenciesTable->addRow(bold(_('Dependent').':'));
	if (!empty($triggerIdsDown[$trigger['triggerid']])) {
		$depTriggers = CMacrosResolverHelper::resolveTriggerNameByIds($triggerIdsDown[$trigger['triggerid']]);

		foreach ($depTriggers as $depTrigger) {
			$dependenciesTable->addRow(SPACE.'-'.SPACE.$depTrigger['description']);
			$dependency = true;
		}
	}

	if ($dependency) {
		$img = new CImg('images/general/arrow_up2.png', 'DEP_UP');
		$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
		$img->setHint($dependenciesTable);

		$description = [$img, SPACE, $description];
	}
	unset($img, $dependenciesTable, $dependency);

	$triggerDescription = (new CSpan($description))->addClass('pointer');

	// host js menu
	$hostList = [];
	foreach ($trigger['hosts'] as $triggerHost) {
		// fetch scripts for the host js menu
		$scripts = [];
		if (isset($scriptsByHosts[$triggerHost['hostid']])) {
			foreach ($scriptsByHosts[$triggerHost['hostid']] as $script) {
				$scripts[] = $script;
			}
		}

		$hostName = (new CSpan($triggerHost['name']))
			->addClass(ZBX_STYLE_BTN_ACTION)
			->setMenuPopup(CMenuPopupHelper::getHost($hosts[$triggerHost['hostid']], $scripts));

		// add maintenance icon with hint if host is in maintenance
		if ($triggerHost['maintenance_status']) {
			$maintenanceIcon = (new CDiv())->addClass('icon-maintenance-inline');

			$maintenances = API::Maintenance()->get([
				'maintenanceids' => $triggerHost['maintenanceid'],
				'output' => API_OUTPUT_EXTEND,
				'limit' => 1
			]);

			if ($maintenance = reset($maintenances)) {
				$hint = $maintenance['name'].' ['.($triggerHost['maintenance_type']
					? _('Maintenance without data collection')
					: _('Maintenance with data collection')).']';

				if (isset($maintenance['description'])) {
					// double quotes mandatory
					$hint .= "\n".$maintenance['description'];
				}

				$maintenanceIcon->setHint($hint);
				$maintenanceIcon->addClass('pointer');
			}

			$hostName->addItem($maintenanceIcon);
		}

		$hostList[] = $hostName;
		$hostList[] = ', ';
	}
	array_pop($hostList);

	// host
	$hostColumn = new CCol($hostList);
	$hostColumn->addStyle('white-space: normal;');

	// status
	$statusSpan = new CSpan(trigger_value2str($trigger['value']));

	// add colors and blinking to span depending on configuration and trigger parameters
	addTriggerValueStyle(
		$statusSpan,
		$trigger['value'],
		$trigger['lastchange'],
		$config['event_ack_enable'] ? ($trigger['event_count'] == 0) : false
	);

	$lastChangeDate = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $trigger['lastchange']);
	$lastChange = empty($trigger['lastchange'])
		? $lastChangeDate
		: new CLink($lastChangeDate,
			'events.php?filter_set=1&triggerid='.$trigger['triggerid'].'&source='.EVENT_SOURCE_TRIGGERS.
				'&stime='.date(TIMESTAMP_FORMAT, $trigger['lastchange']).'&period='.ZBX_PERIOD_DEFAULT
		);

	// acknowledge
	if ($config['event_ack_enable']) {
		if ($trigger['hasEvents']) {
			if ($trigger['event_count']) {
				$ackColumn = new CCol([
					(new CLink(
						_('No'),
						'acknow.php?'.
							'triggers[]='.$trigger['triggerid'].
							'&backurl='.$page['file']))
						->addClass(ZBX_STYLE_LINK_ALT)
						->addClass(ZBX_STYLE_RED),
					CViewHelper::showNum($trigger['event_count'])
				]);
			}
			else {
				$ackColumn = new CCol(
					(new CLink(
						_('Yes'),
						'acknow.php?'.
							'eventid='.$trigger['lastEvent']['eventid'].
							'&triggerid='.$trigger['lastEvent']['objectid'].
							'&backurl='.$page['file']))
						->addClass(ZBX_STYLE_LINK_ALT)
						->addClass(ZBX_STYLE_GREEN)
				);
			}
		}
		else {
			$ackColumn = (new CCol(_('No events')))->addClass(ZBX_STYLE_GREY);
		}
	}
	else {
		$ackColumn = null;
	}

	// open or close
	if ($showEvents != EVENTS_OPTION_NOEVENT && !empty($trigger['events'])) {
		$openOrCloseDiv = (new CDiv(SPACE))
			->addClass('filterclosed')
			->setAttribute('data-switcherid', $trigger['triggerid']);
	}
	elseif ($showEvents == EVENTS_OPTION_NOEVENT) {
		$openOrCloseDiv = null;
	}
	else {
		$openOrCloseDiv = SPACE;
	}

	// severity
	$severityColumn = getSeverityCell($trigger['priority'], $config, null, !$trigger['value']);
	if ($showEventColumn) {
		$severityColumn->setColSpan(2);
	}

	// unknown triggers
	$unknown = SPACE;
	if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
		$unknown = (new CDiv(SPACE))
			->addClass('status_icon')
			->addClass('iconunknown')
			->setHint($trigger['error'], ZBX_STYLE_RED);
	}

	// comments
	if (isset($triggerEditable[$trigger['triggerid']])) {
		$comments = new CLink(zbx_empty($trigger['comments']) ? _('Add') : _('Show'), 'tr_comments.php?triggerid='.$trigger['triggerid']);
	}
	else {
		$comments = zbx_empty($trigger['comments'])
			? new CSpan('')
			: new CLink(_('Show'), 'tr_comments.php?triggerid='.$trigger['triggerid']);
	}

	$triggerTable->addRow([
		$openOrCloseDiv,
		$config['event_ack_enable'] ?
			($showEventColumn ? null : new CCheckBox('triggers['.$trigger['triggerid'].']', $trigger['triggerid'])) : null,
		$severityColumn,
		$statusSpan,
		$unknown,
		$lastChange,
		empty($trigger['lastchange']) ? '' : zbx_date2age($trigger['lastchange']),
		$showEventColumn ? SPACE : null,
		$ackColumn,
		$hostColumn,
		$triggerDescription,
		$comments
	], 'even_row');

	if ($showEvents != EVENTS_OPTION_NOEVENT && !empty($trigger['events'])) {
		$i = 1;
		foreach ($trigger['events'] as $enum => $event) {
			$i++;

			$eventStatusSpan = new CSpan(trigger_value2str($event['value']));

			// add colors and blinking to span depending on configuration and trigger parameters
			addTriggerValueStyle(
				$eventStatusSpan,
				$event['value'],
				$event['clock'],
				$event['acknowledged']
			);

			$statusSpan = new CCol($eventStatusSpan);
			$statusSpan->setColSpan(2);

			$ack = getEventAckState($event, $page['file']);

			$ackCheckBox = ($event['acknowledged'] == 0 && $event['value'] == TRIGGER_VALUE_TRUE)
				? new CCheckBox('events['.$event['eventid'].']', $event['eventid'])
				: SPACE;

			$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
				'tr_events.php?triggerid='.$trigger['triggerid'].'&eventid='.$event['eventid']);

			$nextClock = isset($trigger['events'][$enum - 1]) ? $trigger['events'][$enum - 1]['clock'] : time();

			$emptyColumn = new CCol(SPACE);
			$emptyColumn->setColSpan(3);
			$ackCheckBoxColumn = new CCol($ackCheckBox);
			$ackCheckBoxColumn->setColSpan(2);

			$row = (new CRow([
				SPACE,
				$config['event_ack_enable'] ? $ackCheckBoxColumn : null,
				$statusSpan,
				$clock,
				zbx_date2age($event['clock']),
				zbx_date2age($nextClock, $event['clock']),
				($config['event_ack_enable']) ? $ack : null,
				$emptyColumn]))
					->addClass('odd_row')
					->setAttribute('data-parentid', $trigger['triggerid'])
					->addStyle('display: none;');
			$triggerTable->addRow($row);

			if ($i > $config['event_show_max']) {
				break;
			}
		}
	}
}

/*
 * Go buttons
 */
$footer = null;
if ($config['event_ack_enable']) {
	$footer = new CActionButtonList('action', $showEventColumn ? 'events' : 'triggers', [
		'trigger.bulkacknowledge' => ['name' => _('Bulk acknowledge')]
	]);
}

$triggerForm->addItem([$triggerTable, $paging, $footer]);
$triggerWidget->addItem($triggerForm)->show();

zbx_add_post_js('jqBlink.blink();');
zbx_add_post_js('var switcher = new CSwitcher(\''.$switcherName.'\');');

require_once dirname(__FILE__).'/include/page_footer.php';

<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$rightForm = (new CForm('get'))
	->addVar('fullscreen', $_REQUEST['fullscreen']);

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
		'filterid' => 'web.tr_status.filter.state',
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
$triggerForm = (new CForm('get', 'zabbix.php'))
	->setName('tr_status')
	->addVar('backurl', $page['file'])
	->addVar('acknowledge_type', ZBX_ACKNOWLEDGE_PROBLEM);

/*
 * Table
 */
$switcherName = 'trigger_switchers';

if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
	$showHideAllDiv = (new CColHeader(
		(new CDiv())
			->addClass(ZBX_STYLE_TREEVIEW)
			->setId($switcherName)
			->addItem((new CSpan())->addClass(ZBX_STYLE_ARROW_RIGHT))
	))->addClass(ZBX_STYLE_CELL_WIDTH);
}
else {
	$showHideAllDiv = null;
}

if ($config['event_ack_enable']) {
	$headerCheckBox = (new CColHeader(
		(new CCheckBox('all_eventids'))
			->onClick("checkAll('".$triggerForm->GetName()."', 'all_eventids', 'eventids');")
	))->addClass(ZBX_STYLE_CELL_WIDTH);
}
else {
	$headerCheckBox = null;
}

$triggerTable = (new CTableInfo())
	->setHeader([
		$showHideAllDiv,
		$headerCheckBox,
		make_sorting_header(_('Severity'), 'priority', $sortField, $sortOrder),
		_('Status'),
		_('Info'),
		make_sorting_header(_('Last change'), 'lastchange', $sortField, $sortOrder),
		_('Age'),
		($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) ? _('Duration') : null,
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

$url = (new CUrl('tr_status.php'))
	->setArgument('fullscreen', getRequest('fullscreen'))
	->setArgument('groupid', $pageFilter->groupid)
	->setArgument('hostid', $pageFilter->hostid)
	->setArgument('show_triggers', getRequest('show_triggers'))
	->setArgument('filter_set', getRequest('filter_set'));

$paging = getPagingLine($triggers, $sortOrder, $url);

$triggers = API::Trigger()->get([
	'triggerids' => zbx_objectValues($triggers, 'triggerid'),
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => [
		'hostid', 'name', 'description', 'status', 'maintenanceid', 'maintenance_status', 'maintenance_type'
	],
	'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
	'selectDependencies' => API_OUTPUT_EXTEND,
	'selectLastEvent' => ['eventid', 'objectid', 'clock', 'ns'],
	'preservekeys' => true
]);

$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);
if ($showDetails) {
	foreach ($triggers as &$trigger) {
		$trigger['expression_orig'] = $trigger['expression'];
	}
	unset($trigger);

	$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
		['html' => true, 'resolve_usermacros' => true, 'resolve_macros' => true]
	);

	foreach ($triggers as &$trigger) {
		$trigger['expression_html'] = $trigger['expression'];
		$trigger['expression'] = $trigger['expression_orig'];
		unset($trigger['expression_orig']);
	}
	unset($trigger);
}

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

if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
	foreach ($triggers as &$trigger) {
		$trigger['events'] = [];
	}
	unset($trigger);

	$options = [
		'output' => ['eventid', 'objectid', 'clock', 'value'],
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'objectids' => zbx_objectValues($triggers, 'triggerid'),
		'time_from' => time() - $config['event_expire'] * SEC_PER_DAY,
		'time_till' => time(),
		'sortfield' => ['clock', 'eventid'],
		'sortorder' => ZBX_SORT_DOWN
	];

	if ($config['event_ack_enable']) {
		$options['select_acknowledges'] = API_OUTPUT_COUNT;
		$options['output'][] = 'acknowledged';
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

$maintenanceids = [];

foreach ($triggers as $trigger) {
	foreach ($trigger['hosts'] as $host) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$maintenanceids[$host['maintenanceid']] = true;
		}
	}
}

if ($maintenanceids) {
	$maintenances = API::Maintenance()->get([
		'maintenanceids' => array_keys($maintenanceids),
		'output' => ['name', 'description'],
		'preservekeys' => true
	]);
}

foreach ($triggers as $trigger) {
	/*
	 * At this point "all" or one group is selected. And same goes for hosts. It is safe to pass 'groupid' and 'hostid'
	 * to trigger menu pop-up, so it properly redirects to Events page. Mind that 'DDRemember' option will be ignored.
	 */
	$trigger['groupid'] = $pageFilter->groupid;
	$trigger['hostid'] = $pageFilter->hostid;

	$description = [];

	if (!empty($trigger['dependencies'])) {
		$dependenciesTable = (new CTable())
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			->addRow(_('Depends on').':');

		foreach ($trigger['dependencies'] as $dependency) {
			$dependenciesTable->addRow(' - '.CMacrosResolverHelper::resolveTriggerNameById($dependency['triggerid']));
		}

		$description[] = (new CSpan())
			->addClass(ZBX_STYLE_ICON_DEPEND_DOWN)
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->setHint($dependenciesTable);
	}

	$dependency = false;
	$dependenciesTable = (new CTable())
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->addRow(_('Dependent').':');
	if (array_key_exists($trigger['triggerid'], $triggerIdsDown) && $triggerIdsDown[$trigger['triggerid']]) {
		$depTriggers = CMacrosResolverHelper::resolveTriggerNameByIds($triggerIdsDown[$trigger['triggerid']]);

		foreach ($depTriggers as $depTrigger) {
			$dependenciesTable->addRow(SPACE.'-'.SPACE.$depTrigger['description']);
			$dependency = true;
		}
	}

	if ($dependency) {
		$description[] = (new CSpan())
			->addClass(ZBX_STYLE_ICON_DEPEND_UP)
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->setHint($dependenciesTable);
	}
	unset($img, $dependenciesTable, $dependency);

	// Trigger has events.
	if ($trigger['lastEvent']) {
		$event = [
			'clock' => $trigger['lastEvent']['clock'],
			'ns' => $trigger['lastEvent']['ns']
		];
	}
	// Trigger has no events.
	else {
		$event = [
			'clock' => $trigger['lastchange'],
			'ns' => '999999999'
		];
	}

	$description[] = (new CSpan(CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, $event))))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->setMenuPopup(CMenuPopupHelper::getTrigger($trigger));

	if ($showDetails) {
		$description[] = BR();
		$description[] = $trigger['expression_html'];
	}

	// host js menu
	$hostList = [];
	foreach ($trigger['hosts'] as $host) {
		// fetch scripts for the host js menu
		$scripts = [];
		if (isset($scriptsByHosts[$host['hostid']])) {
			foreach ($scriptsByHosts[$host['hostid']] as $script) {
				$scripts[] = $script;
			}
		}

		$host_name = (new CSpan($host['name']))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->setMenuPopup(CMenuPopupHelper::getHost($hosts[$host['hostid']], $scripts));

		// add maintenance icon with hint if host is in maintenance
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$maintenance_icon = (new CSpan())
				->addClass(ZBX_STYLE_ICON_MAINT)
				->addClass(ZBX_STYLE_CURSOR_POINTER);

			if (array_key_exists($host['maintenanceid'], $maintenances)) {
				$maintenance = $maintenances[$host['maintenanceid']];

				$hint = $maintenance['name'].' ['.($host['maintenance_type']
					? _('Maintenance without data collection')
					: _('Maintenance with data collection')).']';

				if ($maintenance['description']) {
					$hint .= "\n".$maintenance['description'];
				}

				$maintenance_icon->setHint($hint);
			}

			$host_name = (new CSpan([$host_name, $maintenance_icon]))->addClass(ZBX_STYLE_REL_CONTAINER);
		}

		$hostList[] = $host_name;
		$hostList[] = ', ';
	}
	array_pop($hostList);

	// status
	$statusSpan = new CSpan(trigger_value2str($trigger['value']));

	// add colors and blinking to span depending on configuration and trigger parameters
	addTriggerValueStyle(
		$statusSpan,
		$trigger['value'],
		$trigger['lastchange'],
		$config['event_ack_enable'] ? ($trigger['event_count'] == 0) : false
	);

	// open or close
	// acknowledge
	if ($config['event_ack_enable']) {
		if ($trigger['hasEvents']) {
			$ack_checkbox = new CCheckBox('eventids['.$trigger['lastEvent']['eventid'].']',
				$trigger['lastEvent']['eventid']
			);
			if ($trigger['event_count']) {
				$ackColumn = [
					(new CLink(_('No'),
						'zabbix.php?action=acknowledge.edit'.
							'&acknowledge_type='.ZBX_ACKNOWLEDGE_PROBLEM.
							'&eventids[]='.$trigger['lastEvent']['eventid'].
							'&backurl='.$page['file']
					))
						->addClass(ZBX_STYLE_LINK_ALT)
						->addClass(ZBX_STYLE_RED),
					CViewHelper::showNum($trigger['event_count'])
				];
			}
			else {
				$ackColumn = (new CLink(_('Yes'),
					'zabbix.php?action=acknowledge.edit'.
						'&acknowledge_type='.ZBX_ACKNOWLEDGE_PROBLEM.
						'&eventids[]='.$trigger['lastEvent']['eventid'].
						'&backurl='.$page['file']
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREEN);
			}
		}
		else {
			$ack_checkbox = '';
			$ackColumn = (new CCol(_('No events')))->addClass(ZBX_STYLE_GREY);
		}
	}
	else {
		$ack_checkbox = null;
		$ackColumn = null;
	}

	if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
		$openOrCloseDiv = $trigger['events']
			? (new CDiv())
				->addClass(ZBX_STYLE_TREEVIEW)
				->setAttribute('data-switcherid', $trigger['triggerid'])
				->addItem((new CSpan())->addClass(ZBX_STYLE_ARROW_RIGHT))
			: '';
	}
	else {
		$openOrCloseDiv = null;
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
		$ack_checkbox,
		getSeverityCell($trigger['priority'], $config, null, !$trigger['value']),
		$statusSpan,
		($trigger['state'] == TRIGGER_STATE_UNKNOWN) ? makeUnknownIcon($trigger['error']) : '',
		($trigger['lastchange'] == 0)
			? _('Never')
			: new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $trigger['lastchange']),
				'events.php?filter_set=1&triggerid='.$trigger['triggerid'].'&source='.EVENT_SOURCE_TRIGGERS.
					'&stime='.date(TIMESTAMP_FORMAT, $trigger['lastchange']).'&period='.ZBX_PERIOD_DEFAULT
			),
		($trigger['lastchange'] == 0) ? '' : zbx_date2age($trigger['lastchange']),
		($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) ? '' : null,
		$ackColumn,
		$hostList,
		$description,
		$comments
	]);

	if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
		$next_event_clock = time();

		foreach (array_slice($trigger['events'], 0, $config['event_show_max']) as $enum => $event) {
			if ($showEvents == EVENTS_OPTION_NOT_ACK) {
				if ($event['acknowledged'] || $event['value'] != TRIGGER_VALUE_TRUE) {
					continue;
				}
			}

			$eventStatusSpan = new CSpan(trigger_value2str($event['value']));

			// add colors and blinking to span depending on configuration and trigger parameters
			addTriggerValueStyle($eventStatusSpan, $event['value'], $event['clock'],
				$config['event_ack_enable'] && $event['acknowledged']);

			$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
				'tr_events.php?triggerid='.$trigger['triggerid'].'&eventid='.$event['eventid']);

			if ($enum != 0) {
				$next_event_clock = $trigger['events'][$enum - 1]['clock'];
			}

			$triggerTable->addRow(
				(new CRow([
					(new CCol())->setColSpan($config['event_ack_enable'] ? 3 : 2),
					(new CCol($eventStatusSpan))->setColSpan(2),
					$clock,
					zbx_date2age($event['clock']),
					zbx_date2age($next_event_clock, $event['clock']),
					$config['event_ack_enable'] ? getEventAckState($event, $page['file']) : null,
					(new CCol())->setColSpan(3)
				]))
					->setAttribute('data-parentid', $trigger['triggerid'])
					->addStyle('display: none;')
			);
		}
	}
}

/*
 * Go buttons
 */
$footer = null;
if ($config['event_ack_enable']) {
	$footer = new CActionButtonList('action', 'eventids', [
		'acknowledge.edit' => ['name' => _('Bulk acknowledge')]
	]);
}

$triggerForm->addItem([$triggerTable, $paging, $footer]);
$triggerWidget->addItem($triggerForm)->show();

zbx_add_post_js('jqBlink.blink();');
zbx_add_post_js('var switcher = new CSwitcher(\''.$switcherName.'\');');

require_once dirname(__FILE__).'/include/page_footer.php';

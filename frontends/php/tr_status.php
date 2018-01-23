<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';

$page['file'] = 'tr_status.php';
$page['title'] = _('Triggers');
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
if (getRequest('groupid') && !isReadableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('hostid') && !isReadableHosts([getRequest('hostid')])) {
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
	CProfile::update('web.tr_status.filter.show_triggers', getRequest('show_triggers', TRIGGERS_OPTION_RECENT_PROBLEM),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.tr_status.filter.show_details', getRequest('show_details', 0), PROFILE_TYPE_INT);
	CProfile::update('web.tr_status.filter.show_maintenance', getRequest('show_maintenance', 0), PROFILE_TYPE_INT);
	CProfile::update('web.tr_status.filter.show_severity', getRequest('show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.tr_status.filter.txt_select', getRequest('txt_select', ''), PROFILE_TYPE_STR);
	CProfile::update('web.tr_status.filter.status_change', getRequest('status_change', 0), PROFILE_TYPE_INT);
	CProfile::update('web.tr_status.filter.status_change_days', getRequest('status_change_days', 14),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.tr_status.filter.application', getRequest('application'), PROFILE_TYPE_STR);

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

$showTriggers = CProfile::get('web.tr_status.filter.show_triggers', TRIGGERS_OPTION_RECENT_PROBLEM);
$showDetails = CProfile::get('web.tr_status.filter.show_details', 0);
$showMaintenance = CProfile::get('web.tr_status.filter.show_maintenance', 1);
$showSeverity = CProfile::get('web.tr_status.filter.show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED);
$txtSelect = CProfile::get('web.tr_status.filter.txt_select', '');
$showChange = CProfile::get('web.tr_status.filter.status_change', 0);
$statusChangeDays = CProfile::get('web.tr_status.filter.status_change_days', 14);
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
		$options['groupids'] = $pageFilter->groupids;
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
	$options['lastChangeSince'] = time() - $statusChangeDays * SEC_PER_DAY;
}
if (!$showMaintenance) {
	$options['maintenance'] = false;
}
$triggers = API::Trigger()->get($options);

order_result($triggers, $sortField, $sortOrder);

$url = (new CUrl('tr_status.php'))
	->setArgument('fullscreen', getRequest('fullscreen'))
	->setArgument('groupid', $pageFilter->groupid)
	->setArgument('hostid', $pageFilter->hostid);

$paging = getPagingLine($triggers, $sortOrder, $url);

$triggers = API::Trigger()->get([
	'triggerids' => zbx_objectValues($triggers, 'triggerid'),
	'output' => ['triggerid', 'expression', 'description', 'url', 'value', 'priority', 'lastchange', 'comments',
		'error', 'state', 'recovery_mode', 'recovery_expression'
	],
	'selectHosts' => ['hostid', 'name', 'status'],
	'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
	'selectDependencies' => ['triggerid'],
	'selectLastEvent' => ['eventid', 'objectid', 'clock', 'ns'],
	'preservekeys' => true
]);

$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);
if ($showDetails) {
	foreach ($triggers as &$trigger) {
		$trigger['expression_html'] = $trigger['expression'];
		$trigger['recovery_expression_html'] = $trigger['recovery_expression'];
	}
	unset($trigger);

	$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers, [
		'html' => true,
		'resolve_usermacros' => true,
		'resolve_macros' => true,
		'sources' => ['expression_html', 'recovery_expression_html']
	]);
}

order_result($triggers, $sortField, $sortOrder);

$triggerIds = array_keys($triggers);

// get editable triggers
$triggerEditable = API::Trigger()->get([
	'triggerids' => $triggerIds,
	'output' => ['triggerid'],
	'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
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
		$triggers[$tnum]['last_problem_eventid'] = 0;

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

	$problems = getTriggerLastProblems($triggerIds, ['eventid', 'objectid']);

	foreach ($problems as $problem) {
		$triggers[$problem['objectid']]['last_problem_eventid'] = $problem['eventid'];
	}
}

if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
	foreach ($triggers as &$trigger) {
		$trigger['display_events'] = false;
		$trigger['events'] = [];
	}
	unset($trigger);

	$options = [
		'output' => ['eventid', 'r_eventid', 'objectid', 'clock', 'value'],
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'value' => TRIGGER_VALUE_TRUE,
		'objectids' => zbx_objectValues($triggers, 'triggerid'),
		'time_from' => time() - timeUnitToSeconds($config['event_expire']),
		'time_till' => time(),
		'sortfield' => ['clock', 'eventid'],
		'sortorder' => ZBX_SORT_DOWN
	];

	if ($config['event_ack_enable']) {
		$options['output'][] = 'acknowledged';
		$options['select_acknowledges'] = ['clock', 'message', 'action', 'userid', 'alias', 'name', 'surname'];
	}

	$events = API::Event()->get($options);

	if ($events) {
		$r_eventids = [];

		foreach ($events as $event) {
			$r_eventids[$event['r_eventid']] = true;
		}
		unset($r_eventids[0]);

		$r_events = $r_eventids
			? API::Event()->get([
				'output' => ['clock'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => array_keys($r_eventids),
				'preservekeys' => true
			])
			: [];

		foreach ($events as $event) {
			if (array_key_exists($event['r_eventid'], $r_events)) {
				$event['r_clock'] = $r_events[$event['r_eventid']]['clock'];
			}
			else {
				$event['r_clock'] = 0;
			}

			$triggers[$event['objectid']]['events'][] = $event;

			if ($showEvents == EVENTS_OPTION_ALL) {
				$triggers[$event['objectid']]['display_events'] = true;
			}
			elseif (!$event['acknowledged']) {
				$triggers[$event['objectid']]['display_events'] = true;
			}
		}
	}
}
else {
	foreach ($triggers as &$trigger) {
		$trigger['display_events'] = false;
	}
	unset($trigger);
}

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

$triggers_hosts = getTriggersHostsList($triggers);
$triggers_hosts = makeTriggersHostsList($triggers_hosts);

/*
 * Display
 */
$switcherName = 'trigger_switchers';

if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
	$showHideAllButton = (new CColHeader(
		(new CSimpleButton())
			->addClass(ZBX_STYLE_TREEVIEW)
			->setId($switcherName)
			->addItem((new CSpan())->addClass(ZBX_STYLE_ARROW_RIGHT))
	))->addClass(ZBX_STYLE_CELL_WIDTH);
}
else {
	$showHideAllButton = null;
}

$form = (new CForm('get', 'zabbix.php'))
	->setName('tr_status')
	->addVar('backurl', $page['file'])
	->addVar('acknowledge_type', ZBX_ACKNOWLEDGE_PROBLEM);

if ($config['event_ack_enable']) {
	$headerCheckBox = (new CColHeader(
		(new CCheckBox('all_eventids'))
			->onClick("checkAll('".$form->getName()."', 'all_eventids', 'eventids');")
	))->addClass(ZBX_STYLE_CELL_WIDTH);
}
else {
	$headerCheckBox = null;
}

$triggerTable = (new CTableInfo())
	->setHeader([
		$showHideAllButton,
		$headerCheckBox,
		make_sorting_header(_('Severity'), 'priority', $sortField, $sortOrder),
		_('Status'),
		_('Info'),
		make_sorting_header(_('Time'), 'lastchange', $sortField, $sortOrder),
		($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) ? _('Recovery time') : null,
		_('Age'),
		($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) ? _('Duration') : null,
		$config['event_ack_enable'] ? _('Ack') : null,
		_('Host'),
		make_sorting_header(_('Name'), 'description', $sortField, $sortOrder),
		_('Description')
	]);

foreach ($triggers as $trigger) {
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

	$description[] = (new CLinkAction(CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, $event))))
		->setMenuPopup(CMenuPopupHelper::getTrigger($trigger));

	if ($showDetails) {
		$description[] = BR();

		if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
			$description[] = [_('Problem'), ': ', $trigger['expression_html'], BR()];
			$description[] = [_('Recovery'), ': ', $trigger['recovery_expression_html']];
		}
		else {
			$description[] = $trigger['expression_html'];
		}
	}

	$statusSpan = new CSpan(trigger_value2str($trigger['value']));

	// add colors and blinking to span depending on configuration and trigger parameters
	addTriggerValueStyle(
		$statusSpan,
		$trigger['value'],
		$trigger['lastchange'],
		$config['event_ack_enable'] ? ($trigger['event_count'] == 0) : false
	);

	if ($config['event_ack_enable']) {
		if ($trigger['hasEvents']) {
			$ack_checkbox = new CCheckBox('eventids['.$trigger['last_problem_eventid'].']',
				$trigger['last_problem_eventid']
			);

			$ack_column = [
				(new CLink(
					($trigger['event_count'] != 0) ? _('No') : _('Yes'),
					'zabbix.php?action=acknowledge.edit'.
						'&acknowledge_type='.ZBX_ACKNOWLEDGE_PROBLEM.
						'&eventids[]='.$trigger['last_problem_eventid'].
						'&backurl='.$page['file']
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(($trigger['event_count'] != 0) ? ZBX_STYLE_RED : ZBX_STYLE_GREEN)
			];

			if ($trigger['event_count'] != 0) {
				$ack_column[] = CViewHelper::showNum($trigger['event_count']);
			}
		}
		else {
			$ack_checkbox = '';
			$ack_column = (new CCol(_('No events')))->addClass(ZBX_STYLE_GREY);
		}
	}
	else {
		$ack_checkbox = null;
		$ack_column = null;
	}

	if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
		$openOrCloseButton = $trigger['display_events']
			? (new CSimpleButton())
				->addClass(ZBX_STYLE_TREEVIEW)
				->setAttribute('data-switcherid', $trigger['triggerid'])
				->addItem((new CSpan())->addClass(ZBX_STYLE_ARROW_RIGHT))
			: '';
	}
	else {
		$openOrCloseButton = null;
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

	$info_icons = [];
	if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
		$info_icons[] = makeUnknownIcon($trigger['error']);
	}

	$triggerTable->addRow([
		$openOrCloseButton,
		$ack_checkbox,
		getSeverityCell($trigger['priority'], $config, null, !$trigger['value']),
		$statusSpan,
		makeInformationList($info_icons),
		($trigger['lastchange'] == 0)
			? _('Never')
			: new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $trigger['lastchange']),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_triggerids[]', $trigger['triggerid'])
					->setArgument('filter_set', '1')
			),
		($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) ? '' : null,
		($trigger['lastchange'] == 0) ? '' : zbx_date2age($trigger['lastchange']),
		($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) ? '' : null,
		$ack_column,
		$triggers_hosts[$trigger['triggerid']],
		$description,
		$comments
	]);

	if ($showEvents == EVENTS_OPTION_ALL || $showEvents == EVENTS_OPTION_NOT_ACK) {
		$next_event_clock = time();

		foreach (array_slice($trigger['events'], 0, $config['event_show_max']) as $enum => $event) {
			if ($showEvents == EVENTS_OPTION_NOT_ACK && $event['acknowledged']) {
				continue;
			}

			if ($event['r_eventid'] == 0) {
				$in_closing = false;

				if ($config['event_ack_enable']) {
					foreach ($event['acknowledges'] as $acknowledge) {
						if ($acknowledge['action'] == ZBX_ACKNOWLEDGE_ACTION_CLOSE_PROBLEM) {
							$in_closing = true;
							break;
						}
					}
				}

				$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
				$value_clock = $in_closing ? time() : $event['clock'];
			}
			else {
				$value = TRIGGER_VALUE_FALSE;
				$value_str = _('RESOLVED');
				$value_clock = $event['r_clock'];
			}

			$cell_status = new CSpan($value_str);

			// Add colors and blinking to span depending on configuration and trigger parameters.
			addTriggerValueStyle($cell_status, $value, $value_clock,
				$config['event_ack_enable'] ? (bool) $event['acknowledges'] : false
			);

			$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
				'tr_events.php?triggerid='.$trigger['triggerid'].'&eventid='.$event['eventid']);

			$r_clock = ($event['r_eventid'] == 0)
				? ''
				: new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['r_clock']),
					'tr_events.php?triggerid='.$trigger['triggerid'].'&eventid='.$event['eventid']
				);

			if ($enum != 0) {
				$next_event_clock = $trigger['events'][$enum - 1]['clock'];
			}

			$triggerTable->addRow(
				(new CRow([
					(new CCol())->setColSpan($config['event_ack_enable'] ? 3 : 2),
					(new CCol($cell_status))->setColSpan(2),
					$clock,
					$r_clock,
					zbx_date2age($event['clock']),
					zbx_date2age($next_event_clock, $event['clock']),
					$config['event_ack_enable']
						? ($event['value'] == TRIGGER_VALUE_TRUE)
							? getEventAckState($event, $page['file'])
							: ''
						: null,
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

$triggerWidget = (new CWidget())
	->setTitle(_('Triggers'))
	->setControls(
		(new CForm('get'))
			->addVar('fullscreen', $_REQUEST['fullscreen'])
			->addItem(
				(new CList())
					->addItem([
						new CLabel(_('Group'), 'groupid'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$pageFilter->getGroupsCB()
					])
					->addItem([
						new CLabel(_('Host'), 'hostid'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$pageFilter->getHostsCB()
					])
					->addItem(get_icon('fullscreen', ['fullscreen' => $_REQUEST['fullscreen']]))
			)
	)
	->addItem(
		(new CView('common.filter.trigger', [
			'overview' => false,
			'filter' => [
				'filterid' => 'web.tr_status.filter.state',
				'showTriggers' => $showTriggers,
				'ackStatus' => $ackStatus,
				'showEvents' => $showEvents,
				'showSeverity' => $showSeverity,
				'statusChange' => $showChange,
				'statusChangeDays' => $statusChangeDays,
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
		]))
			->render()
	)
	->addItem($form->addItem([$triggerTable, $paging, $footer]))
	->show();

zbx_add_post_js('jqBlink.blink();');
zbx_add_post_js('var switcher = new CSwitcher(\''.$switcherName.'\');');

require_once dirname(__FILE__).'/include/page_footer.php';

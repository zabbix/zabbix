<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
$page['hist_arg'] = array('groupid', 'hostid');
$page['scripts'] = array('class.cswitcher.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if ($page['type'] == PAGE_TYPE_HTML) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'fullscreen' =>			array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'btnSelect' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	// filter
	'filter_rst' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'filter_set' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'show_triggers' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'show_events' =>		array(T_ZBX_INT, O_OPT, P_SYS,	null,		null),
	'ack_status' =>			array(T_ZBX_INT, O_OPT, P_SYS,	null,		null),
	'show_severity' =>		array(T_ZBX_INT, O_OPT, P_SYS,	null,		null),
	'show_details' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'show_maintenance' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	'status_change_days' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, DAY_IN_YEAR * 2), null),
	'status_change' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'txt_select' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>				array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>				array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>			array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})')
);
check_fields($fields);

/*
 * Permissions
 */
if (get_request('groupid') && !API::HostGroup()->isReadable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (get_request('hostid') && !API::Host()->isReadable(array($_REQUEST['hostid']))) {
	access_deny();
}

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.tr_status.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Filter
 */
$config = select_config();

$pageFilter = new CPageFilter(array(
	'groups' => array(
		'monitored_hosts' => true,
		'with_monitored_triggers' => true
	),
	'hosts' => array(
		'monitored_hosts' => true,
		'with_monitored_triggers' => true
	),
	'hostid' => get_request('hostid', null),
	'groupid' => get_request('groupid', null)
));
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = $pageFilter->hostid;

if (isset($_REQUEST['filter_rst'])) {
	$_REQUEST['show_details'] = 0;
	$_REQUEST['show_maintenance'] = 1;
	$_REQUEST['show_triggers'] = TRIGGERS_OPTION_ONLYTRUE;
	$_REQUEST['show_events'] = EVENTS_OPTION_NOEVENT;
	$_REQUEST['ack_status'] = ZBX_ACK_STS_ANY;
	$_REQUEST['show_severity'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
	$_REQUEST['txt_select'] = '';
	$_REQUEST['status_change'] = 0;
	$_REQUEST['status_change_days'] = 14;
}

// show triggers
$_REQUEST['show_triggers'] = isset($_REQUEST['show_triggers']) ? $_REQUEST['show_triggers'] : TRIGGERS_OPTION_ONLYTRUE;

// show events
if (isset($_REQUEST['show_events'])) {
	if ($config['event_ack_enable'] == EVENT_ACK_DISABLED) {
		if (!str_in_array($_REQUEST['show_events'], array(EVENTS_OPTION_NOEVENT, EVENTS_OPTION_ALL))) {
			$_REQUEST['show_events'] = EVENTS_OPTION_NOEVENT;
		}
	}

	CProfile::update('web.tr_status.filter.show_events', $_REQUEST['show_events'], PROFILE_TYPE_INT);
}
else {
	$_REQUEST['show_events'] = ($config['event_ack_enable'] == EVENT_ACK_DISABLED)
		? EVENTS_OPTION_NOEVENT
		: CProfile::get('web.tr_status.filter.show_events', EVENTS_OPTION_NOEVENT);
}

// show details
if (isset($_REQUEST['show_details'])) {
	CProfile::update('web.tr_status.filter.show_details', $_REQUEST['show_details'], PROFILE_TYPE_INT);
}
else {
	if (isset($_REQUEST['filter_set'])) {
		CProfile::update('web.tr_status.filter.show_details', 0, PROFILE_TYPE_INT);
		$_REQUEST['show_details'] = 0;
	}
	else {
		$_REQUEST['show_details'] = CProfile::get('web.tr_status.filter.show_details', 0);
	}
}

// show maintenance
if (isset($_REQUEST['show_maintenance'])) {
	CProfile::update('web.tr_status.filter.show_maintenance', $_REQUEST['show_maintenance'], PROFILE_TYPE_INT);
}
else {
	if (isset($_REQUEST['filter_set'])) {
		CProfile::update('web.tr_status.filter.show_maintenance', 0, PROFILE_TYPE_INT);
		$_REQUEST['show_maintenance'] = 0;
	}
	else {
		$_REQUEST['show_maintenance'] = CProfile::get('web.tr_status.filter.show_maintenance', 1);
	}
}

// show severity
if (isset($_REQUEST['show_severity'])) {
	CProfile::update('web.tr_status.filter.show_severity', $_REQUEST['show_severity'], PROFILE_TYPE_INT);
}
else {
	$_REQUEST['show_severity'] = CProfile::get('web.tr_status.filter.show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED);
}

// status change
if (isset($_REQUEST['status_change'])) {
	CProfile::update('web.tr_status.filter.status_change', $_REQUEST['status_change'], PROFILE_TYPE_INT);
}
else {
	if (isset($_REQUEST['filter_set'])) {
		CProfile::update('web.tr_status.filter.status_change', 0, PROFILE_TYPE_INT);
		$_REQUEST['status_change'] = 0;
	}
	else {
		$_REQUEST['status_change'] = CProfile::get('web.tr_status.filter.status_change', 0);
	}
}

// status change days
if (isset($_REQUEST['status_change_days'])) {
	$maxDays = DAY_IN_YEAR * 2;

	if ($_REQUEST['status_change_days'] > $maxDays) {
		$_REQUEST['status_change_days'] = $maxDays;
	}

	CProfile::update('web.tr_status.filter.status_change_days', $_REQUEST['status_change_days'], PROFILE_TYPE_INT);
}
else {
	$_REQUEST['status_change_days'] = CProfile::get('web.tr_status.filter.status_change_days');

	if (!$_REQUEST['status_change_days']) {
		$_REQUEST['status_change_days'] = 14;
	}
}

// ack status
if (isset($_REQUEST['ack_status'])) {
	if ($config['event_ack_enable'] == EVENT_ACK_DISABLED) {
		$_REQUEST['ack_status'] = ZBX_ACK_STS_ANY;
	}

	CProfile::update('web.tr_status.filter.ack_status', $_REQUEST['ack_status'], PROFILE_TYPE_INT);
}
else {
	$_REQUEST['ack_status'] = ($config['event_ack_enable'] == EVENT_ACK_DISABLED)
		? ZBX_ACK_STS_ANY
		: CProfile::get('web.tr_status.filter.ack_status', ZBX_ACK_STS_ANY);
}

// txt select
if (isset($_REQUEST['txt_select'])) {
	CProfile::update('web.tr_status.filter.txt_select', $_REQUEST['txt_select'], PROFILE_TYPE_STR);
}
else {
	$_REQUEST['txt_select'] = CProfile::get('web.tr_status.filter.txt_select', '');
}

/*
 * Clean cookies
 */
if (get_request('show_events') != CProfile::get('web.tr_status.filter.show_events')) {
	clearCookies(true);
}

/*
 * Page sorting
 */
validate_sort_and_sortorder('lastchange', ZBX_SORT_DOWN);

/*
 * Play sound
 */
$mute = CProfile::get('web.tr_status.mute', 0);
if (isset($audio) && !$mute) {
	play_sound($audio);
}

/*
 * Display
 */
$displayNodes = (is_show_all_nodes() && $pageFilter->groupid == 0 && $pageFilter->hostid == 0);

$showTriggers = $_REQUEST['show_triggers'];
$showEvents = $_REQUEST['show_events'];
$showSeverity = $_REQUEST['show_severity'];
$ackStatus = $_REQUEST['ack_status'];

$triggerWidget = new CWidget();

$rightForm = new CForm('get');
$rightForm->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB(true)));
$rightForm->addItem(array(SPACE._('Host').SPACE, $pageFilter->getHostsCB(true)));
$rightForm->addVar('fullscreen', $_REQUEST['fullscreen']);

$triggerWidget->addPageHeader(
	_('STATUS OF TRIGGERS').SPACE.'['.zbx_date2str(_('d M Y H:i:s')).']',
	get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']))
);
$triggerWidget->addHeader(_('Triggers'), $rightForm);
$triggerWidget->addHeaderRowNumber();

/*
 * Filter
 */
$filterForm = new CFormTable(null, null, 'get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('fullscreen', $_REQUEST['fullscreen']);
$filterForm->addVar('groupid', $_REQUEST['groupid']);
$filterForm->addVar('hostid', $_REQUEST['hostid']);

$statusComboBox = new CComboBox('show_triggers', $showTriggers);
$statusComboBox->addItem(TRIGGERS_OPTION_ALL, _('Any'));
$statusComboBox->additem(TRIGGERS_OPTION_ONLYTRUE, _('Problem'));
$filterForm->addRow(_('Triggers status'), $statusComboBox);

if ($config['event_ack_enable']) {
	$ackStatusComboBox = new CComboBox('ack_status', $ackStatus);
	$ackStatusComboBox->addItem(ZBX_ACK_STS_ANY, _('Any'));
	$ackStatusComboBox->additem(ZBX_ACK_STS_WITH_UNACK, _('With unacknowledged events'));
	$ackStatusComboBox->additem(ZBX_ACK_STS_WITH_LAST_UNACK, _('With last event unacknowledged'));
	$filterForm->addRow(_('Acknowledge status'), $ackStatusComboBox);
}

$eventsComboBox = new CComboBox('show_events', $_REQUEST['show_events']);
$eventsComboBox->addItem(EVENTS_OPTION_NOEVENT, _('Hide all'));
$eventsComboBox->addItem(EVENTS_OPTION_ALL, _('Show all').' ('.$config['event_expire'].' '.(($config['event_expire'] > 1) ? _('Days') : _('Day')).')');
if ($config['event_ack_enable']) {
	$eventsComboBox->addItem(EVENTS_OPTION_NOT_ACK, _('Show unacknowledged').' ('.$config['event_expire'].' '.(($config['event_expire'] > 1) ? _('Days') : _('Day')).')');
}
$filterForm->addRow(_('Events'), $eventsComboBox);

$severityComboBox = new CComboBox('show_severity', $showSeverity);
$severityComboBox->addItems(array(
	TRIGGER_SEVERITY_NOT_CLASSIFIED => getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED),
	TRIGGER_SEVERITY_INFORMATION => getSeverityCaption(TRIGGER_SEVERITY_INFORMATION),
	TRIGGER_SEVERITY_WARNING => getSeverityCaption(TRIGGER_SEVERITY_WARNING),
	TRIGGER_SEVERITY_AVERAGE => getSeverityCaption(TRIGGER_SEVERITY_AVERAGE),
	TRIGGER_SEVERITY_HIGH => getSeverityCaption(TRIGGER_SEVERITY_HIGH),
	TRIGGER_SEVERITY_DISASTER => getSeverityCaption(TRIGGER_SEVERITY_DISASTER)
));
$filterForm->addRow(_('Minimum trigger severity'), $severityComboBox);

$statusChangeDays = new CNumericBox('status_change_days', $_REQUEST['status_change_days'], 3, false, false, false);
if (!$_REQUEST['status_change']) {
	$statusChangeDays->setAttribute('disabled', 'disabled');
}
$statusChangeDays->addStyle('vertical-align: middle;');

$statusChangeCheckBox = new CCheckBox('status_change', $_REQUEST['status_change'], 'javascript: this.checked ? $("status_change_days").enable() : $("status_change_days").disable()', 1);
$statusChangeCheckBox->addStyle('vertical-align: middle;');

$daysSpan = new CSpan(_('days'));
$daysSpan->addStyle('vertical-align: middle;');
$filterForm->addRow(_('Age less than'), array($statusChangeCheckBox, $statusChangeDays, SPACE, $daysSpan));
$filterForm->addRow(_('Show details'), new CCheckBox('show_details', $_REQUEST['show_details'], null, 1));
$filterForm->addRow(_('Filter by name'), new CTextBox('txt_select', $_REQUEST['txt_select'], 40));
$filterForm->addRow(_('Show hosts in maintenance'), new CCheckBox('show_maintenance', $_REQUEST['show_maintenance'], null, 1));

$filterForm->addItemToBottomRow(new CSubmit('filter_set', _('Filter'), 'chkbxRange.clearSelectedOnFilterChange();'));
$filterForm->addItemToBottomRow(new CSubmit('filter_rst', _('Reset'), 'chkbxRange.clearSelectedOnFilterChange();'));

$triggerWidget->addFlicker($filterForm, CProfile::get('web.tr_status.filter.state', 0));

/*
 * Form
 */
if ($_REQUEST['fullscreen']) {
	$triggerInfo = new CTriggersInfo($_REQUEST['groupid'], $_REQUEST['hostid']);
	$triggerInfo->hideHeader();
	$triggerInfo->show();
}

$triggerForm = new CForm('get', 'acknow.php');
$triggerForm->setName('tr_status');
$triggerForm->addVar('backurl', $page['file']);

/*
 * Table
 */
$showEventColumn = ($config['event_ack_enable'] && $_REQUEST['show_events'] != EVENTS_OPTION_NOEVENT);

$switcherName = 'trigger_switchers';

$headerCheckBox = ($showEventColumn)
	? new CCheckBox('all_events', false, "checkAll('".$triggerForm->GetName()."', 'all_events', 'events');")
	: new CCheckBox('all_triggers', false, "checkAll('".$triggerForm->GetName()."', 'all_triggers', 'triggers');");

if ($showEvents != EVENTS_OPTION_NOEVENT) {
	$showHideAllDiv = new CDiv(SPACE, 'filterclosed');
	$showHideAllDiv->setAttribute('id', $switcherName);
}
else {
	$showHideAllDiv = null;
}

$triggerTable = new CTableInfo(_('No triggers found.'));
$triggerTable->setHeader(array(
	$showHideAllDiv,
	$config['event_ack_enable'] ? $headerCheckBox : null,
	make_sorting_header(_('Severity'), 'priority'),
	_('Status'),
	_('Info'),
	make_sorting_header(_('Last change'), 'lastchange'),
	_('Age'),
	$showEventColumn ? _('Duration') : null,
	$config['event_ack_enable'] ? _('Acknowledged') : null,
	$displayNodes ? _('Node') : null,
	_('Host'),
	make_sorting_header(_('Name'), 'description'),
	_('Comments')
));

// get triggers
$sortfield = getPageSortField('description');
$sortorder = getPageSortOrder();
$options = array(
	'nodeids' => get_current_nodeid(),
	'monitored' => true,
	'output' => array('triggerid', $sortfield),
	'skipDependent' => true,
	'limit' => $config['search_limit'] + 1
);

if ($pageFilter->hostsSelected) {
	if ($pageFilter->hostid > 0) {
		$options['hostids'] = $pageFilter->hostid;
	}
	elseif ($pageFilter->groupid > 0) {
		$options['groupids'] = $pageFilter->groupid;
	}
}
else {
	$options['hostids'] = array();
}

if (!zbx_empty($_REQUEST['txt_select'])) {
	$options['search'] = array('description' => $_REQUEST['txt_select']);
}
if ($showTriggers == TRIGGERS_OPTION_ONLYTRUE) {
	$options['only_true'] = 1;
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
if ($_REQUEST['status_change']) {
	$options['lastChangeSince'] = time() - $_REQUEST['status_change_days'] * SEC_PER_DAY;
}
if (!get_request('show_maintenance')) {
	$options['maintenance'] = false;
}
$triggers = API::Trigger()->get($options);

order_result($triggers, $sortfield, $sortorder);
$paging = getPagingLine($triggers);


$triggers = API::Trigger()->get(array(
	'nodeids' => get_current_nodeid(),
	'triggerids' => zbx_objectValues($triggers, 'triggerid'),
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => array('hostid', 'name', 'maintenance_status', 'maintenance_type', 'maintenanceid', 'description'),
	'selectItems' => array('itemid', 'hostid', 'key_', 'name', 'value_type'),
	'selectDependencies' => API_OUTPUT_EXTEND,
	'selectLastEvent' => true,
	'expandDescription' => true,
	'preservekeys' => true
));

order_result($triggers, $sortfield, $sortorder);

$triggerIds = zbx_objectValues($triggers, 'triggerid');

// get editable triggers
$triggerEditable = API::Trigger()->get(array(
	'triggerids' => $triggerIds,
	'output' => array('triggerid'),
	'editable' => true,
	'preservekeys' => true
));

// get events
if ($config['event_ack_enable']) {
	// get all unacknowledged events, if trigger has unacknowledged even => it has events
	$eventCounts = API::Event()->get(array(
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'countOutput' => true,
		'groupCount' => true,
		'objectids' => $triggerIds,
		'filter' => array(
			'acknowledged' => 0,
			'value' => TRIGGER_VALUE_TRUE
		)
	));
	foreach ($eventCounts as $eventCount) {
		$triggers[$eventCount['objectid']]['hasEvents'] = true;
		$triggers[$eventCount['objectid']]['event_count'] = $eventCount['rowscount'];
	}

	// gather ids of triggers which don't have unack. events
	$triggerIdsWithoutUnackEvents = array();
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
		$allEventCounts = API::Event()->get(array(
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'countOutput' => true,
			'groupCount' => true,
			'objectids' => $triggerIdsWithoutUnackEvents
		));
		$allEventCounts = zbx_toHash($allEventCounts, 'objectid');

		foreach ($triggers as $tnum => $trigger) {
			if (!isset($trigger['hasEvents'])) {
				$triggers[$tnum]['hasEvents'] = isset($allEventCounts[$trigger['triggerid']]);
			}
		}
	}
}

if ($showEvents != EVENTS_OPTION_NOEVENT) {
	$options = array(
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'nodeids' => get_current_nodeid(),
		'objectids' => zbx_objectValues($triggers, 'triggerid'),
		'output' => API_OUTPUT_EXTEND,
		'select_acknowledges' => API_OUTPUT_COUNT,
		'time_from' => time() - $config['event_expire'] * SEC_PER_DAY,
		'time_till' => time(),
		'sortfield' => array('clock', 'eventid'),
		'sortorder' => ZBX_SORT_DOWN
	);

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
$hostIds = array();
foreach ($triggers as $tnum => $trigger) {
	foreach ($trigger['hosts'] as $host) {
		$hostIds[$host['hostid']] = $host['hostid'];
	}
}

// get hosts
$hosts = API::Host()->get(array(
	'hostids' => $hostIds,
	'preservekeys' => true,
	'selectScreens' => API_OUTPUT_COUNT
));

// get host scripts
$scriptsByHosts = API::Script()->getScriptsByHosts($hostIds);

// get trigger dependencies
$dbTriggerDependencies = DBselect(
	'SELECT triggerid_down,triggerid_up'.
	' FROM trigger_depends'.
	' WHERE '.dbConditionInt('triggerid_up', $triggerIds)
);
$triggerIdsDown = array();
while ($row = DBfetch($dbTriggerDependencies)) {
	$triggerIdsDown[$row['triggerid_up']][] = intval($row['triggerid_down']);
}

foreach ($triggers as $trigger) {
	$usedHosts = array();
	foreach ($trigger['hosts'] as $host) {
		$usedHosts[$host['hostid']] = $host['name'];
	}
	$usedHostCount = count($usedHosts);

	$triggerItems = array();

	$trigger['items'] = CMacrosResolverHelper::resolveItemNames($trigger['items']);

	foreach ($trigger['items'] as $item) {
		$triggerItems[] = array(
			'name' => ($usedHostCount > 1) ? $usedHosts[$item['hostid']].NAME_DELIMITER.$item['name_expanded'] : $item['name_expanded'],
			'params' => array(
				'itemid' => $item['itemid'],
				'action' => in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
					? 'showgraph' : 'showvalues'
			)
		);
	}

	$description = new CSpan($trigger['description'], 'link_menu');
	$description->setMenuPopup(getMenuPopupTrigger($trigger, $triggerItems));

	if ($_REQUEST['show_details']) {
		$description = array($description, BR(), explode_exp($trigger['expression'], true, true));
	}

	if (!empty($trigger['dependencies'])) {
		$dependenciesTable = new CTableInfo();
		$dependenciesTable->setAttribute('style', 'width: 200px;');
		$dependenciesTable->addRow(bold(_('Depends on').NAME_DELIMITER));

		foreach ($trigger['dependencies'] as $dependency) {
			$dependenciesTable->addRow(' - '.CMacrosResolverHelper::resolveTriggerNameById($dependency['triggerid']));
		}

		$img = new Cimg('images/general/arrow_down2.png', 'DEP_UP');
		$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
		$img->setHint($dependenciesTable);

		$description = array($img, SPACE, $description);
	}

	$dependency = false;
	$dependenciesTable = new CTableInfo();
	$dependenciesTable->setAttribute('style', 'width: 200px;');
	$dependenciesTable->addRow(bold(_('Dependent').NAME_DELIMITER));
	if (!empty($triggerIdsDown[$trigger['triggerid']])) {
		$depTriggers = CMacrosResolverHelper::resolveTriggerNameByIds($triggerIdsDown[$trigger['triggerid']]);

		foreach ($depTriggers as $depTrigger) {
			$dependenciesTable->addRow(SPACE.'-'.SPACE.$depTrigger['description']);
			$dependency = true;
		}
	}

	if ($dependency) {
		$img = new Cimg('images/general/arrow_up2.png', 'DEP_UP');
		$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
		$img->setHint($dependenciesTable);

		$description = array($img, SPACE, $description);
	}
	unset($img, $dependenciesTable, $dependency);

	$triggerDescription = new CSpan($description, 'pointer');

	// host js menu
	$hostList = array();
	foreach ($trigger['hosts'] as $triggerHost) {
		// fetch scripts for the host js menu
		$scripts = array();
		if (isset($scriptsByHosts[$triggerHost['hostid']])) {
			foreach ($scriptsByHosts[$triggerHost['hostid']] as $script) {
				$scripts[] = $script;
			}
		}

		$hostName = new CSpan($triggerHost['name'], 'link_menu');
		$hostName->setMenuPopup(getMenuPopupHost($hosts[$triggerHost['hostid']], $scripts));

		$hostDiv = new CDiv($hostName);

		// add maintenance icon with hint if host is in maintenance
		if ($triggerHost['maintenance_status']) {
			$maintenanceIcon = new CDiv(null, 'icon-maintenance-inline');

			$maintenances = API::Maintenance()->get(array(
				'maintenanceids' => $triggerHost['maintenanceid'],
				'output' => API_OUTPUT_EXTEND,
				'limit' => 1
			));

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

			$hostDiv->addItem($maintenanceIcon);
		}

		// add comma after hosts, except last
		if (next($trigger['hosts'])) {
			$hostDiv->addItem(','.SPACE);
		}

		$hostList[] = $hostDiv;
	}

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

	$lastChangeDate = zbx_date2str(_('d M Y H:i:s'), $trigger['lastchange']);
	$lastChange = empty($trigger['lastchange'])
		? $lastChangeDate
		: new CLink($lastChangeDate, 'events.php?triggerid='.$trigger['triggerid']);

	// acknowledge
	if ($config['event_ack_enable']) {
		if ($trigger['hasEvents']) {
			if ($trigger['event_count']) {
				$ackColumn = new CCol(array(
					new CLink(
						_('Acknowledge'),
						'acknow.php?'.
							'triggers[]='.$trigger['triggerid'].
							'&backurl='.$page['file'],
						'on'
					), ' ('.$trigger['event_count'].')'
				));
			}
			else {
				$ackColumn = new CCol(
					new CLink(
						_('Acknowledged'),
						'acknow.php?'.
							'eventid='.$trigger['lastEvent']['eventid'].
							'&triggerid='.$trigger['lastEvent']['objectid'].
							'&backurl='.$page['file'],
						'off'
				));
			}
		}
		else {
			$ackColumn = new CCol(_('No events'), 'unknown');
		}
	}
	else {
		$ackColumn = null;
	}

	// open or close
	if ($showEvents != EVENTS_OPTION_NOEVENT && !empty($trigger['events'])) {
		$openOrCloseDiv = new CDiv(SPACE, 'filterclosed');
		$openOrCloseDiv->setAttribute('data-switcherid', $trigger['triggerid']);
	}
	elseif ($showEvents == EVENTS_OPTION_NOEVENT) {
		$openOrCloseDiv = null;
	}
	else {
		$openOrCloseDiv = SPACE;
	}

	// severity
	$severityColumn = getSeverityCell($trigger['priority'], null, !$trigger['value']);
	if ($showEventColumn) {
		$severityColumn->setColSpan(2);
	}

	// unknown triggers
	$unknown = SPACE;
	if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
		$unknown = new CDiv(SPACE, 'status_icon iconunknown');
		$unknown->setHint($trigger['error'], '', 'on');
	}

	// comments
	if (isset($triggerEditable[$trigger['triggerid']])) {
		$comments = new CLink(zbx_empty($trigger['comments']) ? _('Add') : _('Show'), 'tr_comments.php?triggerid='.$trigger['triggerid']);
	}
	else {
		$comments = zbx_empty($trigger['comments'])
			? new CSpan('-')
			: new CLink(_('Show'), 'tr_comments.php?triggerid='.$trigger['triggerid']);
	}

	$triggerTable->addRow(array(
		$openOrCloseDiv,
		$config['event_ack_enable'] ?
			($showEventColumn ? null : new CCheckBox('triggers['.$trigger['triggerid'].']', 'no', null, $trigger['triggerid'])) : null,
		$severityColumn,
		$statusSpan,
		$unknown,
		$lastChange,
		empty($trigger['lastchange']) ? '-' : zbx_date2age($trigger['lastchange']),
		$showEventColumn ? SPACE : null,
		$ackColumn,
		$displayNodes ? get_node_name_by_elid($trigger['triggerid']) : null,
		$hostColumn,
		$triggerDescription,
		$comments
	), 'even_row');

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

			$ack = getEventAckState($event, true);

			$ackCheckBox = ($event['acknowledged'] == 0 && $event['value'] == TRIGGER_VALUE_TRUE)
				? new CCheckBox('events['.$event['eventid'].']', 'no', null, $event['eventid'])
				: SPACE;

			$clock = new CLink(zbx_date2str(_('d M Y H:i:s'), $event['clock']),
				'tr_events.php?triggerid='.$trigger['triggerid'].'&eventid='.$event['eventid']);

			$nextClock = isset($trigger['events'][$enum - 1]) ? $trigger['events'][$enum - 1]['clock'] : time();

			$emptyColumn = new CCol(SPACE);
			$emptyColumn->setColSpan(3);
			$ackCheckBoxColumn = new CCol($ackCheckBox);
			$ackCheckBoxColumn->setColSpan(2);

			$row = new CRow(array(
				SPACE,
				$config['event_ack_enable'] ? $ackCheckBoxColumn : null,
				$statusSpan,
				$clock,
				zbx_date2age($event['clock']),
				zbx_date2age($nextClock, $event['clock']),
				($config['event_ack_enable']) ? $ack : null,
				$displayNodes ? SPACE : null,
				$emptyColumn
			), 'odd_row');
			$row->setAttribute('data-parentid', $trigger['triggerid']);
			$row->addStyle('display: none;');
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
	$goComboBox = new CComboBox('go');
	$goComboBox->addItem('bulkacknowledge', _('Bulk acknowledge'));

	$goButton = new CSubmit('goButton', _('Go').' (0)');
	$goButton->setAttribute('id', 'goButton');

	$showEventColumn
		? zbx_add_post_js('chkbxRange.pageGoName = "events";')
		: zbx_add_post_js('chkbxRange.pageGoName = "triggers";');

	$footer = get_table_header(array($goComboBox, $goButton));
}

$triggerForm->addItem(array($paging, $triggerTable, $paging, $footer));
$triggerWidget->addItem($triggerForm);
$triggerWidget->show();

zbx_add_post_js('jqBlink.blink();');
zbx_add_post_js('var switcher = new CSwitcher(\''.$switcherName.'\');');

require_once dirname(__FILE__).'/include/page_footer.php';

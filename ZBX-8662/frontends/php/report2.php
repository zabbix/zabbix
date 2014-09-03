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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/reports.inc.php';

$page['title'] = _('Availability report');
$page['file'] = 'report2.php';
$page['hist_arg'] = array('mode', 'groupid', 'hostid', 'tpl_triggerid');
$page['scripts'] = array('class.calendar.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'mode' =>				array(T_ZBX_INT,	O_OPT,	P_SYS,			IN('0,1'),	null),
	'hostgroupid' =>		array(T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null),
	'tpl_triggerid' =>		array(T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null),
	'triggerid' =>			array(T_ZBX_INT,	O_OPT,	P_SYS|P_NZERO,	DB_ID,		null),
	// filter
	'filter_groupid'=>		array(T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null),
	'filter_hostid' =>		array(T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null),
	'filter_rst'=>			array(T_ZBX_STR,	O_OPT,	P_SYS,			null,		null),
	'filter_set' =>			array(T_ZBX_STR,	O_OPT,	P_SYS,			null,		null),
	'filter_timesince' =>	array(T_ZBX_STR,	O_OPT,	P_UNSET_EMPTY,	null,		null),
	'filter_timetill' =>	array(T_ZBX_STR,	O_OPT,	P_UNSET_EMPTY,	null,		null),
	// ajax
	'filterState' =>		array(T_ZBX_INT,	O_OPT,	P_ACT,			null,		null)
);
check_fields($fields);

$availabilityReportMode = getRequest('mode', CProfile::get('web.avail_report.mode', AVAILABILITY_REPORT_BY_HOST));
CProfile::update('web.avail_report.mode', $availabilityReportMode, PROFILE_TYPE_INT);

/*
 * Permissions
 */
if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
	if (getRequest('hostgroupid') && !API::HostGroup()->isReadable(array($_REQUEST['hostgroupid']))
			|| getRequest('filter_groupid') && !API::HostGroup()->isReadable(array($_REQUEST['filter_groupid']))
			|| getRequest('filter_hostid') && !API::Host()->isReadable(array($_REQUEST['filter_hostid']))) {
		access_deny();
	}
	if (getRequest('tpl_triggerid')) {
		$trigger = API::Trigger()->get(array(
			'triggerids' => $_REQUEST['tpl_triggerid'],
			'output' => array('triggerid'),
			'filter' => array('flags' => null)
		));
		if (!$trigger) {
			access_deny();
		}
	}
}
else {
	if (getRequest('filter_groupid') && !API::HostGroup()->isReadable(array($_REQUEST['filter_groupid']))
			|| getRequest('filter_hostid') && !API::Host()->isReadable(array($_REQUEST['filter_hostid']))) {
		access_deny();
	}
}
if (getRequest('triggerid') && !API::Trigger()->isReadable(array($_REQUEST['triggerid']))) {
	access_deny();
}

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.avail_report.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Filter
 */
if (hasRequest('filter_rst')) {
	$_REQUEST['filter_groupid'] = 0;
	$_REQUEST['filter_hostid'] = 0;
	$_REQUEST['filter_timesince'] = 0;
	$_REQUEST['filter_timetill'] = 0;

	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		$_REQUEST['tpl_triggerid'] = 0;
		$_REQUEST['hostgroupid'] = 0;
	}
}

if (!hasRequest('filter_rst')) {
	$_REQUEST['filter_groupid'] = getRequest('filter_groupid',
		CProfile::get('web.avail_report.'.$availabilityReportMode.'.groupid', 0)
	);
	$_REQUEST['filter_hostid'] = getRequest('filter_hostid',
		CProfile::get('web.avail_report.'.$availabilityReportMode.'.hostid', 0)
	);
	$_REQUEST['filter_timesince'] = getRequest('filter_timesince',
		CProfile::get('web.avail_report.'.$availabilityReportMode.'.timesince', 0)
	);
	$_REQUEST['filter_timetill'] = getRequest('filter_timetill',
		CProfile::get('web.avail_report.'.$availabilityReportMode.'.timetill', 0)
	);
}

CProfile::update('web.avail_report.'.$availabilityReportMode.'.groupid', getRequest('filter_groupid', 0),
	PROFILE_TYPE_ID
);
CProfile::update('web.avail_report.'.$availabilityReportMode.'.timesince', getRequest('filter_timesince', 0),
	PROFILE_TYPE_STR
);
CProfile::update('web.avail_report.'.$availabilityReportMode.'.timetill', getRequest('filter_timetill', 0),
	PROFILE_TYPE_STR
);
CProfile::update('web.avail_report.'.$availabilityReportMode.'.hostid', getRequest('filter_hostid', 0),
	PROFILE_TYPE_ID
);

$config = select_config();

if ($_REQUEST['filter_timetill'] > 0 && $_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill']) {
	zbx_swap($_REQUEST['filter_timesince'], $_REQUEST['filter_timetill']);
}

$_REQUEST['filter_timesince'] = zbxDateToTime($_REQUEST['filter_timesince']
	? $_REQUEST['filter_timesince'] : date(TIMESTAMP_FORMAT_ZERO_TIME, time() - SEC_PER_DAY));
$_REQUEST['filter_timetill'] = zbxDateToTime($_REQUEST['filter_timetill']
	? $_REQUEST['filter_timetill'] : date(TIMESTAMP_FORMAT_ZERO_TIME, time()));

/*
 * Header
 */
$triggerData = isset($_REQUEST['triggerid'])
	? API::Trigger()->get(array(
		'triggerids' => $_REQUEST['triggerid'],
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => API_OUTPUT_EXTEND,
		'expandDescription' => true
	))
	: null;

$reportWidget = new CWidget();
$reportWidget->addPageHeader(_('AVAILABILITY REPORT'));

if ($triggerData) {
	$triggerData = reset($triggerData);
	$host = reset($triggerData['hosts']);

	$triggerData['hostid'] = $host['hostid'];
	$triggerData['hostname'] = $host['name'];

	$reportWidget->addHeader(array(
		new CLink($triggerData['hostname'], '?filter_groupid='.$_REQUEST['filter_groupid']),
		NAME_DELIMITER,
		$triggerData['description']
	), SPACE);

	$table = new CTableInfo(null, 'graph');
	$table->addRow(new CImg('chart4.php?triggerid='.$_REQUEST['triggerid']));

	$reportWidget->addItem(BR());
	$reportWidget->addItem($table);
	$reportWidget->show();
}
elseif (isset($_REQUEST['filter_hostid'])) {
	$modeComboBox = new CComboBox('mode', $availabilityReportMode, 'submit()');
	$modeComboBox->addItem(AVAILABILITY_REPORT_BY_HOST, _('By host'));
	$modeComboBox->addItem(AVAILABILITY_REPORT_BY_TEMPLATE, _('By trigger template'));

	$headerForm = new CForm('get');
	$headerForm->addItem($modeComboBox);

	$reportWidget->addHeader(_('Report'), array(_('Mode').SPACE, $headerForm));

	$triggerOptions = array(
		'output' => array('triggerid', 'description', 'expression', 'value'),
		'expandDescription' => true,
		'monitored' => true,
		'selectHosts' => array('name'),
		'filter' => array(),
		'hostids' => null,
		'limit' => $config['search_limit'] + 1
	);

	/*
	 * Filter
	 */
	$filterForm = new CFormTable();
	$filterForm->setTableClass('formtable old-filter');
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');
	$filterForm->addVar('config', $availabilityReportMode);
	$filterForm->addVar('filter_timesince', date(TIMESTAMP_FORMAT, $_REQUEST['filter_timesince']));
	$filterForm->addVar('filter_timetill', date(TIMESTAMP_FORMAT, $_REQUEST['filter_timetill']));

	// report by template
	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		// trigger options
		if (!empty($_REQUEST['filter_hostid']) || !$config['dropdown_first_entry']) {
			$hosts = API::Host()->get(array(
				'output' => array('hostid'),
				'templateids' => $_REQUEST['filter_hostid']
			));

			$triggerOptions['hostids'] = zbx_objectValues($hosts, 'hostid');
		}
		if (isset($_REQUEST['tpl_triggerid']) && !empty($_REQUEST['tpl_triggerid'])) {
			$triggerOptions['filter']['templateid'] = $_REQUEST['tpl_triggerid'];
		}
		if (isset($_REQUEST['hostgroupid']) && !empty($_REQUEST['hostgroupid'])) {
			$triggerOptions['groupids'] = $_REQUEST['hostgroupid'];
		}

		// filter template group
		$groupsComboBox = new CComboBox('filter_groupid', $_REQUEST['filter_groupid'], 'javascript: submit();');
		$groupsComboBox->addItem(0, _('all'));

		$groups = API::HostGroup()->get(array(
			'output' => array('groupid', 'name'),
			'templated_hosts' => true,
			'with_triggers' => true
		));
		order_result($groups, 'name');

		foreach ($groups as $group) {
			$groupsComboBox->addItem($group['groupid'], $group['name']);
		}
		$filterForm->addRow(_('Template group'), $groupsComboBox);

		// filter template
		$templateComboBox = new CComboBox('filter_hostid', $_REQUEST['filter_hostid'], 'javascript: submit();');
		$templateComboBox->addItem(0, _('all'));

		$templates = API::Template()->get(array(
			'output' => array('templateid', 'name'),
			'groupids' => empty($_REQUEST['filter_groupid']) ? null : $_REQUEST['filter_groupid'],
			'with_triggers' => true
		));
		order_result($templates, 'name');

		$templateIds = array();
		foreach ($templates as $template) {
			$templateIds[$template['templateid']] = $template['templateid'];

			$templateComboBox->addItem($template['templateid'], $template['name']);
		}
		$filterForm->addRow(_('Template'), $templateComboBox);

		// filter trigger
		$triggerComboBox = new CComboBox('tpl_triggerid', getRequest('tpl_triggerid', 0), 'javascript: submit()');
		$triggerComboBox->addItem(0, _('all'));

		$sqlCondition = empty($_REQUEST['filter_hostid'])
			? ' AND '.dbConditionInt('h.hostid', $templateIds)
			: ' AND h.hostid='.zbx_dbstr($_REQUEST['filter_hostid']);

		$sql =
			'SELECT DISTINCT t.triggerid,t.description,h.name'.
			' FROM triggers t,hosts h,items i,functions f'.
			' WHERE f.itemid=i.itemid'.
				' AND h.hostid=i.hostid'.
				' AND t.status='.TRIGGER_STATUS_ENABLED.
				' AND t.triggerid=f.triggerid'.
				' AND h.status='.HOST_STATUS_TEMPLATE.
				' AND i.status='.ITEM_STATUS_ACTIVE.
					$sqlCondition.
			' ORDER BY t.description';
		$triggers = DBfetchArrayAssoc(DBselect($sql), 'triggerid');

		foreach ($triggers as $trigger) {
			$templateName = empty($_REQUEST['filter_hostid']) ? $trigger['name'].NAME_DELIMITER : '';

			$triggerComboBox->addItem($trigger['triggerid'], $templateName.$trigger['description']);
		}

		if (isset($_REQUEST['tpl_triggerid']) && !isset($triggers[$_REQUEST['tpl_triggerid']])) {
			unset($triggerOptions['filter']['templateid']);
		}

		$filterForm->addRow(_('Template trigger'), $triggerComboBox);

		// filter host group
		$hostGroupsComboBox = new CComboBox('hostgroupid', getRequest('hostgroupid', 0), 'javascript: submit()');
		$hostGroupsComboBox->addItem(0, _('all'));

		$hostGroups = API::HostGroup()->get(array(
			'output' => array('groupid', 'name'),
			'hostids' => $triggerOptions['hostids'],
			'monitored_hosts' => true,
			'preservekeys' => true
		));
		order_result($hostGroups, 'name');

		foreach ($hostGroups as $hostGroup) {
			$hostGroupsComboBox->addItem($hostGroup['groupid'], $hostGroup['name']);
		}

		if (isset($_REQUEST['hostgroupid']) && !isset($hostGroups[$_REQUEST['hostgroupid']])) {
			unset($triggerOptions['groupids']);
		}

		$filterForm->addRow(_('Filter by host group'), $hostGroupsComboBox);
	}

	// report by host
	elseif ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
		// filter host group
		$groupsComboBox = new CComboBox('filter_groupid', $_REQUEST['filter_groupid'], 'javascript: submit();');
		$groupsComboBox->addItem(0, _('all'));

		$groups = API::HostGroup()->get(array(
			'output' => array('groupid', 'name'),
			'monitored_hosts' => true,
			'with_triggers' => true
		));
		order_result($groups, 'name');

		foreach ($groups as $group) {
			$groupsComboBox->addItem($group['groupid'], $group['name']);
		}
		$filterForm->addRow(_('Host group'), $groupsComboBox);

		// filter host
		$hostsComboBox = new CComboBox('filter_hostid', $_REQUEST['filter_hostid'], 'javascript: submit();');
		$hostsComboBox->addItem(0, _('all'));

		$hosts = API::Host()->get(array(
			'groupids' => empty($_REQUEST['filter_groupid']) ? null : $_REQUEST['filter_groupid'],
			'output' => array('hostid', 'name'),
			'monitored_hosts' => true,
			'with_triggers' => true
		));
		order_result($hosts, 'name');
		$hosts = zbx_toHash($hosts, 'hostid');

		foreach ($hosts as $host) {
			$hostsComboBox->addItem($host['hostid'], $host['name']);
		}
		$filterForm->addRow(_('Host'), $hostsComboBox);

		// trigger options
		if (!empty($_REQUEST['filter_groupid']) || !$config['dropdown_first_entry']) {
			$triggerOptions['groupids'] = $_REQUEST['filter_groupid'];
		}
		if (!empty($_REQUEST['filter_hostid']) && isset($hosts[$_REQUEST['filter_hostid']]) || !$config['dropdown_first_entry']) {
			$triggerOptions['hostids'] = $_REQUEST['filter_hostid'];
		}
	}

	// filter period
	$timeSinceRow = createDateSelector('filter_timesince', $_REQUEST['filter_timesince'], 'filter_timetill');
	array_unshift($timeSinceRow, _('From'));
	$timeTillRow = createDateSelector('filter_timetill', $_REQUEST['filter_timetill'], 'filter_timesince');
	array_unshift($timeTillRow, _('Till'));

	$filterPeriodTable = new CTable(null, 'calendar');
	$filterPeriodTable->addRow($timeSinceRow);
	$filterPeriodTable->addRow($timeTillRow);

	$filterForm->addRow(_('Period'), $filterPeriodTable);

	// filter buttons
	$filterForm->addItemToBottomRow(new CSubmit('filter_set',_('Filter')));
	$filterForm->addItemToBottomRow(new CSubmit('filter_rst', _('Reset')));

	$reportWidget->addFlicker($filterForm, CProfile::get('web.avail_report.filter.state', 0));

	/*
	 * Triggers
	 */
	$triggerTable = new CTableInfo(_('No triggers found.'));
	$triggerTable->setHeader(array(
		($_REQUEST['filter_hostid'] == 0 || $availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) ? _('Host') : null,
		_('Name'),
		_('Problems'),
		_('Ok'),
		_('Graph')
	));

	$triggers = API::Trigger()->get($triggerOptions);

	CArrayHelper::sort($triggers, array('host', 'description'));

	$paging = getPagingLine($triggers);

	foreach ($triggers as $trigger) {
		$availability = calculateAvailability($trigger['triggerid'], getRequest('filter_timesince'),
			getRequest('filter_timetill')
		);

		$triggerTable->addRow(array(
			($_REQUEST['filter_hostid'] == 0 || $availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE)
				? $trigger['hosts'][0]['name'] : null,
			new CLink($trigger['description'], 'events.php?filter_set=1&triggerid='.$trigger['triggerid'].
				'&source='.EVENT_SOURCE_TRIGGERS
			),
			new CSpan(sprintf('%.4f%%', $availability['true']), 'on'),
			new CSpan(sprintf('%.4f%%', $availability['false']), 'off'),
			new CLink(_('Show'), 'report2.php?filter_groupid='.$_REQUEST['filter_groupid'].
				'&filter_hostid='.$_REQUEST['filter_hostid'].'&triggerid='.$trigger['triggerid'])
		));
	}

	$reportWidget->addItem(BR());
	$reportWidget->addItem(array($paging, $triggerTable, $paging));
	$reportWidget->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

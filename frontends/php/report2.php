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
require_once dirname(__FILE__).'/include/hosts.inc.php';

$page['title'] = _('Availability report');
$page['file'] = 'report2.php';
$page['scripts'] = ['class.calendar.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'mode' =>				[T_ZBX_INT,	O_OPT,	P_SYS,			IN('0,1'),	null],
	'hostgroupid' =>		[T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null],
	'tpl_triggerid' =>		[T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null],
	'triggerid' =>			[T_ZBX_INT,	O_OPT,	P_SYS|P_NZERO,	DB_ID,		null],
	// filter
	'filter_groupid'=>		[T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null],
	'filter_hostid' =>		[T_ZBX_INT,	O_OPT,	P_SYS,			DB_ID,		null],
	'filter_rst'=>			[T_ZBX_STR,	O_OPT,	P_SYS,			null,		null],
	'filter_set' =>			[T_ZBX_STR,	O_OPT,	P_SYS,			null,		null],
	'filter_timesince' =>	[T_ZBX_STR,	O_OPT,	P_UNSET_EMPTY,	null,		null],
	'filter_timetill' =>	[T_ZBX_STR,	O_OPT,	P_UNSET_EMPTY,	null,		null]
];
check_fields($fields);

$availabilityReportMode = getRequest('mode', CProfile::get('web.avail_report.mode', AVAILABILITY_REPORT_BY_HOST));
CProfile::update('web.avail_report.mode', $availabilityReportMode, PROFILE_TYPE_INT);

/*
 * Permissions
 */
if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
	if (getRequest('hostgroupid') && !isReadableHostGroups([getRequest('hostgroupid')])) {
		access_deny();
	}
	if (getRequest('filter_groupid') && !isReadableHostGroups([getRequest('filter_groupid')])) {
		access_deny();
	}
	if (getRequest('filter_hostid') && !isReadableTemplates([getRequest('filter_hostid')])) {
		access_deny();
	}
	if (getRequest('tpl_triggerid')) {
		$trigger = API::Trigger()->get([
			'triggerids' => $_REQUEST['tpl_triggerid'],
			'output' => ['triggerid'],
			'filter' => ['flags' => null]
		]);
		if (!$trigger) {
			access_deny();
		}
	}
}
else {
	if (getRequest('filter_groupid') && !isReadableHostGroups([getRequest('filter_groupid')])) {
		access_deny();
	}
	if (getRequest('filter_hostid') && !isReadableHosts([getRequest('filter_hostid')])) {
		access_deny();
	}
}
if (getRequest('triggerid') && !isReadableTriggers([getRequest('triggerid')])) {
	access_deny();
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

	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		$_REQUEST['tpl_triggerid'] = getRequest('tpl_triggerid',
			CProfile::get('web.avail_report.'.$availabilityReportMode.'.tpl_triggerid', 0)
		);

		$_REQUEST['hostgroupid'] = getRequest('hostgroupid',
			CProfile::get('web.avail_report.'.$availabilityReportMode.'.hostgroupid', 0)
		);
	}
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

if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
	CProfile::update('web.avail_report.'.$availabilityReportMode.'.tpl_triggerid', getRequest('tpl_triggerid', 0),
		PROFILE_TYPE_ID
	);

	CProfile::update('web.avail_report.'.$availabilityReportMode.'.hostgroupid', getRequest('hostgroupid', 0),
		PROFILE_TYPE_ID
	);
}

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
	? API::Trigger()->get([
		'triggerids' => $_REQUEST['triggerid'],
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => API_OUTPUT_EXTEND,
		'expandDescription' => true
	])
	: null;

$reportWidget = (new CWidget())->setTitle(_('Availability report'));

if ($triggerData) {
	$triggerData = reset($triggerData);
	$host = reset($triggerData['hosts']);

	$triggerData['hostid'] = $host['hostid'];
	$triggerData['hostname'] = $host['name'];

	$reportWidget->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CLink($triggerData['hostname'], '?filter_groupid='.$_REQUEST['filter_groupid']))
			->addItem($triggerData['description'])
		))
			->setAttribute('aria-label', _('Content controls'))
	);

	$table = (new CTableInfo())
		->addRow(new CImg('chart4.php?triggerid='.$_REQUEST['triggerid']));

	$reportWidget->addItem(BR())
		->addItem($table)
		->show();
}
elseif (isset($_REQUEST['filter_hostid'])) {
	$reportWidget->setControls((new CForm('get'))
		->setAttribute('aria-label', _('Main filter'))
		->addItem((new CList())
			->addItem([
				new CLabel(_('Mode'), 'mode'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CComboBox('mode', $availabilityReportMode, 'submit()', [
					AVAILABILITY_REPORT_BY_HOST => _('By host'),
					AVAILABILITY_REPORT_BY_TEMPLATE => _('By trigger template')
				])
			])
	));

	$triggerOptions = [
		'output' => ['triggerid', 'description', 'expression', 'value'],
		'expandDescription' => true,
		'monitored' => true,
		'selectHosts' => ['name'],
		'filter' => [],
		'hostids' => null,
		'limit' => $config['search_limit'] + 1
	];

	/*
	 * Filter
	 */
	$filterForm = (new CFilter('web.avail_report.filter.state'))
		->addFormItem((new CVar('config', $availabilityReportMode))->removeId())
		->addVar('filter_timesince', date(TIMESTAMP_FORMAT, $_REQUEST['filter_timesince']))
		->addVar('filter_timetill', date(TIMESTAMP_FORMAT, $_REQUEST['filter_timetill']));

	$filterColumn1 = new CFormList();
	$filterColumn2 = new CFormList();

	// report by template
	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		// trigger options
		if (!empty($_REQUEST['filter_hostid'])) {
			$hosts = API::Host()->get([
				'output' => ['hostid'],
				'templateids' => $_REQUEST['filter_hostid']
			]);

			$triggerOptions['hostids'] = zbx_objectValues($hosts, 'hostid');
		}
		if (isset($_REQUEST['tpl_triggerid']) && !empty($_REQUEST['tpl_triggerid'])) {
			$triggerOptions['filter']['templateid'] = $_REQUEST['tpl_triggerid'];
		}

		// filter template group
		$groupsComboBox = (new CComboBox('filter_groupid', $_REQUEST['filter_groupid'], 'javascript: submit();'))
				->setAttribute('autofocus', 'autofocus');
		$groupsComboBox->addItem(0, _('all'));

		$groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'templated_hosts' => true,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		$groups = CPageFilter::enrichParentGroups($groups);

		order_result($groups, 'name');

		foreach ($groups as $group) {
			$groupsComboBox->addItem($group['groupid'], $group['name']);
		}
		$filterColumn1->addRow(_('Template group'), $groupsComboBox);

		// filter template
		$templateComboBox = new CComboBox('filter_hostid', $_REQUEST['filter_hostid'], 'javascript: submit();');
		$templateComboBox->addItem(0, _('all'));

		if (getRequest('filter_groupid')) {
			$filter_groupids = [getRequest('filter_groupid')];
			$parent = $groups[getRequest('filter_groupid')]['name'].'/';
			foreach ($groups as $group) {
				if (strpos($group['name'], $parent) === 0) {
					$filter_groupids[] = $group['groupid'];
				}
			}
		}
		else {
			$filter_groupids = null;
		}

		$templates = API::Template()->get([
			'output' => ['templateid', 'name'],
			'groupids' => $filter_groupids,
			'with_triggers' => true
		]);
		order_result($templates, 'name');

		$templateIds = [];
		foreach ($templates as $template) {
			$templateIds[$template['templateid']] = $template['templateid'];

			$templateComboBox->addItem($template['templateid'], $template['name']);
		}
		$filterColumn1->addRow(_('Template'), $templateComboBox);

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

		$filterColumn1->addRow(_('Template trigger'), $triggerComboBox);

		// filter host group
		$hostGroupsComboBox = new CComboBox('hostgroupid', getRequest('hostgroupid', 0), 'javascript: submit()');
		$hostGroupsComboBox->addItem(0, _('all'));

		$groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'hostids' => $triggerOptions['hostids'],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);
		$groups = CPageFilter::enrichParentGroups($groups);

		order_result($groups, 'name');

		if (hasRequest('hostgroupid') && getRequest('hostgroupid')) {
			$triggerOptions['groupids'] = [getRequest('hostgroupid')];
			if (array_key_exists(getRequest('hostgroupid'), $groups)) {
				$parent = $groups[getRequest('hostgroupid')]['name'].'/';
				foreach ($groups as $group) {
					if (strpos($group['name'], $parent) === 0) {
						$triggerOptions['groupids'][] = $group['groupid'];
					}
				}
			}
		}
		else {
			$triggerOptions['groupids'] = null;
		}

		foreach ($groups as $group) {
			$hostGroupsComboBox->addItem($group['groupid'], $group['name']);
		}

		if (isset($_REQUEST['hostgroupid']) && !isset($groups[$_REQUEST['hostgroupid']])) {
			unset($triggerOptions['groupids']);
		}

		$filterColumn1->addRow(_('Host group'), $hostGroupsComboBox);
	}

	// report by host
	elseif ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
		// filter host group
		$groupsComboBox = (new CComboBox('filter_groupid', $_REQUEST['filter_groupid'], 'javascript: submit();'))
				->setAttribute('autofocus', 'autofocus');
		$groupsComboBox->addItem(0, _('all'));

		$groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'monitored_hosts' => true,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		$groups = CPageFilter::enrichParentGroups($groups);

		order_result($groups, 'name');

		foreach ($groups as $group) {
			$groupsComboBox->addItem($group['groupid'], $group['name']);
		}
		$filterColumn1->addRow(_('Host group'), $groupsComboBox);

		// filter host
		$hostsComboBox = new CComboBox('filter_hostid', $_REQUEST['filter_hostid'], 'javascript: submit();');
		$hostsComboBox->addItem(0, _('all'));
		if (getRequest('filter_groupid')) {
			$filter_groupids = [getRequest('filter_groupid')];
			$parent = $groups[getRequest('filter_groupid')]['name'].'/';
			foreach ($groups as $group) {
				if (strpos($group['name'], $parent) === 0) {
					$filter_groupids[] = $group['groupid'];
				}
			}
		}
		else {
			$filter_groupids = null;
		}

		$hosts = API::Host()->get([
			'groupids' => $filter_groupids,
			'output' => ['hostid', 'name'],
			'monitored_hosts' => true,
			'with_triggers' => true
		]);
		order_result($hosts, 'name');
		$hosts = zbx_toHash($hosts, 'hostid');

		foreach ($hosts as $host) {
			$hostsComboBox->addItem($host['hostid'], $host['name']);
		}
		$filterColumn1->addRow(_('Host'), $hostsComboBox);

		// trigger options
		$triggerOptions['groupids'] = $filter_groupids;
		if (!empty($_REQUEST['filter_hostid']) && isset($hosts[$_REQUEST['filter_hostid']])) {
			$triggerOptions['hostids'] = $_REQUEST['filter_hostid'];
		}
	}

	// filter period
	$filterColumn2->addRow(_('From'), createDateSelector('filter_timesince', $_REQUEST['filter_timesince']));
	$filterColumn2->addRow(_('To'), createDateSelector('filter_timetill', $_REQUEST['filter_timetill']));

	$filterForm->addColumn($filterColumn1);
	$filterForm->addColumn($filterColumn2);

	$reportWidget->addItem($filterForm);

	/*
	 * Triggers
	 */
	$triggerTable = (new CTableInfo())
		->setHeader([
			_('Host'),
			_('Name'),
			_('Problems'),
			_('Ok'),
			_('Graph')
		]);

	$triggers = API::Trigger()->get($triggerOptions);

	foreach ($triggers as &$trigger) {
		$trigger['host_name'] = $trigger['hosts'][0]['name'];
	}
	unset($trigger);

	CArrayHelper::sort($triggers, ['host_name', 'description']);

	$paging = getPagingLine($triggers, ZBX_SORT_UP, new CUrl('report2.php'));

	foreach ($triggers as $trigger) {
		$availability = calculateAvailability($trigger['triggerid'], getRequest('filter_timesince'),
			getRequest('filter_timetill')
		);

		$triggerTable->addRow([
			$trigger['host_name'],
			new CLink($trigger['description'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_triggerids[]', $trigger['triggerid'])
					->setArgument('filter_set', '1')
			),
			$availability['true'] == 0 ? '' : (new CSpan(sprintf('%.4f%%', $availability['true'])))->addClass(ZBX_STYLE_RED),
			$availability['false'] == 0 ? '' : (new CSpan(sprintf('%.4f%%', $availability['false'])))->addClass(ZBX_STYLE_GREEN),
			new CLink(_('Show'), 'report2.php?filter_groupid='.$_REQUEST['filter_groupid'].
				'&filter_hostid='.$_REQUEST['filter_hostid'].'&triggerid='.$trigger['triggerid'])
		]);
	}

	$reportWidget->addItem([$triggerTable, $paging])
		->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

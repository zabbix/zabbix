<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

$page['title'] = _('Availability report');
$page['file'] = 'report2.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'mode' =>			[T_ZBX_INT,			O_OPT,	P_SYS,			IN('0,1'),	null],
	'hostgroupid' =>	[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'tpl_triggerid' =>	[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'triggerid' =>		[T_ZBX_INT,			O_OPT,	P_SYS|P_NZERO,	DB_ID,		null],
	// filter
	'filter_groupid' =>	[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'filter_hostid' =>	[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'filter_rst'=>		[T_ZBX_STR,			O_OPT,	P_SYS,			null,		null],
	'filter_set' =>		[T_ZBX_STR,			O_OPT,	P_SYS,			null,		null],
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT,	P_SYS,			null,		null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT,	P_SYS,			null,		null],
];
check_fields($fields);
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

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

$timeselector_options = [
	'profileIdx' => 'web.avail_report.filter',
	'profileIdx2' => 0,
	'from' => getRequest('from'),
	'to' => getRequest('to')
];
updateTimeSelectorPeriod($timeselector_options);

$config = select_config();

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
elseif (hasRequest('filter_hostid')) {
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

	/*
	 * Filter
	 */
	$data = [
		'filter' => [
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'active_tab' => CProfile::get('web.avail_report.filter.active', 1)
		]
	];

	$filter_column = new CFormList();

	$filter_hostid = getRequest('filter_hostid');
	$filter_groupid = getRequest('filter_groupid');
	$tpl_triggerid = getRequest('tpl_triggerid');
	$hostgroupid = getRequest('hostgroupid');

	// Sanitize $filter_groupid and prepare "Template group" or "Host group" combo box (for both view modes).

	$options = [
		'output' => ['name'],
		'with_triggers' => true,
		'preservekeys' => true
	];

	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		$options['templated_hosts'] = true;
	}
	elseif ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
		$options['monitored_hosts'] = true;
	}

	$groups = API::HostGroup()->get($options);
	$groups = enrichParentGroups($groups);
	CArrayHelper::sort($groups, ['name']);

	if (!array_key_exists($filter_groupid, $groups)) {
		$filter_groupid = 0;
	}

	$filter_groupid_combobox = (new CComboBox('filter_groupid', $filter_groupid, 'javascript: submit();'))
		->setAttribute('autofocus', 'autofocus')
		->addItem(0, _('all'));

	foreach ($groups as $groupid => $group) {
		$filter_groupid_combobox->addItem($groupid, $group['name']);
	}

	if ($filter_groupid == 0) {
		$filter_groupids = null;
	}
	else {
		$filter_groupids = [$filter_groupid];
		$parent = $groups[$filter_groupid]['name'].'/';

		foreach ($groups as $groupid => $group) {
			if (strpos($group['name'], $parent) === 0) {
				$filter_groupids[] = $groupid;
			}
		}
	}

	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		// Sanitize $filter_hostid and prepare "Template" combo box.

		$templates = API::Template()->get([
			'output' => ['name'],
			'groupids' => $filter_groupids,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		CArrayHelper::sort($templates, ['name']);

		if (!array_key_exists($filter_hostid, $templates)) {
			$filter_hostid = 0;
		}

		$filter_hostid_combobox = (new CComboBox('filter_hostid', $filter_hostid, 'javascript: submit();'))
			->addItem(0, _('all'));

		foreach ($templates as $templateid => $template) {
			$filter_hostid_combobox->addItem($templateid, $template['name']);
		}

		// Sanitize $tpl_triggerid and prepare "Template Trigger" combo box.

		$triggers = API::Trigger()->get([
			'output' => ['description'],
			'selectHosts' => ['name'],
			'selectItems' => ['status'],
			'templateids' => ($filter_hostid == 0) ? null : $filter_hostid,
			'groupids' => $filter_groupids,
			'templated' => true,
			'filter' => ['status' => TRIGGER_STATUS_ENABLED, 'flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
			'sortfield' => 'description',
			'preservekeys' => true
		]);

		foreach ($triggers as $triggerid => $trigger) {
			foreach ($trigger['items'] as $item) {
				if ($item['status'] != ITEM_STATUS_ACTIVE) {
					unset($triggers[$triggerid]);

					break;
				}
			}
		}

		if (!array_key_exists($tpl_triggerid, $triggers)) {
			$tpl_triggerid = 0;
		}

		$tpl_triggerid_combobox = (new CComboBox('tpl_triggerid', $tpl_triggerid, 'javascript: submit()'))
			->addItem(0, _('all'));

		$tpl_triggerids = [];

		foreach ($triggers as $triggerid => $trigger) {
			$tpl_triggerid_combobox->addItem($triggerid,
				(($filter_hostid == 0) ? $trigger['hosts'][0]['name'].NAME_DELIMITER : '').$trigger['description']
			);

			$tpl_triggerids[$triggerid] = true;
		}

		// Sanitize $hostgroupid and prepare "Host Group" combo box.

		$host_groups = API::HostGroup()->get([
			'output' => ['name'],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);
		$host_groups = enrichParentGroups($host_groups);
		CArrayHelper::sort($host_groups, ['name']);

		if (!array_key_exists($hostgroupid, $host_groups)) {
			$hostgroupid = 0;
		}

		$hostgroupid_combobox = (new CComboBox('hostgroupid', $hostgroupid, 'javascript: submit()'))
			->addItem(0, _('all'));

		foreach ($host_groups as $groupid => $group) {
			$hostgroupid_combobox->addItem($groupid, $group['name']);
		}

		$hostgroupids = [];

		if ($hostgroupid != 0) {
			$hostgroupids[$hostgroupid] = true;
			$parent = $host_groups[$hostgroupid]['name'].'/';

			foreach ($host_groups as $groupid => $group) {
				if (strpos($group['name'], $parent) === 0) {
					$hostgroupids[$groupid] = true;
				}
			}
		}

		// Gather all templated triggers, originating from host templates, which belong to requested template groups.

		$templated_triggers_all = ($tpl_triggerid == 0) ? $tpl_triggerids : [$tpl_triggerid => true];
		$templated_triggers_new = $templated_triggers_all;

		while ($templated_triggers_new) {
			$templated_triggers_new = API::Trigger()->get([
				'output' => ['triggerid'],
				'templated' => true,
				'filter' => ['templateid' => array_keys($templated_triggers_new)],
				'preservekeys' => true
			]);
			$templated_triggers_new = array_diff_key($templated_triggers_new, $templated_triggers_all);
			$templated_triggers_all += $templated_triggers_new;
		}

		if ($templated_triggers_all) {
			// Select monitored host triggers, derived from templates and belonging to the requested groups.
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'value'],
				'selectHosts' => ['name'],
				'expandDescription' => true,
				'monitored' => true,
				'groupids' => ($hostgroupid == 0) ? null : array_keys($hostgroupids),
				'filter' => ['templateid' => array_keys($templated_triggers_all)],
				'limit' => $config['search_limit'] + 1
			]);
		}
		else {
			// No trigger templates means there are no derived triggers.
			$triggers = [];
		}

		$filter_column
			->addRow(_('Template group'), $filter_groupid_combobox)
			->addRow(_('Template'), $filter_hostid_combobox)
			->addRow(_('Template trigger'), $tpl_triggerid_combobox)
			->addRow(_('Host group'), $hostgroupid_combobox);
	}
	// Report by host.
	elseif ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
		// Sanitize $filter_hostid and prepare "Host" combo box.

		$hosts = API::Host()->get([
			'output' => ['name'],
			'groupids' => $filter_groupids,
			'monitored_hosts' => true,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		CArrayHelper::sort($hosts, ['name']);

		if (!array_key_exists($filter_hostid, $hosts)) {
			$filter_hostid = 0;
		}

		// Select monitored host triggers, derived from templates and belonging to the requested groups.
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'value'],
			'selectHosts' => ['name'],
			'expandDescription' => true,
			'monitored' => true,
			'groupids' => $filter_groupids,
			'hostids' => ($filter_hostid == 0) ? null : [$filter_hostid],
			'limit' => $config['search_limit'] + 1
		]);

		$hosts_combobox = (new CComboBox('filter_hostid', $filter_hostid, 'javascript: submit();'))
			->addItem(0, _('all'));

		foreach ($hosts as $hostid => $host) {
			$hosts_combobox->addItem($hostid, $host['name']);
		}

		$filter_column
			->addRow(_('Host group'), $filter_groupid_combobox)
			->addRow(_('Host'), $hosts_combobox);
	}

	// Now just prepare needed data.

	foreach ($triggers as &$trigger) {
		$trigger['host_name'] = $trigger['hosts'][0]['name'];
	}
	unset($trigger);

	$reportWidget->addItem(
		(new CFilter(new CUrl('report2.php')))
			->setProfile($data['filter']['timeline']['profileIdx'])
			->setActiveTab($data['filter']['active_tab'])
			->addFormItem((new CVar('config', $availabilityReportMode))->removeId())
			->addTimeSelector($data['filter']['timeline']['from'], $data['filter']['timeline']['to'], true,
				ZBX_DATE_TIME)
			->addFilterTab(_('Filter'), [$filter_column])
	);

	/*
	 * Triggers
	 */
	$triggerTable = (new CTableInfo())->setHeader([_('Host'), _('Name'), _('Problems'), _('Ok'), _('Graph')]);

	CArrayHelper::sort($triggers, ['host_name', 'description']);

	$paging = getPagingLine($triggers, ZBX_SORT_UP, new CUrl('report2.php'));

	foreach ($triggers as $trigger) {
		$availability = calculateAvailability($trigger['triggerid'], $data['filter']['timeline']['from_ts'],
			$data['filter']['timeline']['to_ts']
		);

		$triggerTable->addRow([
			$trigger['host_name'],
			new CLink($trigger['description'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_triggerids[]', $trigger['triggerid'])
					->setArgument('filter_set', '1')
			),
			($availability['true'] < 0.00005)
				? ''
				: (new CSpan(sprintf('%.4f%%', $availability['true'])))->addClass(ZBX_STYLE_RED),
			($availability['false'] < 0.00005)
				? ''
				: (new CSpan(sprintf('%.4f%%', $availability['false'])))->addClass(ZBX_STYLE_GREEN),
			new CLink(_('Show'), 'report2.php?filter_groupid='.$_REQUEST['filter_groupid'].
				'&filter_hostid='.$_REQUEST['filter_hostid'].'&triggerid='.$trigger['triggerid']
			)
		]);
	}

	$obj_data = [
		'id' => 'timeline_1',
		'domid' => 'avail_report',
		'loadSBox' => 0,
		'loadImage' => 0,
		'dynamic' => 0,
		'mainObject' => 1
	];
	zbx_add_post_js(
		'timeControl.addObject("avail_report", '.zbx_jsvalue($data['filter']).', '.zbx_jsvalue($obj_data).');'
	);
	zbx_add_post_js('timeControl.processObjects();');

	$reportWidget
		->addItem([$triggerTable, $paging])
		->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

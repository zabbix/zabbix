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

	$trigger_options = [
		'output' => ['triggerid', 'description', 'expression', 'value', 'status'],
		'selectHosts' => ['name', 'status'],
		'selectGroups' => ['groupid'],
		'selectItems' => ['status'],
		'hostids' => null,
		'groupids' => null,
		'expandDescription' => true,
		'filter' => [],
		'limit' => $config['search_limit'] + 1,
		'preservekeys' => true
	];

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

	$filter_groupids = null;

	$group_options = [
		'output' => ['name'],
		'with_triggers' => true,
		'preservekeys' => true
	];

	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		$group_options['templated_hosts'] = true;
	}
	elseif ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
		$group_options['monitored_hosts'] = true;
	}

	$groups = API::HostGroup()->get($group_options);
	$groups = enrichParentGroups($groups);
	CArrayHelper::sort($groups, ['name']);

	if ($filter_groupid != 0 && array_key_exists($filter_groupid, $groups)) {
		$filter_groupids = [$filter_groupid];
		$parent = $groups[$filter_groupid]['name'].'/';

		foreach ($groups as $groupid => $group) {
			if (strpos($group['name'], $parent) === 0) {
				$filter_groupids[] = $groupid;
			}
		}
	}
	else {
		/*
		 * In case user has selected a "Template group" in mode "By trigger template" and then switched to
		 * mode "By host", the chosen $filter_groupid will not be in $groups. So reset "Host group" dropdown in
		 * mode "By host" to "all".
		 */
		$filter_groupid = 0;
	}

	// Prepare Template groups and Host groups dropdown depending on selected mode.
	$groups_cmb_box = (new CComboBox('filter_groupid', $filter_groupid, 'javascript: submit();'))
		->setAttribute('autofocus', 'autofocus')
		->addItem(0, _('all'));

	foreach ($groups as $groupid => $group) {
		$groups_cmb_box->addItem($groupid, $group['name']);
	}

	// Report by template.
	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		$templates = API::Template()->get([
			'output' => ['name'],
			'selectGroups' => ['groupid'],
			'groupids' => $filter_groupids,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		CArrayHelper::sort($templates, ['name']);

		if ($filter_hostid != 0 && !array_key_exists($filter_hostid, $templates)) {
			/*
			 * In case user has selected a "Template group" and "Template" and then changed "Template group" to a
			 * different one, it is possible that the previous template does not belong to that group.
			 * Reset the "Template" to "all".
			 */
			$filter_hostid = 0;
		}

		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'flags'],
			'selectHosts' => ['name'],
			'selectItems' => ['status'],
			'templateids' => ($filter_hostid == 0) ? array_keys($templates) : $filter_hostid,
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
				}
			}
		}

		if ($tpl_triggerid != 0 && !array_key_exists($tpl_triggerid, $triggers)) {
			/*
			 * In case user has selected "Template" and "Template trigger" and then changed "Template" to a different
			 * one, it is possible that the previous template trigger does not blong to that template.
			 * Reset "Template" to "all".
			 */
			$tpl_triggerid = 0;
		}

		$trigger_cmb_box = (new CComboBox('tpl_triggerid', $tpl_triggerid, 'javascript: submit()'))
			->addItem(0, _('all'));

		foreach ($triggers as $trigger) {
			$template_name = ($filter_hostid == 0) ? $trigger['hosts'][0]['name'].NAME_DELIMITER : '';

			if (!array_key_exists('templateid', $trigger_options['filter'])) {
				$trigger_options['filter']['templateid'] = [];
			}

			$trigger_options['filter']['templateid'][] = $trigger['triggerid'];

			$trigger_cmb_box->addItem($trigger['triggerid'], $template_name.$trigger['description']);
		}

		// Filter triggers by specific template trigger. Overwrites previous IDs of a specific trigger is selected.
		if ($tpl_triggerid != 0) {
			$trigger_options['filter']['templateid'] = [$tpl_triggerid];
		}

		$host_groups = API::HostGroup()->get([
			'output' => ['name'],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);
		$host_groups = enrichParentGroups($host_groups);
		CArrayHelper::sort($host_groups, ['name']);

		$groupids = [];

		if ($hostgroupid != 0) {
			$groupids[$hostgroupid] = true;

			if (array_key_exists($hostgroupid, $host_groups)) {
				$parent = $host_groups[$hostgroupid]['name'].'/';

				foreach ($host_groups as $groupid => $group) {
					if (strpos($group['name'], $parent) === 0) {
						$groupids[$groupid] = true;
					}
				}

				/*
				 * Collect group IDs for trigger selecetion to narrow down search results if all template groups are
				 * selected.
				 */
				if ($filter_groupid == 0) {
					$trigger_options['groupids'] = array_keys($groupids);
				}
			}
		}

		$triggers = API::Trigger()->get($trigger_options);

		/*
		 * Check if result belongs to template or host. If resulting triggers belong to template, continue searching
		 * till host is reached. If resulting triggers belong to host, just store them for later use.
		 */
		if ($trigger_options['filter']['templateid']) {
			$host_triggers = [];

			while ($trigger_options['filter']['templateid']) {
				$trigger_options['filter']['templateid'] = [];

				foreach ($triggers as $trigger) {
					if ($trigger['hosts'][0]['status'] == HOST_STATUS_TEMPLATE) {
						$trigger_options['filter']['templateid'][] = $trigger['triggerid'];
					}
					else {
						$host_triggers[$trigger['triggerid']] = $trigger;
					}
				}

				if ($trigger_options['filter']['templateid']) {
					$triggers = API::Trigger()->get($trigger_options);
				}
			}

			$triggers = $host_triggers;
		}

		/*
		 * Filter by selected host group. This filtering is required because some results may have been on template
		 * level rather than host level, and hosts could belong to different host groups. That's why
		 * $trigger_options['groupids'] is not in API request and this post-fileting by groups is necessary.
		 */
		if ($filter_groupid != 0 && $groupids) {
			$triggers_tmp = $triggers;
			$triggers = [];

			foreach ($triggers_tmp as $triggerid => $trigger) {
				foreach ($trigger['groups'] as $group) {
					if (array_key_exists($group['groupid'], $groupids)) {
						$trigger['host_name'] = $trigger['hosts'][0]['name'];
						$triggers[$trigger['triggerid']] = $trigger;
					}
				}
			}
		}
		else {
			foreach ($triggers as $triggerid => &$trigger) {
				$trigger['host_name'] = $trigger['hosts'][0]['name'];
			}
			unset($trigger);
		}

		/*
		 * Filter "monitored". Since "monitored" property limits trigger.get to host level, it didn't go in API request.
		 * So this post-filtering by item status, host status and trigger status is required.
		 */
		foreach ($triggers as $triggerid => $trigger) {
			if ($trigger['status'] == TRIGGER_STATUS_DISABLED) {
				unset($triggers[$triggerid]);
				continue;
			}

			foreach ($trigger['hosts'] as $host) {
				if ($host['status'] != HOST_STATUS_MONITORED) {
					unset($triggers[$triggerid]);
				}
			}

			foreach ($trigger['items'] as $item) {
				if ($item['status'] == ITEM_STATUS_DISABLED) {
					unset($triggers[$triggerid]);
				}
			}
		}

		// Build combo boxes and add them to the column.
		$host_groups_cmb_box = (new CComboBox('hostgroupid', $hostgroupid, 'javascript: submit()'))
			->addItem(0, _('all'));

		foreach ($host_groups as $groupid => $group) {
			$host_groups_cmb_box->addItem($groupid, $group['name']);
		}

		$template_cmb_box = (new CComboBox('filter_hostid', $filter_hostid, 'javascript: submit();'))
			->addItem(0, _('all'));

		foreach ($templates as $templateid => $template) {
			$template_cmb_box->addItem($templateid, $template['name']);
		}

		$filter_column
			->addRow(_('Template group'), $groups_cmb_box)
			->addRow(_('Template'), $template_cmb_box)
			->addRow(_('Template trigger'), $trigger_cmb_box)
			->addRow(_('Host group'), $host_groups_cmb_box);
	}
	// Report by host.
	elseif ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
		$hosts = API::Host()->get([
			'output' => ['name'],
			'groupids' => $filter_groupids,
			'monitored_hosts' => true,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		CArrayHelper::sort($hosts, ['name']);

		$trigger_options['groupids'] = $filter_groupids;
		$trigger_options['monitored'] = true;

		if ($filter_hostid != 0 && array_key_exists($filter_hostid, $hosts)) {
			$trigger_options['hostids'] = $filter_hostid;
		}

		$triggers = API::Trigger()->get($trigger_options);

		foreach ($triggers as $triggerid => &$trigger) {
			$trigger['host_name'] = $trigger['hosts'][0]['name'];
		}
		unset($trigger);

		$hosts_cmb_box = (new CComboBox('filter_hostid', $filter_hostid, 'javascript: submit();'))
			->addItem(0, _('all'));

		foreach ($hosts as $hostid => $host) {
			$hosts_cmb_box->addItem($hostid, $host['name']);
		}

		$filter_column
			->addRow(_('Host group'), $groups_cmb_box)
			->addRow(_('Host'), $hosts_cmb_box);
	}

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

<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'report2.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'mode' =>				[T_ZBX_INT,			O_OPT,	P_SYS,			IN(implode(',', [AVAILABILITY_REPORT_BY_HOST, AVAILABILITY_REPORT_BY_TEMPLATE])),	null],
	'hostgroupid' =>		[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'tpl_triggerid' =>		[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'triggerid' =>			[T_ZBX_INT,			O_OPT,	P_SYS|P_NZERO,	DB_ID,		null],
	// filter
	'filter_groups' =>		[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'filter_hostids' =>		[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'filter_templateid' =>	[T_ZBX_INT,			O_OPT,	P_SYS,			DB_ID,		null],
	'filter_rst'=>			[T_ZBX_STR,			O_OPT,	P_SYS,			null,		null],
	'filter_set' =>			[T_ZBX_STR,			O_OPT,	P_SYS,			null,		null],
	'from' =>				[T_ZBX_RANGE_TIME,	O_OPT,	P_SYS,			null,		null],
	'to' =>					[T_ZBX_RANGE_TIME,	O_OPT,	P_SYS,			null,		null]
];
check_fields($fields);
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

$report_mode = getRequest('mode', CProfile::get('web.avail_report.mode', AVAILABILITY_REPORT_BY_HOST));
CProfile::update('web.avail_report.mode', $report_mode, PROFILE_TYPE_INT);

/*
 * Permissions
 */
if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
	if (getRequest('hostgroupid') && !isReadableHostGroups([getRequest('hostgroupid')])) {
		access_deny();
	}
	if (getRequest('filter_groups') && !isReadableHostGroups([getRequest('filter_groups')])) {
		access_deny();
	}
	if (getRequest('filter_templateid') && !isReadableTemplates([getRequest('filter_templateid')])) {
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
$key_prefix = 'web.avail_report.'.$report_mode;

if (hasRequest('filter_set')) {
	if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		CProfile::update($key_prefix.'.groupid', getRequest('filter_groups', 0), PROFILE_TYPE_ID);
		CProfile::update($key_prefix.'.hostid', getRequest('filter_templateid', 0), PROFILE_TYPE_ID);
		CProfile::update($key_prefix.'.tpl_triggerid', getRequest('tpl_triggerid', 0), PROFILE_TYPE_ID);
		CProfile::update($key_prefix.'.hostgroupid', getRequest('hostgroupid', 0), PROFILE_TYPE_ID);
	}
	else {
		CProfile::updateArray($key_prefix.'.groupids', getRequest('filter_groups', []), PROFILE_TYPE_ID);
		CProfile::updateArray($key_prefix.'.hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);
	}
}
elseif (hasRequest('filter_rst')) {
	if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		CProfile::delete($key_prefix.'.groupid');
		CProfile::delete($key_prefix.'.hostid');
		CProfile::delete($key_prefix.'.tpl_triggerid');
		CProfile::delete($key_prefix.'.hostgroupid');
	}
	else {
		CProfile::deleteIdx($key_prefix.'.groupids');
		CProfile::deleteIdx($key_prefix.'.hostids');
	}
}

// Get filter values.
$data['filter'] = ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE)
	? [
		// 'Template group' field.
		'groups' => getRequest('filter_groups', CProfile::get($key_prefix.'.groupid', 0)),
		// 'Template' field.
		'hostids' => getRequest('filter_templateid', CProfile::get($key_prefix.'.hostid', 0)),
		// 'Template trigger' field.
		'tpl_triggerid' => getRequest('tpl_triggerid', CProfile::get($key_prefix.'.tpl_triggerid', 0)),
		// 'Host group' field.
		'hostgroupid' => getRequest('hostgroupid', CProfile::get($key_prefix.'.hostgroupid', 0))
	]
	: [
		// 'Host groups' field.
		'groups' => CProfile::getArray($key_prefix.'.groupids', getRequest('filter_groups', [])),
		// 'Hosts' field.
		'hostids' => CProfile::getArray($key_prefix.'.hostids', getRequest('filter_hostids', []))
	];

// Get time selector filter values.
$timeselector_options = [
	'profileIdx' => 'web.avail_report.filter',
	'profileIdx2' => 0,
	'from' => getRequest('from'),
	'to' => getRequest('to')
];
updateTimeSelectorPeriod($timeselector_options);

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
			->addItem(new CLink($triggerData['hostname'], (new CUrl('report2.php'))
				->setArgument('page', CPagerHelper::loadPage('report2.php', null))
				->getUrl()
			))
			->addItem($triggerData['description'])
		))->setAttribute('aria-label', _('Content controls'))
	);

	$table = (new CTableInfo())
		->addRow(new CImg('chart4.php?triggerid='.$_REQUEST['triggerid']));

	$reportWidget->addItem(BR())
		->addItem($table)
		->show();
}
else {
	/**
	 * Report list view (both data presentation modes).
	 */

	$select_mode = (new CSelect('mode'))
		->setValue($report_mode)
		->setFocusableElementId('mode')
		->addOption(new CSelectOption(AVAILABILITY_REPORT_BY_HOST, _('By host')))
		->addOption(new CSelectOption(AVAILABILITY_REPORT_BY_TEMPLATE, _('By trigger template')));

	$reportWidget->setControls((new CForm('get'))
		->cleanItems()
		->setAttribute('aria-label', _('Main filter'))
		->addItem((new CList())
			->addItem([
				new CLabel(_('Mode'), $select_mode->getFocusableElementId()),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$select_mode
			])
		)
		->setName('report2')
	);

	/*
	 * Filter
	 */
	$data['filter'] += [
		'timeline' => getTimeSelectorPeriod($timeselector_options),
		'active_tab' => CProfile::get('web.avail_report.filter.active', 1)
	];

	$filter_column = new CFormList();

	// Make filter fields.
	if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		// Sanitize $data['filter']['groups'] and prepare "Template group" select options.
		$groups = API::HostGroup()->get([
			'output' => ['name'],
			'templated_hosts' => true,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		$groups = enrichParentGroups($groups);
		CArrayHelper::sort($groups, ['name']);

		if (!array_key_exists($data['filter']['groups'], $groups)) {
			$data['filter']['groups'] = 0;
		}

		// Sanitize $data['filter']['hostids'] and prepare "Template" select options.
		$templates = API::Template()->get([
			'output' => ['name'],
			'groupids' => $data['filter']['groups'] ? [$data['filter']['groups']] : null,
			'with_triggers' => true,
			'preservekeys' => true
		]);
		CArrayHelper::sort($templates, ['name']);

		if (!array_key_exists($data['filter']['hostids'], $templates)) {
			$data['filter']['hostids'] = 0;
		}

		$select_filter_hostid = (new CSelect('filter_templateid'))
			->setValue($data['filter']['hostids'])
			->setFocusableElementId('filter-templateid')
			->addOption(new CSelectOption(0, _('all')));

		foreach ($templates as $templateid => $template) {
			$select_filter_hostid->addOption(new CSelectOption($templateid, $template['name']));
		}

		// Sanitize $data['filter']['tpl_triggerid'] and prepare "Template Trigger" select options.
		$triggers = API::Trigger()->get([
			'output' => ['description'],
			'selectHosts' => ['name'],
			'selectItems' => ['status'],
			'templateids' => $data['filter']['hostids']
				? [$data['filter']['hostids']]
				: null,
			'groupids' => $data['filter']['groups']
				? [$data['filter']['groups']]
				: null,
			'templated' => true,
			'filter' => [
				'status' => TRIGGER_STATUS_ENABLED,
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL]
			],
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

		if (!array_key_exists($data['filter']['tpl_triggerid'], $triggers)) {
			$data['filter']['tpl_triggerid'] = 0;
		}

		$select_tpl_triggerid = (new CSelect('tpl_triggerid'))
			->setValue($data['filter']['tpl_triggerid'])
			->setFocusableElementId('tpl-triggerid')
			->addOption(new CSelectOption(0, _('all')));

		$tpl_triggerids = [];

		foreach ($triggers as $triggerid => $trigger) {
			$label = (($data['filter']['hostids'] == 0) ? $trigger['hosts'][0]['name'].NAME_DELIMITER : '').$trigger['description'];
			$select_tpl_triggerid->addOption(new CSelectOption($triggerid, $label));

			$tpl_triggerids[$triggerid] = true;
		}

		// Sanitize $data['filter']['hostgroupid'] and prepare "Host Group" select options.
		$host_groups = API::HostGroup()->get([
			'output' => ['name'],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);
		$host_groups = enrichParentGroups($host_groups);
		CArrayHelper::sort($host_groups, ['name']);

		if (!array_key_exists($data['filter']['hostgroupid'], $host_groups)) {
			$data['filter']['hostgroupid'] = 0;
		}

		$select_hostgroupid = (new CSelect('hostgroupid'))
			->setValue($data['filter']['hostgroupid'])
			->setFocusableElementId('hostgroupid')
			->addOption(new CSelectOption(0, _('all')));

		foreach ($host_groups as $groupid => $group) {
			$select_hostgroupid->addOption(new CSelectOption($groupid, $group['name']));
		}

		$hostgroupids = [];
		if ($data['filter']['hostgroupid'] != 0) {
			$hostgroupids[$data['filter']['hostgroupid']] = true;
			$parent = $host_groups[$data['filter']['hostgroupid']]['name'].'/';

			foreach ($host_groups as $groupid => $group) {
				if (strpos($group['name'], $parent) === 0) {
					$hostgroupids[$groupid] = true;
				}
			}
		}

		// Gather all templated triggers, originating from host templates, which belong to requested template groups.
		$templated_triggers_all = ($data['filter']['tpl_triggerid'] == 0)
			? $tpl_triggerids
			: [$data['filter']['tpl_triggerid'] => true];
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
			$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'value'],
				'selectHosts' => ['name'],
				'expandDescription' => true,
				'monitored' => true,
				'groupids' => ($data['filter']['hostgroupid'] == 0) ? null : array_keys($hostgroupids),
				'filter' => ['templateid' => array_keys($templated_triggers_all)],
				'limit' => $limit
			]);
		}
		else {
			// No trigger templates means there are no derived triggers.
			$triggers = [];
		}

		$select_filter_groupid = (new CSelect('filter_groups'))
			->setAttribute('autofocus', 'autofocus')
			->setValue($data['filter']['groups'])
			->setFocusableElementId('filter-groups')
			->addOption(new CSelectOption(0, _('all')));

		foreach ($groups as $groupid => $group) {
			$select_filter_groupid->addOption(new CSelectOption($groupid, $group['name']));
		}

		$filter_column
			->addRow(
				new CLabel(_('Template group'), $select_filter_groupid->getFocusableElementId()),
				$select_filter_groupid
			)
			->addRow(
				new CLabel(_('Template'), $select_filter_hostid->getFocusableElementId()),
				$select_filter_hostid
			)
			->addRow(
				new CLabel(_('Template trigger'), $select_tpl_triggerid->getFocusableElementId()),
				$select_tpl_triggerid
			)
			->addRow(
				new CLabel(_('Host group'), $select_hostgroupid->getFocusableElementId()),
				$select_hostgroupid
			)
			->addVar('filter_set', '1');
	}
	// Report by host.
	else {
		// Sanitize $data['filter']['groups'] and prepare "Host groups" filter field.
		$data['filter']['groups'] = $data['filter']['groups']
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $data['filter']['groups'],
				'monitored_hosts' => true,
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		CArrayHelper::sort($data['filter']['groups'], ['name']);

		// Sanitize $data['filter']['hostids'] and prepare "Hosts" filter field.
		$data['filter']['hostids'] = $data['filter']['hostids']
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $data['filter']['hostids'],
				'monitored_hosts' => true,
				'with_triggers' => true,
				'preservekeys' => true
			]), ['hostid' => 'id'])
			: [];

		CArrayHelper::sort($data['filter']['hostids'], ['name']);

		// Select monitored host triggers, derived from templates and belonging to the requested groups.
		$groups = enrichParentGroups($data['filter']['groups']);

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'value'],
			'selectHosts' => ['name'],
			'groupids' => $groups ? array_keys($groups) : null,
			'hostids' => $data['filter']['hostids'] ? array_keys($data['filter']['hostids']) : null,
			'expandDescription' => true,
			'monitored' => true,
			'limit' => $limit
		]);

		$filter_column
			->addRow(
				(new CLabel(_('Host groups'), 'filter_groups__ms')),
				(new CMultiSelect([
					'name' => 'filter_groups[]',
					'object_name' => 'hostGroup',
					'data' => $data['filter']['groups'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_groups_',
							'with_triggers' => true,
							'real_hosts' => 1,
							'enrich_parent_groups' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(
				(new CLabel(_('Hosts'), 'filter_hostid__ms')),
				(new CMultiSelect([
					'name' => 'filter_hostids[]',
					'object_name' => 'hosts',
					'data' => $data['filter']['hostids'],
					'popup' => [
						'filter_preselect_fields' => [
							'hostgroups' => 'filter_groups_'
						],
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_hostids_',
							'with_triggers' => true,
							'real_hosts' => 1
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			);
	}

	// Now just prepare needed data.
	foreach ($triggers as &$trigger) {
		$trigger['host_name'] = $trigger['hosts'][0]['name'];
	}
	unset($trigger);

	$reportWidget->addItem(
		(new CFilter())
			->setResetUrl(new CUrl('report2.php'))
			->setProfile($data['filter']['timeline']['profileIdx'])
			->setActiveTab($data['filter']['active_tab'])
			->addFormItem((new CVar('mode', $report_mode))->removeId())
			->addTimeSelector($data['filter']['timeline']['from'], $data['filter']['timeline']['to'], true,
				ZBX_DATE_TIME)
			->addFilterTab(_('Filter'), [$filter_column])
	);

	/*
	 * Triggers
	 */
	$triggerTable = (new CTableInfo())->setHeader([_('Host'), _('Name'), _('Problems'), _('Ok'), _('Graph')]);

	CArrayHelper::sort($triggers, ['host_name', 'description']);

	// pager
	$page_num = getRequest('page', 1);
	CPagerHelper::savePage($page['file'], $page_num);
	$paging = CPagerHelper::paginate($page_num, $triggers, ZBX_SORT_UP, new CUrl('report2.php'));
	$allowed_ui_problems = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);

	foreach ($triggers as $trigger) {
		$availability = calculateAvailability($trigger['triggerid'], $data['filter']['timeline']['from_ts'],
			$data['filter']['timeline']['to_ts']
		);

		$url = (new CUrl('report2.php'))->setArgument('triggerid', $trigger['triggerid']);
		if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
			$url->setArgument('filter_templateid', $data['filter']['hostids']);
		}
		else {
			$url->setArgument('filter_hostids', $trigger['hosts'][0]['hostid']);
		}

		$triggerTable->addRow([
			$trigger['host_name'],
			$allowed_ui_problems
				? new CLink($trigger['description'],
					(new CUrl('zabbix.php'))
						->setArgument('action', 'problem.view')
						->setArgument('filter_name', '')
						->setArgument('triggerids', [$trigger['triggerid']])
				)
				: $trigger['description'],
			($availability['true'] < 0.00005)
				? ''
				: (new CSpan(sprintf('%.4f%%', $availability['true'])))->addClass(ZBX_STYLE_RED),
			($availability['false'] < 0.00005)
				? ''
				: (new CSpan(sprintf('%.4f%%', $availability['false'])))->addClass(ZBX_STYLE_GREEN),
			new CLink(_('Show'), $url)
		]);
	}

	$obj_data = [
		'id' => 'timeline_1',
		'domid' => 'avail_report',
		'loadSBox' => 0,
		'loadImage' => 0,
		'dynamic' => 0
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

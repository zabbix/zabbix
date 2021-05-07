<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Overview');
$page['file'] = 'overview.php';
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['scripts'] = ['layout.mode.js', 'multiselect.js', 'monitoring.overview.js', 'class.tagfilteritem.js'];
$page['web_layout_mode'] = CViewHelper::loadLayoutMode();

define('ZBX_PAGE_DO_REFRESH', 1);
define('SHOW_TRIGGERS', 0);
define('SHOW_DATA', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'view_style'  => [T_ZBX_INT, O_OPT, P_SYS, IN(STYLE_LEFT.','.STYLE_TOP),	null],
	'type'        => [T_ZBX_INT, O_OPT, P_SYS, IN(SHOW_TRIGGERS.','.SHOW_DATA), null],
	// filter
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_groupids' =>	[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_hostids' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_evaltype' =>	[T_ZBX_INT, O_OPT, null,	IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), null],
	'filter_tags' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'show_triggers' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'ack_status' =>			[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'show_severity' =>		[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'show_suppressed' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'status_change_days' =>	[T_ZBX_INT, O_OPT, null,	BETWEEN(1, DAY_IN_YEAR * 2), null],
	'status_change' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'txt_select' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'inventory' =>			[T_ZBX_STR, O_OPT, null,	null,		null]
];
check_fields($fields);

if (hasRequest('filter_set')) {
	CProfile::update('web.overview.filter.show_triggers', getRequest('show_triggers', TRIGGERS_OPTION_RECENT_PROBLEM),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.overview.filter.show_suppressed', getRequest('show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.overview.filter.show_severity', getRequest('show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.overview.filter.txt_select', getRequest('txt_select'), PROFILE_TYPE_STR);
	CProfile::update('web.overview.filter.status_change', getRequest('status_change', 0), PROFILE_TYPE_INT);
	CProfile::update('web.overview.filter.status_change_days', getRequest('status_change_days', 14),
		PROFILE_TYPE_INT
	);

	// ack status
	CProfile::update('web.overview.filter.ack_status', getRequest('ack_status', 0), PROFILE_TYPE_INT);

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
	CProfile::updateArray('web.overview.filter.inventory.field', $inventoryFields, PROFILE_TYPE_STR);
	CProfile::updateArray('web.overview.filter.inventory.value', $inventoryValues, PROFILE_TYPE_STR);
	CProfile::updateArray('web.overview.filter.groupids', getRequest('filter_groupids', []), PROFILE_TYPE_ID);
	CProfile::updateArray('web.overview.filter.hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);

	$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
	foreach (getRequest('filter_tags', []) as $filter_tag) {
		if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
			continue;
		}

		$filter_tags['tags'][] = $filter_tag['tag'];
		$filter_tags['values'][] = $filter_tag['value'];
		$filter_tags['operators'][] = $filter_tag['operator'];
	}

	CProfile::update('web.overview.filter.evaltype', getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
		PROFILE_TYPE_INT
	);
	CProfile::updateArray('web.overview.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
	CProfile::updateArray('web.overview.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
	CProfile::updateArray('web.overview.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.overview.filter.show_triggers');
	CProfile::delete('web.overview.filter.show_suppressed');
	CProfile::delete('web.overview.filter.ack_status');
	CProfile::delete('web.overview.filter.show_severity');
	CProfile::delete('web.overview.filter.txt_select');
	CProfile::delete('web.overview.filter.status_change');
	CProfile::delete('web.overview.filter.status_change_days');
	CProfile::deleteIdx('web.overview.filter.inventory.field');
	CProfile::deleteIdx('web.overview.filter.inventory.value');
	CProfile::deleteIdx('web.overview.filter.groupids');
	CProfile::deleteIdx('web.overview.filter.hostids');
	CProfile::delete('web.overview.filter.evaltype');
	CProfile::deleteIdx('web.overview.filter.tags.tag');
	CProfile::deleteIdx('web.overview.filter.tags.value');
	CProfile::deleteIdx('web.overview.filter.tags.operator');
	DBend();
}

$type = getRequest('type', SHOW_TRIGGERS);

// overview style
if (hasRequest('view_style')) {
	CProfile::update('web.overview.view_style', getRequest('view_style'), PROFILE_TYPE_INT);
}

/*
 * Display
 */
$data = [
	'type' => $type,
	'view_style' => CProfile::get('web.overview.view_style', STYLE_TOP),
	'profileIdx' => 'web.overview.filter',
	'active_tab' => CProfile::get('web.overview.filter.active', 1),
	'db_hosts' => [],
	'db_triggers' => [],
	'dependencies' => [],
	'triggers_by_name' => [],
	'hosts_by_name' => [],
	'exceeded_limit' => false,
	'filter' => [
		'hostids' => CProfile::getArray('web.overview.filter.hostids', []),
		'groupids' => CProfile::getArray('web.overview.filter.groupids', []),
		'show_suppressed' => CProfile::get('web.overview.filter.show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
		'evaltype' => CProfile::get('web.overview.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
		'tags' => []
	]
];

foreach (CProfile::getArray('web.overview.filter.tags.tag', []) as $i => $tag) {
	$data['filter']['tags'][] = [
		'tag' => $tag,
		'value' => CProfile::get('web.overview.filter.tags.value', null, $i),
		'operator' => CProfile::get('web.overview.filter.tags.operator', null, $i)
	];
}

// fetch trigger data
if ($type == SHOW_TRIGGERS) {
	// filter data
	$data['filter'] += [
		'showTriggers' => CProfile::get('web.overview.filter.show_triggers', TRIGGERS_OPTION_RECENT_PROBLEM),
		'ackStatus' => CProfile::get('web.overview.filter.ack_status', 0),
		'showSeverity' => CProfile::get('web.overview.filter.show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		'statusChange' => CProfile::get('web.overview.filter.status_change', 0),
		'statusChangeDays' => CProfile::get('web.overview.filter.status_change_days', 14),
		'txtSelect' => CProfile::get('web.overview.filter.txt_select', ''),
		'inventory' => []
	];

	foreach (CProfile::getArray('web.overview.filter.inventory.field', []) as $i => $field) {
		$data['filter']['inventory'][] = [
			'field' => $field,
			'value' => CProfile::get('web.overview.filter.inventory.value', null, $i)
		];
	}

	$trigger_options = [
		'search' => ($data['filter']['txtSelect'] !== '') ? ['description' => $data['filter']['txtSelect']] : null,
		'only_true' => ($data['filter']['showTriggers'] == TRIGGERS_OPTION_RECENT_PROBLEM) ? true : null,
		'filter' => [
			'value' => ($data['filter']['showTriggers'] == TRIGGERS_OPTION_IN_PROBLEM) ? TRIGGER_VALUE_TRUE : null
		],
		'skipDependent' => ($data['filter']['showTriggers'] == TRIGGERS_OPTION_ALL) ? null : true
	];

	$problem_options = [
		'tags' => $data['filter']['tags'] ? $data['filter']['tags'] : null,
		'evaltype' => $data['filter']['evaltype'] ? $data['filter']['evaltype'] : TAG_EVAL_TYPE_AND_OR,
		'show_recent' => ($data['filter']['showTriggers'] == TRIGGERS_OPTION_RECENT_PROBLEM) ? true : null,
		'show_suppressed' => $data['filter']['show_suppressed'],
		'acknowledged' => ($data['filter']['ackStatus'] == EXTACK_OPTION_UNACK) ? false : null,
		'min_severity' => $data['filter']['showSeverity'],
		'time_from' => $data['filter']['statusChange'] ? (time() - $data['filter']['statusChangeDays'] * SEC_PER_DAY) : null
	];

	$data['filter']['groups'] = $data['filter']['groupids']
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $data['filter']['groupids'],
			'preservekeys' => true
		]), ['groupid' => 'id'])
		: [];

	$groupids = $data['filter']['groups'] ? getSubGroups(array_keys($data['filter']['groups'])) : [];

	$data['filter']['hosts'] = $data['filter']['hostids']
		? CArrayHelper::renameObjectsKeys(API::Host()->get([
			'output' => ['hostid', 'name'],
			'hostids' => $data['filter']['hostids'],
			'monitored_hosts' => true,
			'with_monitored_triggers' => true,
			'preservekeys' => true
		]), ['hostid' => 'id'])
		: [];

	unset($data['filter']['groupids'], $data['filter']['hostids']);

	$host_options = [];
	if ($data['filter']['inventory']) {
		$host_options['searchInventory'] = [];
		foreach ($data['filter']['inventory'] as $field) {
			$host_options['searchInventory'][$field['field']][] = $field['value'];
		}
	}
	if ($data['filter']['hosts']) {
		$host_options['hostids'] = array_keys($data['filter']['hosts']);
	}

	$data['allowed'] = [
		'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
		'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
		'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
		'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)
	];

	[$data['db_hosts'], $data['db_triggers'], $data['dependencies'], $data['triggers_by_name'], $data['hosts_by_name'],
		$data['exceeded_limit']
	] = getTriggersOverviewData($groupids, $host_options, $trigger_options, $problem_options);

	$data['config'] = [
		'blink_period' => CSettingsHelper::get(CSettingsHelper::BLINK_PERIOD)
	];

	if (!$data['filter']['tags']) {
		$data['filter']['tags'] = [[
			'tag' => '',
			'value' => '',
			'operator' => TAG_OPERATOR_LIKE
		]];
	}

	// Render view.
	echo (new CView('monitoring.overview.triggers', $data))->getOutput();
}
// Fetch item data.
else {
	$data['filter']['groups'] = $data['filter']['groupids']
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $data['filter']['groupids'],
			'preservekeys' => true
		]), ['groupid' => 'id'])
		: [];

	$data['filter']['hosts'] = $data['filter']['hostids']
		? CArrayHelper::renameObjectsKeys(API::Host()->get([
			'output' => ['hostid', 'name'],
			'hostids' => $data['filter']['hostids'],
			'preservekeys' => true
		]), ['hostid' => 'id'])
		: [];

	unset($data['filter']['groupids'], $data['filter']['hostids']);

	$groupids = $data['filter']['groups'] ? getSubGroups(array_keys($data['filter']['groups'])) : null;
	$hostids = $data['filter']['hosts'] ? array_keys($data['filter']['hosts']) : null;

	[$data['items'], $data['hosts'], $data['has_hidden_data']] = getDataOverview($groupids, $hostids, $data['filter']);

	if (!$data['filter']['tags']) {
		$data['filter']['tags'] = [[
			'tag' => '',
			'value' => '',
			'operator' => TAG_OPERATOR_LIKE
		]];
	}

	// Render view.
	echo (new CView('monitoring.overview.items', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';

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
require_once dirname(__FILE__).'/include/maintenances.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of maintenance periods');
$page['file'] = 'maintenance.php';
$page['scripts'] = ['class.calendar.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostids' =>							[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'groupids' =>							[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	// maintenance
	'maintenanceid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'maintenanceids' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 		null],
	'mname' =>								[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Name')],
	'maintenance_type' =>					[T_ZBX_INT, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'description' =>						[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'active_since' =>						[T_ZBX_ABS_TIME, O_OPT, null, NOT_EMPTY,
												'isset({add}) || isset({update})', _('Active since')
											],
	'active_till' =>						[T_ZBX_ABS_TIME, O_OPT, null, NOT_EMPTY,
												'isset({add}) || isset({update})', _('Active till')
											],
	'timeperiods' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	'tags_evaltype' =>						[T_ZBX_INT, O_OPT, null,	null,		null],
	'tags' =>								[T_ZBX_STR, O_OPT, null,	null,		null],
	// actions
	'action' =>								[T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"maintenance.massdelete"'), null],
	'add' =>								[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>								[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>								[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>								[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>								[T_ZBX_STR, O_OPT, P_SYS,		 null,	null],
	// form
	'form' =>								[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>						[T_ZBX_INT, O_OPT, null,	null,		null],
	// filter
	'filter_set' =>							[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>							[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_status' =>						[T_ZBX_INT, O_OPT, null,	IN([-1, MAINTENANCE_STATUS_ACTIVE, MAINTENANCE_STATUS_APPROACH, MAINTENANCE_STATUS_EXPIRED]), null],
	'filter_groups' =>						[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	// sort and sortorder
	'sort' =>								[T_ZBX_STR, O_OPT, P_SYS,
												IN('"active_since","active_till","maintenance_type","name"'),
												null
											],
	'sortorder' =>							[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),
												null
											]
];

check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['maintenanceid'])) {
	$dbMaintenance = API::Maintenance()->get([
		'output' => API_OUTPUT_EXTEND,
		'selectTimeperiods' => API_OUTPUT_EXTEND,
		'selectTags' => API_OUTPUT_EXTEND,
		'editable' => true,
		'maintenanceids' => getRequest('maintenanceid')
	]);
	if (empty($dbMaintenance)) {
		access_deny();
	}
}
if (hasRequest('action') && (!hasRequest('maintenanceids') || !is_array(getRequest('maintenanceids')))) {
	access_deny();
}

$allowed_edit = CWebUser::checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);

if (!$allowed_edit && hasRequest('form') && getRequest('form') !== 'update') {
	access_deny(ACCESS_DENY_PAGE);
}

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['maintenanceid'])) {
	unset($_REQUEST['maintenanceid']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	if (!$allowed_edit) {
		access_deny(ACCESS_DENY_PAGE);
	}

	if (hasRequest('update')) {
		$messageSuccess = _('Maintenance updated');
		$messageFailed = _('Cannot update maintenance');
	}
	else {
		$messageSuccess = _('Maintenance added');
		$messageFailed = _('Cannot add maintenance');
	}

	$result = true;
	$absolute_time_parser = new CAbsoluteTimeParser();

	$absolute_time_parser->parse(getRequest('active_since'));
	$active_since_date = $absolute_time_parser->getDateTime(true);

	$absolute_time_parser->parse(getRequest('active_till'));
	$active_till_date = $absolute_time_parser->getDateTime(true);

	if ($result) {
		$timeperiods = getRequest('timeperiods', []);
		$type_fields = [
			TIMEPERIOD_TYPE_ONETIME => ['start_date'],
			TIMEPERIOD_TYPE_DAILY => ['start_time', 'every'],
			TIMEPERIOD_TYPE_WEEKLY => ['start_time', 'every', 'dayofweek'],
			TIMEPERIOD_TYPE_MONTHLY => ['start_time', 'every', 'day', 'dayofweek', 'month']
		];

		foreach ($timeperiods as &$timeperiod) {
			if ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
				$absolute_time_parser->parse($timeperiod['start_date']);
				$timeperiod['start_date'] = $absolute_time_parser
					->getDateTime(true)
					->getTimestamp();
			}

			$timeperiod = array_intersect_key($timeperiod,
				array_flip(['period', 'timeperiod_type']) + array_flip($type_fields[$timeperiod['timeperiod_type']])
			);
		}
		unset($timeperiod);

		$maintenance = [
			'name' => $_REQUEST['mname'],
			'maintenance_type' => getRequest('maintenance_type'),
			'description' => getRequest('description'),
			'active_since' => $active_since_date->getTimestamp(),
			'active_till' => $active_till_date->getTimestamp(),
			'groups' => zbx_toObject(getRequest('groupids', []), 'groupid'),
			'hosts' => zbx_toObject(getRequest('hostids', []), 'hostid'),
			'timeperiods' => $timeperiods
		];

		if ($maintenance['maintenance_type'] != MAINTENANCE_TYPE_NODATA) {
			$maintenance += [
				'tags_evaltype' => getRequest('tags_evaltype', MAINTENANCE_TAG_EVAL_TYPE_AND_OR),
				'tags' => getRequest('tags', [])
			];

			foreach ($maintenance['tags'] as $tnum => $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					unset($maintenance['tags'][$tnum]);
				}
			}
		}

		if (isset($_REQUEST['maintenanceid'])) {
			$maintenance['maintenanceid'] = $_REQUEST['maintenanceid'];
			$result = API::Maintenance()->update($maintenance);
		}
		else {
			$result = API::Maintenance()->create($maintenance);
		}
	}

	if ($result) {
		unset($_REQUEST['form']);
		uncheckTableRows();
	}

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('delete') || getRequest('action', '') == 'maintenance.massdelete') {
	if (!$allowed_edit) {
		access_deny(ACCESS_DENY_PAGE);
	}

	$maintenanceids = getRequest('maintenanceid', []);
	if (hasRequest('maintenanceids')) {
		$maintenanceids = getRequest('maintenanceids');
	}

	zbx_value2array($maintenanceids);

	$result = API::Maintenance()->delete($maintenanceids);
	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['maintenanceid']);
		uncheckTableRows();
	}
	else {
		$maintenances = API::Maintenance()->get([
			'maintenanceids' => getRequest('maintenanceids'),
			'output' => [],
			'editable' => true
		]);
		uncheckTableRows(null, zbx_objectValues($maintenances, 'maintenanceid'));
	}

	show_messages($result, _('Maintenance deleted'), _('Cannot delete maintenance'));
}

/*
 * Display
 */
$data = [
	'form' => getRequest('form'),
	'allowed_edit' => $allowed_edit
];

if (!empty($data['form'])) {
	$data['maintenanceid'] = getRequest('maintenanceid');
	$data['form_refresh'] = getRequest('form_refresh', 0);

	if (isset($data['maintenanceid']) && !hasRequest('form_refresh')) {
		$dbMaintenance = reset($dbMaintenance);
		$data['mname'] = $dbMaintenance['name'];
		$data['maintenance_type'] = $dbMaintenance['maintenance_type'];
		$data['active_since'] = date(ZBX_DATE_TIME, $dbMaintenance['active_since']);
		$data['active_till'] = date(ZBX_DATE_TIME, $dbMaintenance['active_till']);
		$data['description'] = $dbMaintenance['description'];

		// time periods
		$data['timeperiods'] = $dbMaintenance['timeperiods'];
		CArrayHelper::sort($data['timeperiods'], ['timeperiod_type', 'start_date']);

		foreach ($data['timeperiods'] as &$timeperiod) {
			$timeperiod['start_date'] = date(ZBX_DATE_TIME, $timeperiod['start_date']);
		}
		unset($timeperiod);

		// get hosts
		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'maintenanceids' => $data['maintenanceid'],
			'editable' => true
		]);

		// get groups
		$db_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'maintenanceids' => $data['maintenanceid'],
			'editable' => true
		]);

		// tags
		$data['tags_evaltype'] = $dbMaintenance['tags_evaltype'];
		$data['tags'] = $dbMaintenance['tags'];
		CArrayHelper::sort($data['tags'], ['tag', 'value']);
	}
	else {
		$data += [
			'mname' => getRequest('mname', ''),
			'maintenance_type' => getRequest('maintenance_type', 0),
			'active_since' => getRequest('active_since', date(ZBX_DATE_TIME, strtotime('today'))),
			'active_till' => getRequest('active_till', date(ZBX_DATE_TIME, strtotime('tomorrow'))),
			'description' => getRequest('description', ''),
			'timeperiods' => getRequest('timeperiods', []),
			'tags_evaltype' => getRequest('tags_evaltype', MAINTENANCE_TAG_EVAL_TYPE_AND_OR),
			'tags' => getRequest('tags', [])
		];

		$hostids = getRequest('hostids', []);
		$groupids = getRequest('groupids', []);

		$db_hosts = $hostids
			? API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $hostids,
				'editable' => true
			])
			: [];

		$db_groups = $groupids
			? API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids,
				'editable' => true
			])
			: [];
	}

	$data['hosts_ms'] = CArrayHelper::renameObjectsKeys($db_hosts, ['hostid' => 'id']);
	CArrayHelper::sort($data['hosts_ms'], ['name']);

	$data['groups_ms'] = CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']);
	CArrayHelper::sort($data['groups_ms'], ['name']);

	// render view
	echo (new CView('configuration.maintenance.edit', $data))->getOutput();
}
else {
	// get maintenances
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.maintenance.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.maintenance.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
		CProfile::updateArray('web.maintenance.filter_groups', getRequest('filter_groups', []), PROFILE_TYPE_ID);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.maintenance.filter_name');
		CProfile::delete('web.maintenance.filter_status');
		CProfile::deleteIdx('web.maintenance.filter_groups');
	}

	$filter = [
		'name' => CProfile::get('web.maintenance.filter_name', ''),
		'status' => CProfile::get('web.maintenance.filter_status', -1),
		'groups' => CProfile::getArray('web.maintenance.filter_groups', [])
	];

	// Get host groups.
	$filter['groups'] = $filter['groups']
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter['groups'],
			'editable' => true,
			'preservekeys' => true
		]), ['groupid' => 'id'])
		: [];

	$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;
	if ($filter_groupids) {
		$filter_groupids = getSubGroups($filter_groupids);
	}

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'profileIdx' => 'web.maintenance.filter',
		'active_tab' => CProfile::get('web.maintenance.filter.active', 1),
		'allowed_edit' => $allowed_edit
	];

	// Get list of maintenances.
	$options = [
		'output' => ['maintenanceid', 'name', 'maintenance_type', 'active_since', 'active_till', 'description'],
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name']
		],
		'groupids' => $filter_groupids,
		'editable' => true,
		'sortfield' => $sortField,
		'sortorder' => $sortOrder,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
	];

	$data['maintenances'] = API::Maintenance()->get($options);

	foreach ($data['maintenances'] as $key => $maintenance) {
		if ($maintenance['active_till'] < time()) {
			$data['maintenances'][$key]['status'] = MAINTENANCE_STATUS_EXPIRED;
		}
		elseif ($maintenance['active_since'] > time()) {
			$data['maintenances'][$key]['status'] = MAINTENANCE_STATUS_APPROACH;
		}
		else {
			$data['maintenances'][$key]['status'] = MAINTENANCE_STATUS_ACTIVE;
		}
	}

	// filter by status
	if ($filter['status'] != -1) {
		foreach ($data['maintenances'] as $key => $maintenance) {
			if ($data['maintenances'][$key]['status'] != $filter['status']) {
				unset($data['maintenances'][$key]);
			}
		}
	}

	order_result($data['maintenances'], $sortField, $sortOrder);

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $data['maintenances'], $sortOrder, new CUrl('maintenance.php'));

	// render view
	echo (new CView('configuration.maintenance.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';

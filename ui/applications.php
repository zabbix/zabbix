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
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of applications');
$page['file'] = 'applications.php';
$page['scripts'] = ['multiselect.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'applications' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO, 'isset({form}) && !isset({applicationid})'],
	'applicationid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			'isset({form}) && {form} == "update"'],
	'appname' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({add}) || isset({update})', _('Name')],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"application.massdelete","application.massdisable","application.massenable"'),
								null
							],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'clone' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,		null,	null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'filter_groups' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'filter_hostids' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['applicationid'])) {
	$dbApplication = API::Application()->get([
		'output' => ['name', 'hostid'],
		'applicationids' => [$_REQUEST['applicationid']],
		'editable' => true
	]);
	if (!$dbApplication) {
		access_deny();
	}
}
elseif (getRequest('hostid') && !isWritableHostTemplates([getRequest('hostid')])) {
	access_deny();
}

/**
 * Select filters.
 */
$sort_field = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
$sort_order = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update('web.'.$page['file'].'.sort', $sort_field, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sort_order, PROFILE_TYPE_STR);

if (hasRequest('filter_set')) {
	CProfile::updateArray('web.applications.filter_groups', getRequest('filter_groups', []), PROFILE_TYPE_ID);
	CProfile::updateArray('web.applications.filter_hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);
}
elseif (hasRequest('filter_rst')) {
	CProfile::deleteIdx('web.applications.filter_groups');

	$filter_hostids = getRequest('filter_hostids', CProfile::getArray('web.applications.filter_hostids', []));
	if (count($filter_hostids) != 1) {
		CProfile::deleteIdx('web.applications.filter_hostids');
	}
}

$filter = [
	'groups' => CProfile::getArray('web.applications.filter_groups', null),
	'hosts' => CProfile::getArray('web.applications.filter_hostids', null)
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

// Get hosts.
$filter['hosts'] = $filter['hosts']
	? CArrayHelper::renameObjectsKeys(API::Host()->get([
		'output' => ['hostid', 'name'],
		'hostids' => $filter['hosts'],
		'templated_hosts' => true,
		'editable' => true,
		'preservekeys' => true
	]), ['hostid' => 'id'])
	: [];

$hostid = (count($filter['hosts']) == 1) ? reset($filter['hosts'])['id'] : getRequest('hostid', 0);

/**
 * Do uncheck.
 */
if (hasRequest('action')) {
	if (!hasRequest('applications') || !is_array(getRequest('applications'))) {
		access_deny();
	}
	else {
		$applications = API::Application()->get([
			'output' => [],
			'applicationids' => getRequest('applications'),
			'editable' => true
		]);
		if (count($applications) != count(getRequest('applications'))) {
			uncheckTableRows($hostid, zbx_objectValues($applications, 'applicationid'));
		}
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	if (hasRequest('applicationid')) {
		$result = (bool) API::Application()->update([
			'applicationid' => $_REQUEST['applicationid'],
			'name' => $_REQUEST['appname']
		]);

		show_messages($result, _('Application updated'), _('Cannot update application'));
	}
	else {
		$result = (bool) API::Application()->create([
			'hostid' => $_REQUEST['hostid'],
			'name' => $_REQUEST['appname']
		]);

		show_messages($result, _('Application added'), _('Cannot add application'));
	}

	if ($result) {
		uncheckTableRows($hostid);
		unset($_REQUEST['form']);
	}
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['applicationid'])) {
	unset($_REQUEST['applicationid']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('delete') && hasRequest('applicationid')) {
	$result = (bool) API::Application()->delete([getRequest('applicationid')]);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
		unset($_REQUEST['form'], $_REQUEST['applicationid']);
	}
	show_messages($result, _('Application deleted'), _('Cannot delete application'));
}
elseif (hasRequest('action') && getRequest('action') == 'application.massdelete' && hasRequest('applications')) {
	$applicationids = getRequest('applications');

	$result = (bool) API::Application()->delete($applicationids);

	if ($result) {
		uncheckTableRows($hostid);
	}
	show_messages($result,
		_n('Application deleted', 'Applications deleted', count($applicationids)),
		_n('Cannot delete application', 'Cannot delete applications', count($applicationids))
	);
}
elseif (hasRequest('applications')
		&& str_in_array(getRequest('action'), ['application.massenable', 'application.massdisable'])) {
	$status = (getRequest('action') === 'application.massenable') ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

	$db_items = API::Item()->get([
		'output' => ['itemid'],
		'applicationids' => getRequest('applications', [])
	]);

	$items = [];
	foreach ($db_items as $db_item) {
		$items[] = ['itemid' => $db_item['itemid'], 'status' => $status];
	}

	$result = (bool) API::Item()->update($items);

	if ($result) {
		uncheckTableRows($hostid);
	}

	$updated = count($items);

	$messageSuccess = ($status == ITEM_STATUS_ACTIVE)
		? _n('Item enabled', 'Items enabled', $updated)
		: _n('Item disabled', 'Items disabled', $updated);
	$messageFailed = ($status == ITEM_STATUS_ACTIVE)
		? _n('Cannot enable item', 'Cannot enable items', $updated)
		: _n('Cannot disable item', 'Cannot disable items', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'applicationid' => getRequest('applicationid'),
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0)
	];

	if (isset($data['applicationid']) && !isset($_REQUEST['form_refresh'])) {
		$dbApplication = reset($dbApplication);

		$data['appname'] = $dbApplication['name'];
		$data['hostid'] = $dbApplication['hostid'];

	}
	else {
		$data['appname'] = getRequest('appname', '');
		$data['hostid'] = getRequest('hostid');
	}

	// render view
	echo (new CView('configuration.application.edit', $data))->getOutput();
}
else {

	$data = [
		'filter' => $filter,
		'sort' => $sort_field,
		'sortorder' => $sort_order,
		'hostid' => $hostid,
		'showInfoColumn' => false,
		'profileIdx' => 'web.applications.filter',
		'active_tab' => CProfile::get('web.applications.filter.active', 1)
	];

	$config = select_config();

	// Get applications.
	$data['applications'] = API::Application()->get([
		'output' => ['applicationid', 'hostid', 'name', 'flags', 'templateids'],
		'hostids' => $filter['hosts'] ? array_keys($filter['hosts']) : null,
		'groupids' => $filter_groupids,
		'selectHost' => ['hostid', 'name'],
		'selectItems' => ['itemid'],
		'selectDiscoveryRule' => ['itemid', 'name'],
		'selectApplicationDiscovery' => ['ts_delete'],
		'editable' => true,
		'sortfield' => $sort_field,
		'limit' => $config['search_limit'] + 1
	]);

	order_result($data['applications'], $sort_field, $sort_order);

	$data['parent_templates'] = getApplicationParentTemplates($data['applications']);

	/*
	 * Calculate the 'ts_delete' which will display the of warning icon and hint telling when application will be
	 * deleted. Also we need only 'ts_delete' for view, so get rid of the multidimensional array inside
	 * 'applicationDiscovery' property.
	 */
	foreach ($data['applications'] as &$application) {
		if ($application['applicationDiscovery']) {
			if (count($application['applicationDiscovery']) > 1) {
				$ts_delete = zbx_objectValues($application['applicationDiscovery'], 'ts_delete');

				if (min($ts_delete) == 0) {
					// One rule stops discovering application, but other rule continues to discover it.
					unset($application['applicationDiscovery']);
					$application['applicationDiscovery']['ts_delete'] = 0;
				}
				else {
					// Both rules stop discovering application. Find maximum clock.
					unset($application['applicationDiscovery']);
					$application['applicationDiscovery']['ts_delete'] = max($ts_delete);
				}
			}
			else {
				// Application is discovered by one rule.
				$ts_delete = $application['applicationDiscovery'][0]['ts_delete'];
				unset($application['applicationDiscovery']);
				$application['applicationDiscovery']['ts_delete'] = $ts_delete;
			}
		}
	}
	unset($application);

	// Info column is show when all hosts are selected or current host is not a template.
	if ($data['hostid'] > 0) {
		$hosts = API::Host()->get([
			'output' => ['status'],
			'hostids' => [$data['hostid']]
		]);

		$data['showInfoColumn'] = $hosts
			&& ($hosts[0]['status'] == HOST_STATUS_MONITORED || $hosts[0]['status'] == HOST_STATUS_NOT_MONITORED);
	}
	else {
		$data['showInfoColumn'] = true;
	}

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

	$data['paging'] = CPagerHelper::paginate($page_num, $data['applications'], $sort_order, new CUrl($page['file']));

	// render view
	echo (new CView('configuration.application.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';

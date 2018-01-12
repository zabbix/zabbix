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
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of applications');
$page['file'] = 'applications.php';

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'applications' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO, 'isset({form}) && !isset({applicationid})'],
	'groupid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
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
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,	null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,			null,	null],
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
		'applicationids' => [$_REQUEST['applicationid']],
		'output' => ['name', 'hostid']
	]);
	if (!$dbApplication) {
		access_deny();
	}
}
if (hasRequest('action')) {
	if (!hasRequest('applications') || !is_array(getRequest('applications'))) {
		access_deny();
	}
	else {
		$dbApplications = API::Application()->get([
			'applicationids' => getRequest('applications'),
			'countOutput' => true
		]);
		if ($dbApplications != count(getRequest('applications'))) {
			access_deny();
		}
	}
}
if (getRequest('groupid') && !isWritableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('hostid') && !isWritableHostTemplates([getRequest('hostid')])) {
	access_deny();
}

$pageFilter = new CPageFilter([
	'groups' => ['editable' => true, 'with_hosts_and_templates' => true],
	'hosts' => ['editable' => true, 'templated_hosts' => true],
	'hostid' => getRequest('hostid'),
	'groupid' => getRequest('groupid')
]);

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
		uncheckTableRows(getRequest('hostid'));
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
		uncheckTableRows($pageFilter->hostid);
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
		uncheckTableRows($pageFilter->hostid);
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
	$applicationView = new CView('configuration.application.edit', $data);
	$applicationView->render();
	$applicationView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$pageFilter = new CPageFilter([
		'groups' => ['editable' => true, 'with_hosts_and_templates' => true],
		'hosts' => ['editable' => true, 'templated_hosts' => true],
		'hostid' => getRequest('hostid'),
		'groupid' => getRequest('groupid')
	]);

	$data = [
		'pageFilter' => $pageFilter,
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'hostid' => $pageFilter->hostid,
		'groupid' => $pageFilter->groupid,
		'showInfoColumn' => false
	];

	if ($pageFilter->hostsSelected) {
		$config = select_config();

		// get application ids
		$applications = API::Application()->get([
			'output' => ['applicationid'],
			'hostids' => ($pageFilter->hostid > 0) ? $pageFilter->hostid : null,
			'groupids' => $pageFilter->groupids,
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		]);
		$applicationids = zbx_objectValues($applications, 'applicationid');

		// get applications
		$data['applications'] = API::Application()->get([
			'output' => ['applicationid', 'hostid', 'name', 'flags', 'templateids'],
			'selectHost' => ['hostid', 'name'],
			'selectItems' => ['itemid'],
			'selectHost' => ['hostid', 'name'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectApplicationDiscovery' => ['ts_delete'],
			'applicationids' => $applicationids
		]);

		order_result($data['applications'], $sortField, $sortOrder);

		// fetch template application source parents
		$applicationSourceParentIds = getApplicationSourceParentIds($applicationids);
		$parentAppIds = [];

		foreach ($applicationSourceParentIds as $applicationParentIds) {
			foreach ($applicationParentIds as $parentId) {
				$parentAppIds[$parentId] = $parentId;
			}
		}

		if ($parentAppIds) {
			$parentTemplates = DBfetchArrayAssoc(DBselect(
				'SELECT a.applicationid,h.hostid,h.name'.
				' FROM applications a,hosts h'.
				' WHERE a.hostid=h.hostid'.
					' AND '.dbConditionInt('a.applicationid', $parentAppIds)
			), 'applicationid');

			foreach ($data['applications'] as &$application) {
				if ($application['templateids'] && isset($applicationSourceParentIds[$application['applicationid']])) {
					foreach ($applicationSourceParentIds[$application['applicationid']] as $parentAppId) {
						$application['sourceTemplates'][] = $parentTemplates[$parentAppId];
					}
				}
			}
			unset($application);
		}

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
		if ($pageFilter->hostid > 0) {
			$hosts = API::Host()->get([
				'output' => ['status'],
				'hostids' => [$pageFilter->hostid]
			]);

			$data['showInfoColumn'] = $hosts
				&& ($hosts[0]['status'] == HOST_STATUS_MONITORED || $hosts[0]['status'] == HOST_STATUS_NOT_MONITORED);
		}
		else {
			$data['showInfoColumn'] = true;
		}
	}
	else {
		$data['applications'] = [];
	}

	// get paging
	$url = (new CUrl('applications.php'))
		->setArgument('groupid', $data['groupid'])
		->setArgument('hostid', $data['hostid']);

	$data['paging'] = getPagingLine($data['applications'], $sortOrder, $url);

	// Select writable templates IDs.
	$hostids = [];

	foreach ($data['applications'] as $application) {
		if (array_key_exists('sourceTemplates', $application)) {
			$hostids = array_merge($hostids, zbx_objectValues($application['sourceTemplates'], 'hostid'));
		}

		$hostids[] = $application['host']['hostid'];
	}

	$data['writable_templates'] = [];

	if ($hostids) {
		$data['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys(array_flip($hostids)),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	// render view
	$applicationView = new CView('configuration.application.list', $data);
	$applicationView->render();
	$applicationView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

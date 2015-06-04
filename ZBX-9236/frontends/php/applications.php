<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
if (getRequest('groupid') && !API::HostGroup()->isWritable([$_REQUEST['groupid']])) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isWritable([$_REQUEST['hostid']])) {
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
	DBstart();

	$application = [
		'name' => $_REQUEST['appname'],
		'hostid' => $_REQUEST['hostid']
	];

	if (isset($_REQUEST['applicationid'])) {
		$application['applicationid'] = $_REQUEST['applicationid'];
		$dbApplications = API::Application()->update($application);

		$messageSuccess = _('Application updated');
		$messageFailed = _('Cannot update application');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$dbApplications = API::Application()->create($application);

		$messageSuccess = _('Application added');
		$messageFailed = _('Cannot add application');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($dbApplications) {
		$applicationId = reset($dbApplications['applicationids']);

		add_audit($auditAction, AUDIT_RESOURCE_APPLICATION,
			_('Application').' ['.$_REQUEST['appname'].'] ['.$applicationId.']'
		);
		unset($_REQUEST['form']);
	}

	$result = DBend($dbApplications);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['applicationid'])) {
	unset($_REQUEST['applicationid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['delete'])) {
	if (isset($_REQUEST['applicationid'])) {
		$result = false;

		DBstart();

		if ($app = get_application_by_applicationid($_REQUEST['applicationid'])) {
			$host = get_host_by_hostid($app['hostid']);

			$result = API::Application()->delete([getRequest('applicationid')]);
		}

		if ($result) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_APPLICATION,
				'Application ['.$app['name'].'] from host ['.$host['host'].']'
			);
		}

		unset($_REQUEST['form'], $_REQUEST['applicationid']);

		$result = DBend($result);

		if ($result) {
			uncheckTableRows(getRequest('hostid'));
		}
		show_messages($result, _('Application deleted'), _('Cannot delete application'));
	}
}
elseif (hasRequest('action') && getRequest('action') == 'application.massdelete' && hasRequest('applications')) {
	$result = true;
	$applications = getRequest('applications');
	$deleted = 0;

	DBstart();

	$dbApplications = DBselect(
		'SELECT a.applicationid,a.name,a.hostid'.
		' FROM applications a'.
		' WHERE '.dbConditionInt('a.applicationid', $applications)
	);

	while ($dbApplication = DBfetch($dbApplications)) {
		if (!isset($applications[$dbApplication['applicationid']])) {
			continue;
		}

		$result &= (bool) API::Application()->delete([$dbApplication['applicationid']]);

		if ($result) {
			$host = get_host_by_hostid($dbApplication['hostid']);

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_APPLICATION,
				'Application ['.$dbApplication['name'].'] from host ['.$host['host'].']'
			);
		}

		$deleted++;
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result,
		_n('Application deleted', 'Applications deleted', $deleted),
		_n('Cannot delete application', 'Cannot delete applications', $deleted)
	);
}
elseif (hasRequest('applications')
		&& str_in_array(getRequest('action'), ['application.massenable', 'application.massdisable'])) {
	$enableApplicationItems = (getRequest('action') === 'application.massenable');

	$applications = API::Application()->get([
		'output' => [],
		'applicationids' => getRequest('applications', []),
		'selectItems' => ['itemid'],
		'hostids' => ($pageFilter->hostid > 0) ? $pageFilter->hostid : null
	]);

	$actionSuccessful = true;
	$updatedItemCount = 0;

	DBstart();

	foreach ($applications as $application) {
		foreach($application['items'] as $item) {
			$actionSuccessful &= $enableApplicationItems
				? activate_item($item['itemid'])
				: disable_item($item['itemid']);

			$updatedItemCount++;
		}
	}

	$actionSuccessful = DBend($actionSuccessful);

	if ($actionSuccessful) {
		uncheckTableRows($pageFilter->hostid);
	}

	$messageSuccess = $enableApplicationItems
		? _n('Item enabled', 'Items enabled', $updatedItemCount)
		: _n('Item disabled', 'Items disabled', $updatedItemCount);
	$messageFailed = $enableApplicationItems
		? _n('Cannot enable item', 'Cannot enable items', $updatedItemCount)
		: _n('Cannot disable item', 'Cannot disable items', $updatedItemCount);

	show_messages($actionSuccessful, $messageSuccess, $messageFailed);
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
		'groupid' => $pageFilter->groupid
	];

	if ($pageFilter->hostsSelected) {
		$config = select_config();

		// get application ids
		$applications = API::Application()->get([
			'hostids' => ($pageFilter->hostid > 0) ? $pageFilter->hostid : null,
			'groupids' => ($pageFilter->groupid > 0) ? $pageFilter->groupid : null,
			'output' => ['applicationid'],
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		]);
		$applicationIds = zbx_objectValues($applications, 'applicationid');

		// get applications
		$data['applications'] = API::Application()->get([
			'applicationids' => $applicationIds,
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => ['itemid'],
			'selectHost' => ['hostid', 'name']
		]);

		order_result($data['applications'], $sortField, $sortOrder);

		// fetch template application source parents
		$applicationSourceParentIds = getApplicationSourceParentIds($applicationIds);
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
		}
	}
	else {
		$data['applications'] = [];
	}

	// get paging
	$data['paging'] = getPagingLine($data['applications'], $sortOrder);

	// render view
	$applicationView = new CView('configuration.application.list', $data);
	$applicationView->render();
	$applicationView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

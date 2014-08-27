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
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of applications');
$page['file'] = 'applications.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'applications' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO, 'isset({form})&&!isset({applicationid})'),
	'groupid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'applicationid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			'isset({form})&&{form}=="update"'),
	'appname' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({save})', _('Name')),
	// actions
	'action' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"application.massdelete","application.massdisable","application.massenable"'),
								null
							),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,			null,	null),
	// sort and sortorder
	'sort' =>				array(T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null),
	'sortorder' =>			array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['applicationid'])) {
	$dbApplication = API::Application()->get(array(
		'applicationids' => array($_REQUEST['applicationid']),
		'output' => array('name', 'hostid')
	));
	if (!$dbApplication) {
		access_deny();
	}
}
if (hasRequest('action')) {
	if (!hasRequest('applications') || !is_array(getRequest('applications'))) {
		access_deny();
	}
	else {
		$dbApplications = API::Application()->get(array(
			'applicationids' => getRequest('applications'),
			'countOutput' => true
		));
		if ($dbApplications != count(getRequest('applications'))) {
			access_deny();
		}
	}
}
if (getRequest('groupid') && !API::HostGroup()->isWritable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isWritable(array($_REQUEST['hostid']))) {
	access_deny();
}

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	DBstart();

	$application = array(
		'name' => $_REQUEST['appname'],
		'hostid' => $_REQUEST['hostid']
	);

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

	unset($_REQUEST['save']);

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

			$result = API::Application()->delete(array(getRequest('applicationid')));
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

		$result &= (bool) API::Application()->delete(array($dbApplication['applicationid']));

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
elseif (hasRequest('action') && str_in_array(getRequest('action'), array('application.massenable', 'application.massdisable')) && hasRequest('applications')) {
	$result = true;
	$hostId = getRequest('hostid');
	$enable = (getRequest('action') == 'application.massenable');
	$updated = 0;

	DBstart();

	foreach (getRequest('applications') as $id => $appid) {
		$dbItems = DBselect(
			'SELECT ia.itemid,i.hostid,i.key_'.
			' FROM items_applications ia'.
				' LEFT JOIN items i ON ia.itemid=i.itemid'.
			' WHERE ia.applicationid='.zbx_dbstr($appid).
				' AND i.hostid='.zbx_dbstr($hostId).
				' AND i.type<>'.ITEM_TYPE_HTTPTEST
		);

		while ($item = DBfetch($dbItems)) {
			$result &= $enable ? activate_item($item['itemid']) : disable_item($item['itemid']);
			$updated++;
		}
	}
	$result = DBend($result);

	$messageSuccess = $enable
		? _n('Item enabled', 'Items enabled', $updated)
		: _n('Item disabled', 'Items disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable item', 'Cannot enable items', $updated)
		: _n('Cannot disable item', 'Cannot disable items', $updated);

	if ($result) {
		uncheckTableRows($hostId);
	}
	show_messages($result, $messageSuccess, $messageFailed);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'applicationid' => getRequest('applicationid'),
		'groupid' => getRequest('groupid', 0),
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0)
	);

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

	$data = array(
		'pageFilter' => new CPageFilter(array(
			'groups' => array('editable' => true, 'with_hosts_and_templates' => true),
			'hosts' => array('editable' => true, 'templated_hosts' => true),
			'hostid' => getRequest('hostid'),
			'groupid' => getRequest('groupid')
		)),
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);
	$data['groupid'] = $data['pageFilter']->groupid;
	$data['hostid'] = $data['pageFilter']->hostid;

	if ($data['pageFilter']->hostsSelected) {
		// get application ids
		$data['applications'] = API::Application()->get(array(
			'hostids' => ($data['pageFilter']->hostid > 0) ? $data['pageFilter']->hostid : null,
			'groupids' => ($data['pageFilter']->groupid > 0) ? $data['pageFilter']->groupid : null,
			'output' => array('applicationid'),
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		));

		// get applications
		$data['applications'] = API::Application()->get(array(
			'applicationids' => zbx_objectValues($data['applications'], 'applicationid'),
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => array('itemid'),
			'expandData' => true
		));

		order_result($data['applications'], $sortField, $sortOrder);

		// fetch template application source parents
		$applicationSourceParentIds = getApplicationSourceParentIds(zbx_objectValues($data['applications'], 'applicationid'));
		$parentAppIds = array();

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
		$data['applications'] = array();
	}

	// get paging
	$data['paging'] = getPagingLine($data['applications']);

	// render view
	$applicationView = new CView('configuration.application.list', $data);
	$applicationView->render();
	$applicationView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

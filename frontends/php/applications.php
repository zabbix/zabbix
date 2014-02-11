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
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,			null,	null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

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
if (isset($_REQUEST['go'])) {
	if (!isset($_REQUEST['applications']) || !is_array($_REQUEST['applications'])) {
		access_deny();
	}
	else {
		$dbApplications = API::Application()->get(array(
			'applicationids' => $_REQUEST['applications'],
			'countOutput' => true
		));
		if ($dbApplications != count($_REQUEST['applications'])) {
			access_deny();
		}
	}
}
if (get_request('groupid') && !API::HostGroup()->isWritable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (get_request('hostid') && !API::Host()->isWritable(array($_REQUEST['hostid']))) {
	access_deny();
}
$_REQUEST['go'] = get_request('go', 'none');

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

		add_audit($auditAction, AUDIT_RESOURCE_APPLICATION, _('Application').' ['.$_REQUEST['appname'].' ] ['.$applicationId.']');
		unset($_REQUEST['form']);
		clearCookies($dbApplications, $_REQUEST['hostid']);
	}

	unset($_REQUEST['save']);

	$result = DBend($dbApplications);
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

			$result = API::Application()->delete($_REQUEST['applicationid']);
		}

		if ($result) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_APPLICATION,
				'Application ['.$app['name'].'] from host ['.$host['host'].']'
			);
		}

		unset($_REQUEST['form'], $_REQUEST['applicationid']);

		$result = DBend($result);
		show_messages($result, _('Application deleted'), _('Cannot delete application'));
		clearCookies($result, $_REQUEST['hostid']);
	}
}
elseif ($_REQUEST['go'] == 'delete') {
	$result = true;
	$applications = get_request('applications', array());
	$deleted = 0;

	DBstart();

	$dbApplications = DBselect(
		'SELECT a.applicationid,a.name,a.hostid'.
		' FROM applications a'.
		' WHERE '.dbConditionInt('a.applicationid', $applications).
			andDbNode('a.applicationid')
	);
	while ($dbApplication = DBfetch($dbApplications)) {
		if (!isset($applications[$dbApplication['applicationid']])) {
			continue;
		}

		$result &= (bool) API::Application()->delete($dbApplication['applicationid']);

		if ($result) {
			$host = get_host_by_hostid($dbApplication['hostid']);

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_APPLICATION,
				'Application ['.$dbApplication['name'].'] from host ['.$host['host'].']'
			);
		}

		$deleted++;
	}

	$messageSuccess = _n('Application deleted', 'Applications deleted', $deleted);
	$messageFailed = _n('Cannot delete application', 'Cannot delete applications', $deleted);

	$result = DBend($result);
	show_messages($result, $messageSuccess, $messageFailed);
	clearCookies($result, $_REQUEST['hostid']);
}
elseif (str_in_array(getRequest('go'), array('activate', 'disable'))) {
	$result = true;
	$hostId = getRequest('hostid');
	$enable = (getRequest('go') == 'activate');
	$updated = 0;

	DBstart();

	foreach (getRequest('applications') as $id => $appid) {
		$dbItems = DBselect(
			'SELECT ia.itemid,i.hostid,i.key_'.
			' FROM items_applications ia'.
				' LEFT JOIN items i ON ia.itemid=i.itemid'.
			' WHERE ia.applicationid='.zbx_dbstr($appid).
				' AND i.hostid='.zbx_dbstr($hostId).
				' AND i.type<>'.ITEM_TYPE_HTTPTEST.
				andDbNode('ia.applicationid')
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

	show_messages($result, $messageSuccess, $messageFailed);
	clearCookies($result, $hostId);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'applicationid' => get_request('applicationid'),
		'groupid' => get_request('groupid', 0),
		'form' => get_request('form'),
		'form_refresh' => get_request('form_refresh', 0)
	);

	if (isset($data['applicationid']) && !isset($_REQUEST['form_refresh'])) {
		$dbApplication = reset($dbApplication);

		$data['appname'] = $dbApplication['name'];
		$data['hostid'] = $dbApplication['hostid'];

	}
	else {
		$data['appname'] = get_request('appname', '');
		$data['hostid'] = get_request('hostid');
	}

	// render view
	$applicationView = new CView('configuration.application.edit', $data);
	$applicationView->render();
	$applicationView->show();
}
else {
	$data = array(
		'pageFilter' => new CPageFilter(array(
			'groups' => array('editable' => true, 'with_hosts_and_templates' => true),
			'hosts' => array('editable' => true, 'templated_hosts' => true),
			'hostid' => get_request('hostid'),
			'groupid' => get_request('groupid')
		))
	);
	$data['groupid'] = $data['pageFilter']->groupid;
	$data['hostid'] = $data['pageFilter']->hostid;
	$data['displayNodes'] = (is_array(get_current_nodeid())
		&& $data['pageFilter']->groupid == 0 && $data['pageFilter']->hostid == 0);

	if ($data['pageFilter']->hostsSelected) {
		// get application ids
		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();

		$data['applications'] = API::Application()->get(array(
			'hostids' => ($data['pageFilter']->hostid > 0) ? $data['pageFilter']->hostid : null,
			'groupids' => ($data['pageFilter']->groupid > 0) ? $data['pageFilter']->groupid : null,
			'output' => array('applicationid'),
			'editable' => true,
			'sortfield' => $sortfield,
			'limit' => $config['search_limit'] + 1
		));

		// get applications
		$data['applications'] = API::Application()->get(array(
			'applicationids' => zbx_objectValues($data['applications'], 'applicationid'),
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => array('itemid'),
			'expandData' => true
		));

		order_result($data['applications'], $sortfield, $sortorder);

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

		// nodes
		if ($data['displayNodes']) {
			foreach ($data['applications'] as $key => $application) {
				$data['applications'][$key]['nodename'] = get_node_name_by_elid($application['applicationid'], true);
			}
		}
	}
	else {
		$data['applications'] = array();
	}

	// get paging
	$data['paging'] = getPagingLine($data['applications'], array('applicationid'));

	// render view
	$applicationView = new CView('configuration.application.list', $data);
	$applicationView->render();
	$applicationView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

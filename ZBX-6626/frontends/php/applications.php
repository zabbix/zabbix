<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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
$page['scripts'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'applications' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO, 'isset({form})&&!isset({applicationid})'),
	'groupid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,	null),
	'applicationid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'isset({form})&&{form}=="update"'),
	'appname' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})', _('Name')),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,	null)
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
	if (empty($dbApplication)) {
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
if (get_request('groupid', 0) > 0) {
	$groupids = available_groups($_REQUEST['groupid'], 1);
	if (empty($groupids)) {
		access_deny();
	}
}
if (get_request('hostid', 0) > 0) {
	$hostids = available_hosts($_REQUEST['hostid'], 1);
	if (empty($hostids)) {
		access_deny();
	}
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

		$action = AUDIT_ACTION_UPDATE;
		$msgOk = _('Application updated');
		$msgFail = _('Cannot update application');
	}
	else {
		$dbApplications = API::Application()->create($application);

		$action = AUDIT_ACTION_ADD;
		$msgOk = _('Application added');
		$msgFail = _('Cannot add application');
	}
	$result = DBend($dbApplications);

	show_messages($result, $msgOk, $msgFail);

	if ($result) {
		$applicationid = reset($dbApplications['applicationids']);
		add_audit($action, AUDIT_RESOURCE_APPLICATION, _('Application').' ['.$_REQUEST['appname'].' ] ['.$applicationid.']');
		unset($_REQUEST['form']);
	}
	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['applicationid'])) {
	unset($_REQUEST['applicationid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['delete'])) {
	if (isset($_REQUEST['applicationid'])) {
		$result = false;
		if ($app = get_application_by_applicationid($_REQUEST['applicationid'])) {
			$host = get_host_by_hostid($app['hostid']);

			DBstart();
			$result = API::Application()->delete($_REQUEST['applicationid']);
			$result = DBend($result);
		}
		show_messages($result, _('Application deleted'), _('Cannot delete application'));

		if ($result) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_APPLICATION, 'Application ['.$app['name'].'] from host ['.$host['host'].']');
		}
		unset($_REQUEST['form'], $_REQUEST['applicationid']);
	}
}
elseif ($_REQUEST['go'] == 'delete') {
	$go_result = true;
	$applications = get_request('applications', array());

	DBstart();
	$dbApplications = DBselect(
		'SELECT a.applicationid,a.name,a.hostid'.
		' FROM applications a'.
		' WHERE '.dbConditionInt('a.applicationid', $applications).
			andDbNode('a.applicationid')
	);
	while ($db_app = DBfetch($dbApplications)) {
		if (!isset($applications[$db_app['applicationid']])) {
			continue;
		}
		$go_result &= (bool) API::Application()->delete($db_app['applicationid']);
		if ($go_result) {
			$host = get_host_by_hostid($db_app['hostid']);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_APPLICATION, 'Application ['.$db_app['name'].'] from host ['.$host['host'].']');
		}
	}
	$go_result = DBend($go_result);

	show_messages($go_result, _('Application deleted'), _('Cannot delete application'));
}
elseif (str_in_array($_REQUEST['go'], array('activate', 'disable'))) {
	$go_result = true;
	$applications = get_request('applications', array());

	DBstart();
	foreach ($applications as $id => $appid) {
		$db_items = DBselect(
			'SELECT ia.itemid,i.hostid,i.key_'.
			' FROM items_applications ia'.
				' LEFT JOIN items i ON ia.itemid=i.itemid'.
			' WHERE ia.applicationid='.$appid.
				' AND i.hostid='.$_REQUEST['hostid'].
				' AND i.type<>'.ITEM_TYPE_HTTPTEST.
				andDbNode('ia.applicationid')
		);
		while ($item = DBfetch($db_items)) {
			if ($_REQUEST['go'] == 'activate') {
				$go_result &= activate_item($item['itemid']);
			}
			else {
				$go_result &= disable_item($item['itemid']);
			}
		}
	}
	$go_result = DBend($go_result);
	if ($_REQUEST['go'] == 'activate') {
		show_messages($go_result, _('Items activated'), null);
	}
	else {
		show_messages($go_result, _('Items disabled'), null);
	}
}
if ($_REQUEST['go'] != 'none' && !empty($go_result)) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Dsiplay
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

	if ($data['pageFilter']->hostsSelected) {
		// get application ids
		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();

		$options = array(
			'output' => array('applicationid'),
			'editable' => true,
			'sortfield' => $sortfield,
			'limit' => $config['search_limit'] + 1
		);
		if ($data['pageFilter']->hostid > 0) {
			$options['hostids'] = $data['pageFilter']->hostid;
		}
		elseif ($data['pageFilter']->groupid > 0) {
			$options['groupids'] = $data['pageFilter']->groupid;
		}
		$data['applications'] = API::Application()->get($options);

		// get applications
		$data['applications'] = API::Application()->get(array(
			'applicationids' => zbx_objectValues($data['applications'], 'applicationid'),
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => API_OUTPUT_REFER,
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

			foreach ($data['applications'] as &$app) {
				if ($app['templateids']) {
					foreach ($applicationSourceParentIds[$app['applicationid']] as $parentAppId) {
						$app['sourceTemplates'][] = $parentTemplates[$parentAppId];
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

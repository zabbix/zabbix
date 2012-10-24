<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	'hosts' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'groups' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'hostids' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'groupids' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'applications' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, null,	DB_ID,	null),
	'groupid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,	null),
	'applicationid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'isset({form})&&({form}=="update")'),
	'appname' =>			array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 'isset({save})'),
	'apphostid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID.'{}>0', 'isset({save})'),
	'apptemplateid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,	null),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_to_group' =>		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID,	null),
	'delete_from_group' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID,	null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>		array(T_ZBX_STR, O_OPT, null,	null,	null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

/*
 * Permissions
 */
if (isset($_REQUEST['applicationid'])) {
	$dbApplication = API::Application()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'applicationids' => get_request('applicationid')
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
		foreach ($_REQUEST['applications'] as $applicationid) {
			$dbApplications = API::Application()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'applicationids' => $applicationid,
				'output' => API_OUTPUT_EXTEND
			));
			if (empty($dbApplications)) {
				access_deny();
			}
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
if (get_request('apphostid', 0) > 0) {
	$hostids = available_hosts($_REQUEST['apphostid'], 1);
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
		'hostid' => $_REQUEST['apphostid']
	);

	if (isset($_REQUEST['applicationid'])) {
		$application['applicationid'] = $_REQUEST['applicationid'];
		$dbApplications = API::Application()->update($application);

		$action = AUDIT_ACTION_UPDATE;
		$msg_ok = _('Application updated');
		$msg_fail = _('Cannot update application');
	}
	else{
		$dbApplications = API::Application()->create($application);

		$action = AUDIT_ACTION_ADD;
		$msg_ok = _('Application added');
		$msg_fail = _('Cannot add application');
	}
	$result = DBend($dbApplications);

	show_messages($result, $msg_ok, $msg_fail);
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
		' WHERE '.DBin_node('a.applicationid').
			' AND '.DBcondition('a.applicationid', $applications)
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
				' AND i.type<>9'.
				' AND '.DBin_node('ia.applicationid')
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
$data = array();
if (isset($_REQUEST['form'])) {
	$data['applicationid'] = get_request('applicationid');
	$data['form'] = get_request('form');
	$data['form_refresh'] = get_request('form_refresh', 0);
	$data['groupid'] = get_request('groupid', 0);

	if (isset($data['applicationid']) && !isset($_REQUEST['form_refresh'])) {
		$dbApplication = reset($dbApplication);
		$data['appname'] = $dbApplication['name'];
		$data['apphostid'] = $dbApplication['hostid'];

	}
	else {
		$data['appname'] = get_request('appname', '');
		$data['apphostid'] = get_request('apphostid', get_request('hostid', 0));
	}

	// select the host for the navigation panel
	$data['hostid'] = get_request('hostid') ? get_request('hostid') : $data['apphostid'];

	// get application hostid
	$db_host = get_host_by_hostid($data['apphostid'], 1);
	if ($db_host) {
		$data['hostname'] = $db_host['name'];
	}
	else {
		$data['hostname'] = '';
	}

	// render view
	$applicationView = new CView('configuration.application.edit', $data);
	$applicationView->render();
	$applicationView->show();
}
else {
	$options = array(
		'groups' => array('editable' => 1, 'with_hosts_and_templates' => true),
		'hosts' => array('editable' => 1, 'templated_hosts' => 1),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null)
	);
	$data['pageFilter'] = new CPageFilter($options);
	$data['groupid'] = $data['pageFilter']->groupid;
	$data['hostid'] = $data['pageFilter']->hostid;

	if ($data['pageFilter']->hostsSelected) {
		// get application
		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();
		$options = array(
			'output' => API_OUTPUT_SHORTEN,
			'editable' => 1,
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
		$options = array(
			'applicationids' => zbx_objectValues($data['applications'], 'applicationid'),
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => API_OUTPUT_REFER,
			'expandData' => 1
		);
		$data['applications'] = API::Application()->get($options);
		order_result($data['applications'], $sortfield, $sortorder);

		// fill applications with templated hosts
		foreach ($data['applications'] as $id => $application) {
			if (!empty($application['templateid'])) {
				$data['applications'][$id]['template_host'] = get_realhost_by_applicationid($application['templateid']);
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
?>

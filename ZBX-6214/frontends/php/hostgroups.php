<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

$page['title'] = _('Configuration of host groups');
$page['file'] = 'hostgroups.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hosts' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'groups' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostids' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'groupids' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// group
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'name' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})'),
	'twb_groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	// other
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>	array(T_ZBX_STR, O_OPT, null,	null,		null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

// validate permissions
if (get_request('groupid', 0) > 0) {
	$groupids = available_groups($_REQUEST['groupid'], 1);
	if (empty($groupids)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['groupid'])) {
	unset($_REQUEST['groupid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$objects = get_request('hosts', array());
	$hosts = API::Host()->get(array(
		'hostids' => $objects,
		'output' => API_OUTPUT_SHORTEN
	));
	$templates = API::Template()->get(array(
		'templateids' => $objects,
		'output' => API_OUTPUT_SHORTEN
	));

	if (!empty($_REQUEST['groupid'])) {
		DBstart();
		$old_group = API::HostGroup()->get(array(
			'groupids' => $_REQUEST['groupid'],
			'output' => API_OUTPUT_EXTEND
		));
		$old_group = reset($old_group);

		$result = API::HostGroup()->update(array(
			'groupid' => $_REQUEST['groupid'],
			'name' => $_REQUEST['name']
		));

		if ($result) {
			$options = array(
				'groupids' => $result['groupids'],
				'output' => API_OUTPUT_EXTEND
			);
			$groups = API::HostGroup()->get($options);

			$options = array(
				'hosts' => $hosts,
				'templates' => $templates,
				'groups' => $groups
			);
			$result = API::HostGroup()->massUpdate($options);
		}
		$result = DBend($result);
		if ($result) {
			$group = reset($groups);
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST_GROUP, $group['groupid'], $group['name'], 'groups', array('name' => $old_group['name']), array('name' => $group['name']));
		}
		$msg_ok = _('Group updated');
		$msg_fail = _('Cannot update group');
	}
	else {
		if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
			access_deny();
		}

		DBstart();
		$result = API::HostGroup()->create(array('name' => $_REQUEST['name']));
		if ($result) {
			$options = array(
				'groupids' => $result['groupids'],
				'output' => API_OUTPUT_EXTEND
			);
			$groups = API::HostGroup()->get($options);

			$options = array(
				'hosts' => $hosts,
				'templates' => $templates,
				'groups' => $groups
			);
			$result = API::HostGroup()->massAdd($options);

			if ($result) {
				$group = reset($groups);
				add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST_GROUP, $group['groupid'], $group['name'], null, null, null);
			}
		}
		$result = DBend($result);
		$msg_ok = _('Group added');
		$msg_fail = _('Cannot add group');
	}
	show_messages($result, $msg_ok, $msg_fail);
	if ($result) {
		unset($_REQUEST['form']);
	}
	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['groupid'])) {
	$result = API::HostGroup()->delete($_REQUEST['groupid']);
	show_messages($result, _('Group deleted'), _('Cannot delete group'));

	if ($result) {
		unset($_REQUEST['form']);
	}
	unset($_REQUEST['delete']);
}
elseif ($_REQUEST['go'] == 'delete') {
	$groups = get_request('groups', array());
	$go_result = API::HostGroup()->delete($groups);

	show_messages($go_result, _('Group deleted'), _('Cannot delete group'));
}
elseif (str_in_array($_REQUEST['go'], array('activate', 'disable'))) {
	$status = $_REQUEST['go'] == 'activate' ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$groups = get_request('groups', array());
	if (!empty($groups)) {
		DBstart();
		$hosts = API::Host()->get(array(
			'groupids' => $groups,
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND
		));

		if (empty($hosts)) {
			$go_result = true;
		}
		else {
			$go_result = API::Host()->massUpdate(array('hosts' => $hosts, 'status' => $status));
			if ($go_result) {
				foreach ($hosts as $host) {
					add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST,
						$host['hostid'],
						$host['host'],
						'hosts',
						array('status' => $host['status']),
						array('status' => $status));
				}
			}
		}
		$go_result = DBend($go_result);
		show_messages($go_result, _('Host status updated'), _('Cannot update host'));
	}
}
if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Display
 */
$data = array('form' => get_request('form'));
if (isset($_REQUEST['form'])) {
	$data['groupid'] = get_request('groupid', 0);
	$data['hosts'] = get_request('hosts', array());
	$data['name'] = get_request('name', '');
	$data['twb_groupid'] = get_request('twb_groupid', -1);
	if ($data['groupid'] > 0) {
		$data['group'] = get_hostgroup_by_groupid($data['groupid']);

		// if first time select all hosts for group from db
		if (!isset($_REQUEST['form_refresh'])) {
			$data['name'] = $data['group']['name'];

			$options = array(
				'groupids' => $data['groupid'],
				'templated_hosts' => 1,
				'output' => API_OUTPUT_SHORTEN
			);
			$data['hosts'] = API::Host()->get($options);
			$data['hosts'] = zbx_objectValues($data['hosts'], 'hostid');
			$data['hosts'] = zbx_toHash($data['hosts'], 'hostid');
		}
	}

	// get all possible groups
	$options = array(
		'not_proxy_host' => 1,
		'sortfield' => 'name',
		'editable' => true,
		'output' => API_OUTPUT_EXTEND
	);
	$data['db_groups'] = API::HostGroup()->get($options);

	if ($data['twb_groupid'] == -1) {
		$gr = reset($data['db_groups']);
		$data['twb_groupid'] = $gr['groupid'];
	}

	if ($data['twb_groupid'] == 0) {
		// get all hosts from all groups
		$options = array(
			'templated_hosts' => 1,
			'sortfield' => 'name',
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND
		);
	}
	else {
		// get hosts from selected twb_groupid combo
		$options = array(
			'groupids' => $data['twb_groupid'],
			'templated_hosts' => 1,
			'sortfield' => 'name',
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND
		);
	}
	$data['db_hosts'] = API::Host()->get($options);

	// get selected hosts
	$options = array(
		'hostids' => $data['hosts'],
		'templated_hosts' => 1,
		'sortfield' => 'name',
		'output' => API_OUTPUT_EXTEND
	);
	$data['r_hosts'] = API::Host()->get($options);

	// get hosts ids
	$options = array(
		'hostids' => $data['hosts'],
		'templated_hosts' =>1,
		'editable' => 1,
		'output' => API_OUTPUT_SHORTEN
	);
	$data['rw_hosts'] = API::Host()->get($options);
	$data['rw_hosts'] = zbx_toHash($data['rw_hosts'], 'hostid');

	if (!empty($data['groupid'])) {
		$data['deletableHostGroups'] = getDeletableHostGroups($data['groupid']);
	}

	// render view
	$hostgroupView = new CView('configuration.hostgroups.edit', $data);
	$hostgroupView->render();
	$hostgroupView->show();
}
else {
	$data['config'] = $config;

	$sortfield = getPageSortField('name');
	$sortorder =  getPageSortOrder();
	$options = array(
		'editable' => 1,
		'output' => API_OUTPUT_SHORTEN,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	);
	$groups = API::HostGroup()->get($options);

	$data['paging'] = getPagingLine($groups);

	// get hosts and templates count
	$options = array(
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'selectHosts' => API_OUTPUT_COUNT,
		'selectTemplates' => API_OUTPUT_COUNT,
		'nopermissions' => 1
	);
	$data['groupCounts'] = API::HostGroup()->get($options);
	$data['groupCounts'] = zbx_toHash($data['groupCounts'], 'groupid');

	// get host groups
	$options = array(
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'selectHosts' => array('hostid', 'name', 'status'),
		'selectTemplates' => array('hostid', 'name', 'status'),
		'output' => API_OUTPUT_EXTEND,
		'nopermissions' => 1,
		'limitSelects' => $config['max_in_table'] + 1
	);
	$data['groups'] = API::HostGroup()->get($options);
	order_result($data['groups'], $sortfield, $sortorder);

	// render view
	$hostgroupView = new CView('configuration.hostgroups.list', $data);
	$hostgroupView->render();
	$hostgroupView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';


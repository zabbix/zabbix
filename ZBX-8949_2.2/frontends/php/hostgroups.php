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
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
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

/*
 * Permissions
 */
if (get_request('groupid') && !API::HostGroup()->isWritable(array($_REQUEST['groupid']))) {
	access_deny();
}

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['groupid'])) {
	unset($_REQUEST['groupid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$hostIds = get_request('hosts', array());

	$hosts = API::Host()->get(array(
		'hostids' => $hostIds,
		'output' => array('hostid'),
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		'preservekeys' => true
	));

	$templates = API::Template()->get(array(
		'templateids' => $hostIds,
		'output' => array('templateid'),
		'preservekeys' => true
	));

	$groupId = getRequest('groupid', 0);

	if ($groupId != 0) {
		DBstart();

		$oldGroup = API::HostGroup()->get(array(
			'groupids' => $_REQUEST['groupid'],
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('hostid'),
			'selectTemplates' => array('templateid')
		));
		$oldGroup = reset($oldGroup);

		$result = true;
		// don't try to update the name for a discovered host group
		if ($oldGroup['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
			$result = API::HostGroup()->update(array(
				'groupid' => $groupId,
				'name' => $_REQUEST['name']
			));
		}

		if ($result) {
			$groups = API::HostGroup()->get(array(
				'groupids' => $_REQUEST['groupid'],
				'output' => API_OUTPUT_EXTEND
			));

			$hostIdsToAdd = array();
			$hostIdsToRemove = array();
			$templateIdsToAdd = array();
			$templateIdsToRemove = array();

			$oldHostIds = zbx_objectValues($oldGroup['hosts'], 'hostid');
			$newHostIds = array_keys($hosts);
			$oldTemplateIds = zbx_objectValues($oldGroup['templates'], 'templateid');
			$newTemplateIds = array_keys($templates);

			foreach (array_diff($newHostIds, $oldHostIds) as $hostId) {
				$hostIdsToAdd[$hostId] = $hostId;
			}

			foreach (array_diff($oldHostIds, $newHostIds) as $hostId) {
				$hostIdsToRemove[$hostId] = $hostId;
			}

			foreach (array_diff($newTemplateIds, $oldTemplateIds) as $templateId) {
				$templateIdsToAdd[$templateId] = $templateId;
			}

			foreach (array_diff($oldTemplateIds, $newTemplateIds) as $templateId) {
				$templateIdsToRemove[$templateId] = $templateId;
			}

			if ($hostIdsToAdd || $templateIdsToAdd) {
				$massAdd = array(
					'groups' => array('groupid' => $groupId)
				);

				if ($hostIdsToAdd) {
					$massAdd['hosts'] = zbx_toObject($hostIdsToAdd, 'hostid');
				}

				if ($templateIdsToAdd) {
					$massAdd['templates'] = zbx_toObject($templateIdsToAdd, 'templateid');
				}

				$result &= (bool) API::HostGroup()->massAdd($massAdd);
			}

			if ($hostIdsToRemove || $templateIdsToRemove) {
				$massRemove = array(
					'groupids' => array($groupId)
				);

				if ($hostIdsToRemove) {
					$massRemove['hostids'] = $hostIdsToRemove;
				}

				if ($templateIdsToRemove) {
					$massRemove['templateids'] = $templateIdsToRemove;
				}

				$result &= (bool) API::HostGroup()->massRemove($massRemove);
			}

			if ($result) {
				$group = reset($groups);

				add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST_GROUP, $group['groupid'], $group['name'],
					'groups', array('name' => $oldGroup['name']), array('name' => $group['name']));
			}
		}

		$result = DBend($result);

		$msgOk = _('Group updated');
		$msgFail = _('Cannot update group');
	}
	else {
		DBstart();

		$result = API::HostGroup()->create(array('name' => $_REQUEST['name']));

		if ($result) {
			$groups = API::HostGroup()->get(array(
				'groupids' => $result['groupids'],
				'output' => API_OUTPUT_EXTEND
			));

			$result = API::HostGroup()->massAdd(array(
				'hosts' => $hosts,
				'templates' => $templates,
				'groups' => $groups
			));

			if ($result) {
				$group = reset($groups);

				add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST_GROUP, $group['groupid'], $group['name'], null, null, null);
			}
		}

		$result = DBend($result);

		$msgOk = _('Group added');
		$msgFail = _('Cannot add group');
	}

	show_messages($result, $msgOk, $msgFail);

	if ($result) {
		unset($_REQUEST['form']);
		clearCookies($result);
	}
	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['groupid'])) {
	$result = API::HostGroup()->delete($_REQUEST['groupid']);

	show_messages($result, _('Group deleted'), _('Cannot delete group'));

	if ($result) {
		unset($_REQUEST['form']);
		clearCookies($result);
	}
	unset($_REQUEST['delete']);
}
elseif ($_REQUEST['go'] == 'delete') {
	$goResult = API::HostGroup()->delete(get_request('groups', array()));

	show_messages($goResult, _('Group deleted'), _('Cannot delete group'));
	clearCookies($goResult);
}
elseif (str_in_array(getRequest('go'), array('activate', 'disable'))) {
	$enable =(getRequest('go') == 'activate');
	$status = $enable ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;

	$groups = getRequest('groups', array());

	if ($groups) {
		DBstart();

		$hosts = API::Host()->get(array(
			'groupids' => $groups,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND
		));

		if ($hosts) {
			$result = API::Host()->massUpdate(array(
				'hosts' => $hosts,
				'status' => $status
			));

			if ($result) {
				foreach ($hosts as $host) {
					add_audit_ext(
						$auditAction,
						AUDIT_RESOURCE_HOST,
						$host['hostid'],
						$host['host'],
						'hosts',
						array('status' => $host['status']),
						array('status' => $status)
					);
				}
			}
		}
		else {
			$result = true;
		}

		$result = DBend($result);

		$updated = count($hosts);

		$messageSuccess = $enable
			? _n('Host enabled', 'Hosts enabled', $updated)
			: _n('Host disabled', 'Hosts disabled', $updated);
		$messageFailed = $enable
			? _n('Cannot enable host', 'Cannot enable hosts', $updated)
			: _n('Cannot disable host', 'Cannot disable hosts', $updated);

		show_messages($result, $messageSuccess, $messageFailed);
		clearCookies($result);
	}
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => get_request('form'),
		'groupid' => get_request('groupid', 0),
		'hosts' => get_request('hosts', array()),
		'name' => get_request('name', ''),
		'twb_groupid' => get_request('twb_groupid', -1)
	);

	if ($data['groupid'] > 0) {
		$data['group'] = get_hostgroup_by_groupid($data['groupid']);

		// if first time select all hosts for group from db
		if (!isset($_REQUEST['form_refresh'])) {
			$data['name'] = $data['group']['name'];

			$data['hosts'] = API::Host()->get(array(
				'groupids' => $data['groupid'],
				'templated_hosts' => true,
				'output' => array('hostid')
			));

			$data['hosts'] = zbx_toHash(zbx_objectValues($data['hosts'], 'hostid'), 'hostid');
		}
	}

	// get all possible groups
	$data['db_groups'] = API::HostGroup()->get(array(
		'not_proxy_host' => true,
		'sortfield' => 'name',
		'editable' => true,
		'output' => API_OUTPUT_EXTEND
	));

	if ($data['twb_groupid'] == -1) {
		$gr = reset($data['db_groups']);

		$data['twb_groupid'] = $gr['groupid'];
	}

	// get all possible hosts
	$data['db_hosts'] = API::Host()->get(array(
		'groupids' => $data['twb_groupid'] ? $data['twb_groupid'] : null,
		'templated_hosts' => true,
		'sortfield' => 'name',
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
	));

	// get selected hosts
	$data['r_hosts'] = API::Host()->get(array(
		'hostids' => $data['hosts'],
		'templated_hosts' => true,
		'sortfield' => 'name',
		'output' => API_OUTPUT_EXTEND
	));
	$data['r_hosts'] = zbx_toHash($data['r_hosts'], 'hostid');

	// deletable groups
	if (!empty($data['groupid'])) {
		$data['deletableHostGroups'] = getDeletableHostGroups($data['groupid']);
	}

	// nodes
	if (is_array(get_current_nodeid())) {
		foreach ($data['db_groups'] as $key => $group) {
			$data['db_groups'][$key]['name'] =
				get_node_name_by_elid($group['groupid'], true, NAME_DELIMITER).$group['name'];
		}

		foreach ($data['r_hosts'] as $key => $host) {
			$data['r_hosts'][$key]['name'] = get_node_name_by_elid($host['hostid'], true, NAME_DELIMITER).$host['name'];
		}

		if (!$data['twb_groupid']) {
			foreach ($data['db_hosts'] as $key => $host) {
				$data['db_hosts'][$key]['name'] =
					get_node_name_by_elid($host['hostid'], true, NAME_DELIMITER).$host['name'];
			}
		}
	}

	// render view
	$hostgroupView = new CView('configuration.hostgroups.edit', $data);
	$hostgroupView->render();
	$hostgroupView->show();
}
else {
	$data = array(
		'config' => $config,
		'displayNodes' => is_array(get_current_nodeid())
	);

	$sortfield = getPageSortField('name');
	$sortorder =  getPageSortOrder();

	$groups = API::HostGroup()->get(array(
		'editable' => true,
		'output' => array('groupid', $sortfield),
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));
	order_result($groups, $sortfield, $sortorder);

	$data['paging'] = getPagingLine($groups, array('groupid'));

	// get hosts and templates count
	$data['groupCounts'] = API::HostGroup()->get(array(
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'selectHosts' => API_OUTPUT_COUNT,
		'selectTemplates' => API_OUTPUT_COUNT,
		'nopermissions' => true
	));
	$data['groupCounts'] = zbx_toHash($data['groupCounts'], 'groupid');

	// get host groups
	$data['groups'] = API::HostGroup()->get(array(
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'selectHosts' => array('hostid', 'name', 'status'),
		'selectTemplates' => array('hostid', 'name', 'status'),
		'selectGroupDiscovery' => array('ts_delete'),
		'selectDiscoveryRule' => array('itemid', 'name'),
		'output' => API_OUTPUT_EXTEND,
		'limitSelects' => $config['max_in_table'] + 1
	));
	order_result($data['groups'], $sortfield, $sortorder);

	// nodes
	if ($data['displayNodes']) {
		foreach ($data['groups'] as $key => $group) {
			$data['groups'][$key]['nodename'] = get_node_name_by_elid($group['groupid'], true);
		}
	}

	// render view
	$hostgroupView = new CView('configuration.hostgroups.list', $data);
	$hostgroupView->render();
	$hostgroupView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

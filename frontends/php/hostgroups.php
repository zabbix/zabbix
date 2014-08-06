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

$page['title'] = _('Configuration of host groups');
$page['file'] = 'hostgroups.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hosts' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'groups' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'groupids' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// group
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
	'name' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Group name')),
	'twb_groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	// other
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,		null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP, array('name'));

/*
 * Form actions
 */
if (hasRequest('form')) {
	if (hasRequest('clone')) {
		unset($_REQUEST['groupid']);
	}
	elseif (hasRequest('save')) {
		$hostIds = getRequest('hosts', array());

		DBstart();

		if (getRequest('groupid', 0) != 0) {
			$messageSuccess = _('Group updated');
			$messageFailed = _('Cannot update group');

			$data = array(
				'groupid' => getRequest('groupid'),
				'name' => getRequest('name')
			);

			$oldGroups = API::HostGroup()->get(array(
				'output' => array('name', 'flags'),
				'groupids' => $data['groupid']
			));
			if (!$oldGroups) {
				access_deny();
			}
			$oldGroup = reset($oldGroups);

			$result = true;

			// don't try to update the name for a discovered host group
			if ($oldGroup['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
				$result = API::HostGroup()->update($data);
			}

			if ($result) {
				$hosts = API::Host()->get(array(
					'output' => array('hostid'),
					'hostids' => $hostIds,
					'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
				));

				$templates = API::Template()->get(array(
					'output' => array('templateid'),
					'templateids' => $hostIds
				));

				$result = API::HostGroup()->massUpdate(array(
					'groups' => array(array('groupid' => $data['groupid'])),
					'hosts' => $hosts,
					'templates' => $templates
				));

				if ($result) {
					add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST_GROUP, $data['groupid'], $data['name'],
						'groups', array('name' => $oldGroup['name']), array('name' => $data['name']));
				}
			}
		}
		else {
			$messageSuccess = _('Group added');
			$messageFailed = _('Cannot add group');

			$data = array(
				'name' => getRequest('name')
			);

			$result = API::HostGroup()->create(array('name' => $data['name']));

			if ($result) {
				$data['groupid'] = $result['groupids'][0];

				$hosts = API::Host()->get(array(
					'output' => array('hostid'),
					'hostids' => $hostIds,
					'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
				));

				$templates = API::Template()->get(array(
					'output' => array('templateid'),
					'templateids' => $hostIds
				));

				$result = API::HostGroup()->massAdd(array(
					'groups' => array(array('groupid' => $data['groupid'])),
					'hosts' => $hosts,
					'templates' => $templates
				));

				if ($result) {
					add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST_GROUP, $data['groupid'], $data['name'],
						null, null, null);
				}
			}
		}

		$result = DBend($result);

		if ($result) {
			unset($_REQUEST['form']);
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);

		unset($_REQUEST['save']);
	}
	elseif (hasRequest('delete') && hasRequest('groupid')) {
		$result = API::HostGroup()->delete(array(getRequest('groupid')));

		if ($result) {
			unset($_REQUEST['form']);
			uncheckTableRows();
		}
		show_messages($result, _('Group deleted'), _('Cannot delete group'));

		unset($_REQUEST['delete']);
	}
}
/*
 * List actions
 */
elseif (hasRequest('go')) {
	if (getRequest('go') == 'delete') {
		$groupIds = getRequest('groups', array());

		if ($groupIds) {
			$result = API::HostGroup()->delete($groupIds);

			$updated = count($groupIds);

			if ($result) {
				uncheckTableRows();
			}
			show_messages($result,
				_n('Group deleted', 'Groups deleted', $updated),
				_n('Cannot delete group', 'Cannot delete groups', $updated)
			);
		}
	}
	elseif (getRequest('go') == 'activate' || getRequest('go') == 'disable') {
		$enable = (getRequest('go') == 'activate');
		$status = $enable ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;

		$groupIds = getRequest('groups', array());

		if ($groupIds) {
			DBstart();

			$hosts = API::Host()->get(array(
				'output' => array('hostid', 'status', 'host'),
				'groupids' => $groupIds,
				'editable' => true
			));

			$result = true;

			if ($hosts) {
				$result = API::Host()->massUpdate(array(
					'hosts' => $hosts,
					'status' => $status
				));

				if ($result) {
					foreach ($hosts as $host) {
						add_audit_ext($auditAction, AUDIT_RESOURCE_HOST, $host['hostid'], $host['host'], 'hosts',
							array('status' => $host['status']), array('status' => $status)
						);
					}
				}
			}

			$result = DBend($result);

			if ($result) {
				uncheckTableRows();
			}

			$updated = count($hosts);

			$messageSuccess = $enable
				? _n('Host enabled', 'Hosts enabled', $updated)
				: _n('Host disabled', 'Hosts disabled', $updated);
			$messageFailed = $enable
				? _n('Cannot enable host', 'Cannot enable hosts', $updated)
				: _n('Cannot disable host', 'Cannot disable hosts', $updated);

			show_messages($result, $messageSuccess, $messageFailed);
		}
	}
}

/*
 * Display form
 */
if (hasRequest('form')) {
	$data = array(
		'form' => getRequest('form'),
		'groupid' => getRequest('groupid', 0),
		'name' => getRequest('name', ''),
		'hosts' => getRequest('hosts', array()),
		'twb_groupid' => getRequest('twb_groupid', -1),
		'r_hosts' => array()
	);

	if ($data['groupid'] != 0) {
		/*
		 * Permissions
		 */
		$groups = API::HostGroup()->get(array(
			'output' => array('name', 'flags'),
			'groupids' => $data['groupid'],
			'editable' => true
		));
		if (!$groups) {
			access_deny();
		}

		$data['group'] = reset($groups);

		// if first time select all hosts for group from db
		if (!hasRequest('form_refresh')) {
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
		'output' => array('groupid', 'name'),
		'with_hosts_and_templates' => true,
		'editable' => true
	));
	order_result($data['db_groups'], 'name');

	if ($data['twb_groupid'] == -1) {
		$dbGroup = reset($data['db_groups']);

		$data['twb_groupid'] = $dbGroup['groupid'];
	}

	// get all possible hosts
	$data['db_hosts'] = API::Host()->get(array(
		'output' => array('hostid', 'name'),
		'groupids' => $data['twb_groupid'] ? $data['twb_groupid'] : null,
		'templated_hosts' => true,
		'editable' => true,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
	));
	order_result($data['db_hosts'], 'name');

	// get selected hosts
	if ($data['hosts']) {
		$data['r_hosts'] = API::Host()->get(array(
			'output' => array('hostid', 'name', 'flags'),
			'hostids' => $data['hosts'],
			'templated_hosts' => true,
			'preservekeys' => true
		));
		order_result($data['r_hosts'], 'name');
	}

	// deletable groups
	if ($data['groupid'] != 0) {
		$data['deletableHostGroups'] = getDeletableHostGroupIds(array($data['groupid']));
	}

	// render view
	$hostgroupView = new CView('configuration.hostgroups.edit', $data);
	$hostgroupView->render();
	$hostgroupView->show();
}
/*
 * Display list
 */
else {
	$data = array(
		'config' => $config
	);

	$sortfield = getPageSortField('name');
	$sortorder =  getPageSortOrder();

	$groups = API::HostGroup()->get(array(
		'output' => array('groupid'),
		'editable' => true,
		'sortfield' => $sortfield,
		'sortorder' => $sortorder,
		'limit' => $config['search_limit'] + 1
	));

	$data['paging'] = getPagingLine($groups);
	$groupIds = zbx_objectValues($groups, 'groupid');

	// get hosts and templates count
	$data['groupCounts'] = API::HostGroup()->get(array(
		'output' => array('groupid'),
		'groupids' => $groupIds,
		'selectHosts' => API_OUTPUT_COUNT,
		'selectTemplates' => API_OUTPUT_COUNT,
		'preservekeys' => true
	));

	// get host groups
	$data['groups'] = API::HostGroup()->get(array(
		'output' => array('groupid', 'name', 'flags'),
		'groupids' => $groupIds,
		'selectHosts' => array('hostid', 'name', 'status'),
		'selectTemplates' => array('templateid', 'name'),
		'selectGroupDiscovery' => array('ts_delete'),
		'selectDiscoveryRule' => array('itemid', 'name'),
		'limitSelects' => $config['max_in_table'] + 1
	));
	order_result($data['groups'], $sortfield, $sortorder);

	// render view
	$hostgroupView = new CView('configuration.hostgroups.list', $data);
	$hostgroupView->render();
	$hostgroupView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

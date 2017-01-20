<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hosts' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'groups' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'groupids' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	// group
	'groupid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Group name')],
	'twb_groupid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	// actions
	'action' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
							IN('"hostgroup.massdelete","hostgroup.massdisable","hostgroup.massenable"'),
							null
						],
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	// other
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	// filter
	'filter_set' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name' =>	[T_ZBX_STR, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null],
	'sortorder' =>		[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Form actions
 */
if (hasRequest('form')) {
	if (hasRequest('clone')) {
		unset($_REQUEST['groupid']);
	}
	elseif (hasRequest('add') || hasRequest('update')) {
		$hostIds = getRequest('hosts', []);
		$groupId = getRequest('groupid');
		$name = getRequest('name');

		DBstart();

		if ($groupId) {
			$messageSuccess = _('Group updated');
			$messageFailed = _('Cannot update group');

			$data = [
				'groupid' => getRequest('groupid'),
				'name' => getRequest('name')
			];

			$oldGroups = API::HostGroup()->get([
				'output' => ['name', 'flags'],
				'selectHosts' => ['hostid'],
				'selectTemplates' => ['templateid'],
				'groupids' => [$groupId]
			]);
			if (!$oldGroups) {
				access_deny();
			}
			$oldGroup = reset($oldGroups);

			$result = true;

			// don't try to update the name for a discovered host group
			if ($oldGroup['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
				$result = API::HostGroup()->update([
					'groupid' => $groupId,
					'name' => $name
				]);
			}

			if ($result) {
				$hosts = API::Host()->get([
					'output' => ['hostid'],
					'hostids' => $hostIds,
					'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
					'preservekeys' => true
				]);

				$templates = API::Template()->get([
					'output' => ['templateid'],
					'templateids' => $hostIds,
					'preservekeys' => true
				]);

				$hostIdsToAdd = [];
				$hostIdsToRemove = [];
				$templateIdsToAdd = [];
				$templateIdsToRemove = [];

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
					$massAdd = [
						'groups' => ['groupid' => $groupId]
					];

					if ($hostIdsToAdd) {
						$massAdd['hosts'] = zbx_toObject($hostIdsToAdd, 'hostid');
					}

					if ($templateIdsToAdd) {
						$massAdd['templates'] = zbx_toObject($templateIdsToAdd, 'templateid');
					}

					$result &= (bool) API::HostGroup()->massAdd($massAdd);
				}

				if ($hostIdsToRemove || $templateIdsToRemove) {
					$massRemove = [
						'groupids' => [$groupId]
					];

					if ($hostIdsToRemove) {
						$massRemove['hostids'] = $hostIdsToRemove;
					}

					if ($templateIdsToRemove) {
						$massRemove['templateids'] = $templateIdsToRemove;
					}

					$result &= (bool) API::HostGroup()->massRemove($massRemove);
				}

				if ($result) {
					add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST_GROUP, $groupId, $name, 'groups',
						['name' => $oldGroup['name']], ['name' => $name]
					);
				}
			}
		}
		else {
			$messageSuccess = _('Group added');
			$messageFailed = _('Cannot add group');

			$result = API::HostGroup()->create(['name' => $name]);

			if ($result) {
				$groupId = $result['groupids'][0];

				$hosts = API::Host()->get([
					'output' => ['hostid'],
					'hostids' => $hostIds,
					'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
				]);

				$templates = API::Template()->get([
					'output' => ['templateid'],
					'templateids' => $hostIds
				]);

				$result = API::HostGroup()->massAdd([
					'groups' => [['groupid' => $groupId]],
					'hosts' => $hosts,
					'templates' => $templates
				]);

				if ($result) {
					add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST_GROUP, $groupId, $name, null, null, null);
				}
			}
		}

		$result = DBend($result);

		if ($result) {
			unset($_REQUEST['form']);
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
	elseif (hasRequest('delete') && hasRequest('groupid')) {
		$result = API::HostGroup()->delete([getRequest('groupid')]);

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
elseif (hasRequest('action')) {
	if (getRequest('action') == 'hostgroup.massdelete') {
		$groupIds = getRequest('groups', []);

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
	elseif (getRequest('action') == 'hostgroup.massenable' || getRequest('action') == 'hostgroup.massdisable') {
		$enable = (getRequest('action') == 'hostgroup.massenable');
		$status = $enable ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;

		$groupIds = getRequest('groups', []);

		if ($groupIds) {
			DBstart();

			$hosts = API::Host()->get([
				'output' => ['hostid', 'status', 'host'],
				'groupids' => $groupIds,
				'editable' => true
			]);

			$result = true;

			if ($hosts) {
				$result = API::Host()->massUpdate([
					'hosts' => $hosts,
					'status' => $status
				]);

				if ($result) {
					foreach ($hosts as $host) {
						add_audit_ext($auditAction, AUDIT_RESOURCE_HOST, $host['hostid'], $host['host'], 'hosts',
							['status' => $host['status']], ['status' => $status]
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
	$data = [
		'form' => getRequest('form'),
		'groupid' => getRequest('groupid', 0),
		'name' => getRequest('name', ''),
		'hosts' => getRequest('hosts', []),
		'twb_groupid' => getRequest('twb_groupid', -1),
		'r_hosts' => []
	];

	if ($data['groupid'] != 0) {
		/*
		 * Permissions
		 */
		$groups = API::HostGroup()->get([
			'output' => ['name', 'flags'],
			'groupids' => $data['groupid'],
			'editable' => true
		]);
		if (!$groups) {
			access_deny();
		}

		$data['group'] = reset($groups);

		// if first time select all hosts for group from db
		if (!hasRequest('form_refresh')) {
			$data['name'] = $data['group']['name'];

			$data['hosts'] = API::Host()->get([
				'groupids' => $data['groupid'],
				'templated_hosts' => true,
				'output' => ['hostid']
			]);

			$data['hosts'] = zbx_toHash(zbx_objectValues($data['hosts'], 'hostid'), 'hostid');
		}
	}

	// get all possible groups
	$data['db_groups'] = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'with_hosts_and_templates' => true,
		'editable' => true
	]);
	order_result($data['db_groups'], 'name');

	if ($data['twb_groupid'] == -1) {
		$dbGroup = reset($data['db_groups']);

		$data['twb_groupid'] = $dbGroup['groupid'];
	}

	// get all possible hosts
	$data['db_hosts'] = API::Host()->get([
		'output' => ['hostid', 'name'],
		'groupids' => $data['twb_groupid'] ? $data['twb_groupid'] : null,
		'templated_hosts' => true,
		'editable' => true,
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
	]);
	order_result($data['db_hosts'], 'name');

	// get selected hosts
	if ($data['hosts']) {
		$data['r_hosts'] = API::Host()->get([
			'output' => ['hostid', 'name', 'flags'],
			'hostids' => $data['hosts'],
			'templated_hosts' => true,
			'preservekeys' => true
		]);
		order_result($data['r_hosts'], 'name');
	}

	// deletable groups
	if ($data['groupid'] != 0) {
		$data['deletableHostGroups'] = getDeletableHostGroupIds([$data['groupid']]);
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
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.groups.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.groups.filter_name');
	}

	$filter = [
		'name' => CProfile::get('web.groups.filter_name', '')
	];

	$config = select_config();

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'config' => $config
	];

	$groups = API::HostGroup()->get([
		'output' => ['groupid', $sortField],
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name']
		],
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);
	order_result($groups, $sortField, $sortOrder);

	$data['paging'] = getPagingLine($groups, $sortOrder, new CUrl('hostgroups.php'));
	$groupIds = zbx_objectValues($groups, 'groupid');

	// get hosts and templates count
	$data['groupCounts'] = API::HostGroup()->get([
		'output' => ['groupid'],
		'groupids' => $groupIds,
		'selectHosts' => API_OUTPUT_COUNT,
		'selectTemplates' => API_OUTPUT_COUNT,
		'preservekeys' => true
	]);

	// get host groups
	$data['groups'] = API::HostGroup()->get([
		'output' => ['groupid', 'name', 'flags'],
		'groupids' => $groupIds,
		'selectHosts' => ['hostid', 'name', 'status'],
		'selectTemplates' => ['templateid', 'name'],
		'selectGroupDiscovery' => ['ts_delete'],
		'selectDiscoveryRule' => ['itemid', 'name'],
		'limitSelects' => $config['max_in_table'] + 1
	]);
	order_result($data['groups'], $sortField, $sortOrder);

	foreach ($data['groups'] as &$group) {
		order_result($group['hosts'], 'name');
		order_result($group['templates'], 'name');
	}
	unset($group);

	// render view
	$hostgroupView = new CView('configuration.hostgroups.list', $data);
	$hostgroupView->render();
	$hostgroupView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

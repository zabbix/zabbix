<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of host prototypes');
$page['file'] = 'host_prototypes.php';
$page['scripts'] = ['effects.js', 'class.cviewswitcher.js', 'multiselect.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>					[T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update"))'],
	'parent_discoveryid' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null],
	'host' =>		        	[T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({add}) || isset({update})', _('Host name')],
	'name' =>	            	[T_ZBX_STR, O_OPT, null,		null,		'isset({add}) || isset({update})'],
	'status' =>		        	[T_ZBX_INT, O_OPT, null,		IN([HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED]), null],
	'inventory_mode' =>			[T_ZBX_INT, O_OPT, null, IN([HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]), null],
	'templates' =>		    	[T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'add_template' =>			[T_ZBX_STR, O_OPT, null,		null,	null],
	'add_templates' =>		    [T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'group_links' =>			[T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'group_prototypes' =>		[T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'unlink' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null],
	'group_hostid' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'show_inherited_macros' =>	[T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"hostprototype.massdelete","hostprototype.massdisable",'.
										'"hostprototype.massenable"'
									),
									null
								],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"name","status"'),						null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

// permissions
if (getRequest('parent_discoveryid')) {
	$discoveryRule = API::DiscoveryRule()->get([
		'itemids' => $_REQUEST['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['flags'],
		'editable' => true
	]);
	$discoveryRule = reset($discoveryRule);
	if (!$discoveryRule || $discoveryRule['hosts'][0]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		access_deny();
	}

	if (getRequest('hostid')) {
		$hostPrototype = API::HostPrototype()->get([
			'hostids' => getRequest('hostid'),
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTemplates' => ['templateid', 'name'],
			'selectParentHost' => ['hostid'],
			'selectInventory' => API_OUTPUT_EXTEND,
			'editable' => true
		]);
		$hostPrototype = reset($hostPrototype);
		if (!$hostPrototype) {
			access_deny();
		}
	}
}
else {
	access_deny();
}

/*
 * Actions
 */
// add templates to the list
if (getRequest('add_template')) {
	foreach (getRequest('add_templates', []) as $templateId) {
		$_REQUEST['templates'][$templateId] = $templateId;
	}
}
// unlink templates
elseif (getRequest('unlink')) {
	foreach (getRequest('unlink') as $templateId => $value) {
		unset($_REQUEST['templates'][$templateId]);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])) {
	DBstart();
	$result = API::HostPrototype()->delete([getRequest('hostid')]);
	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}
	show_messages($result, _('Host prototype deleted'), _('Cannot delete host prototypes'));

	unset($_REQUEST['hostid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])) {
	unset($_REQUEST['hostid']);
	foreach ($_REQUEST['group_prototypes'] as &$groupPrototype) {
		unset($groupPrototype['group_prototypeid']);
	}
	unset($groupPrototype);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	DBstart();

	$newHostPrototype = [
		'host' => getRequest('host'),
		'name' => getRequest('name'),
		'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
		'groupLinks' => [],
		'groupPrototypes' => [],
		'templates' => getRequest('templates', []),
		'inventory' => [
			'inventory_mode' => getRequest('inventory_mode')
		]
	];

	// add custom group prototypes
	foreach (getRequest('group_prototypes', []) as $groupPrototype) {
		if (!$groupPrototype['group_prototypeid']) {
			unset($groupPrototype['group_prototypeid']);
		}

		if (!zbx_empty($groupPrototype['name'])) {
			$newHostPrototype['groupPrototypes'][] = $groupPrototype;
		}
	}

	if (getRequest('hostid')) {
		$newHostPrototype['hostid'] = getRequest('hostid');

		if (!$hostPrototype['templateid']) {
			// add group prototypes based on existing host groups
			$groupPrototypesByGroupId = zbx_toHash($hostPrototype['groupLinks'], 'groupid');
			unset($groupPrototypesByGroupId[0]);
			foreach (getRequest('group_links', []) as $groupId) {
				if (isset($groupPrototypesByGroupId[$groupId])) {
					$newHostPrototype['groupLinks'][] = [
						'groupid' => $groupPrototypesByGroupId[$groupId]['groupid'],
						'group_prototypeid' => $groupPrototypesByGroupId[$groupId]['group_prototypeid']
					];
				}
				else {
					$newHostPrototype['groupLinks'][] = [
						'groupid' => $groupId
					];
				}
			}
		}
		else {
			unset($newHostPrototype['groupPrototypes'], $newHostPrototype['groupLinks']);
		}

		$newHostPrototype = CArrayHelper::unsetEqualValues($newHostPrototype, $hostPrototype, ['hostid']);
		$result = API::HostPrototype()->update($newHostPrototype);

		show_messages($result, _('Host prototype updated'), _('Cannot update host prototype'));
	}
	else {
		$newHostPrototype['ruleid'] = getRequest('parent_discoveryid');

		// add group prototypes based on existing host groups
		foreach (getRequest('group_links', []) as $groupId) {
			$newHostPrototype['groupLinks'][] = [
				'groupid' => $groupId
			];
		}

		$result = API::HostPrototype()->create($newHostPrototype);

		show_messages($result, _('Host prototype added'), _('Cannot add host prototype'));
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
		unset($_REQUEST['itemid'], $_REQUEST['form']);
	}
}
// GO
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['hostprototype.massenable', 'hostprototype.massdisable']) && hasRequest('group_hostid')) {
	$enable = (getRequest('action') == 'hostprototype.massenable');
	$status = $enable ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$update = [];

	DBstart();
	foreach ((array) getRequest('group_hostid') as $hostPrototypeId) {
		$update[] = [
			'hostid' => $hostPrototypeId,
			'status' => $status
		];
	}

	$result = API::HostPrototype()->update($update);
	$result = DBend($result);

	$updated = count($update);

	$messageSuccess = $enable
		? _n('Host prototype enabled', 'Host prototypes enabled', $updated)
		: _n('Host prototype disabled', 'Host prototypes disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable host prototype', 'Cannot enable host prototypes', $updated)
		: _n('Cannot disable host prototype', 'Cannot disable host prototypes', $updated);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'hostprototype.massdelete' && getRequest('group_hostid')) {
	DBstart();
	$result = API::HostPrototype()->delete(getRequest('group_hostid'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}
	show_messages($result, _('Host prototypes deleted'), _('Cannot delete host prototypes'));
}

$config = select_config();

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'discovery_rule' => $discoveryRule,
		'host_prototype' => [
			'hostid' => getRequest('hostid'),
			'templateid' => getRequest('templateid'),
			'host' => getRequest('host'),
			'name' => getRequest('name'),
			'status' => getRequest('status', HOST_STATUS_MONITORED),
			'templates' => [],
			'inventory' => [
				'inventory_mode' => getRequest('inventory_mode', $config['default_inventory_mode'])
			],
			'groupPrototypes' => getRequest('group_prototypes', [])
		],
		'groups' => [],
		'show_inherited_macros' => getRequest('show_inherited_macros', 0)
	];

	// add already linked and new templates
	$data['host_prototype']['templates'] = API::Template()->get([
		'output' => ['templateid', 'name'],
		'templateids' => getRequest('templates', [])
	]);

	// add parent host
	$parentHost = API::Host()->get([
		'output' => API_OUTPUT_EXTEND,
		'selectGroups' => ['groupid', 'name'],
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectMacros' => ['macro', 'value'],
		'hostids' => $discoveryRule['hostid'],
		'templated_hosts' => true
	]);
	$parentHost = reset($parentHost);
	$data['parent_host'] = $parentHost;

	if (getRequest('group_links')) {
		$data['groups'] = API::HostGroup()->get([
			'output' => API_OUTPUT_EXTEND,
			'groupids' => getRequest('group_links'),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	if ($parentHost['proxy_hostid']) {
		$proxy = API::Proxy()->get([
			'output' => ['host', 'proxyid'],
			'proxyids' => $parentHost['proxy_hostid'],
			'limit' => 1
		]);
		$data['proxy'] = reset($proxy);
	}

	// host prototype edit form
	if (getRequest('hostid') && !getRequest('form_refresh')) {
		$data['host_prototype'] = array_merge($data['host_prototype'], $hostPrototype);

		if (!array_key_exists('inventory_mode', $data['host_prototype']['inventory'])) {
			$data['host_prototype']['inventory']['inventory_mode'] = HOST_INVENTORY_DISABLED;
		}

		$data['groups'] = API::HostGroup()->get([
			'output' => API_OUTPUT_EXTEND,
			'groupids' => zbx_objectValues($data['host_prototype']['groupLinks'], 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		// add parent templates
		if ($hostPrototype['templateid']) {
			$data['parents'] = [];
			$hostPrototypeId = $hostPrototype['templateid'];
			while ($hostPrototypeId) {
				$parentHostPrototype = API::HostPrototype()->get([
					'output' => ['itemid', 'templateid'],
					'selectParentHost' => ['hostid', 'name'],
					'selectDiscoveryRule' => ['itemid'],
					'hostids' => $hostPrototypeId
				]);
				$parentHostPrototype = reset($parentHostPrototype);
				$hostPrototypeId = null;

				if ($parentHostPrototype) {
					$data['parents'][] = $parentHostPrototype;
					$hostPrototypeId = $parentHostPrototype['templateid'];
				}
			}
		}
	}

	// order linked templates
	CArrayHelper::sort($data['host_prototype']['templates'], ['name']);

	// render view
	$itemView = new CView('configuration.host.prototype.edit', $data);
	$itemView->render();
	$itemView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = [
		'form' => getRequest('form'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'discovery_rule' => $discoveryRule,
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// get items
	$data['hostPrototypes'] = API::HostPrototype()->get([
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'selectTemplates' => ['templateid', 'name'],
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	order_result($data['hostPrototypes'], $sortField, $sortOrder);

	$url = (new CUrl('host_prototypes.php'))
		->setArgument('parent_discoveryid', $data['parent_discoveryid']);

	$data['paging'] = getPagingLine($data['hostPrototypes'], $sortOrder, $url);
	// fetch templates linked to the prototypes
	$templateIds = [];
	foreach ($data['hostPrototypes'] as $hostPrototype) {
		$templateIds = array_merge($templateIds, zbx_objectValues($hostPrototype['templates'], 'templateid'));
	}
	$templateIds = array_unique($templateIds);

	$linkedTemplates = API::Template()->get([
		'output' => ['templateid', 'name'],
		'templateids' => $templateIds,
		'selectParentTemplates' => ['hostid', 'name']
	]);
	$data['linkedTemplates'] = zbx_toHash($linkedTemplates, 'templateid');

	// fetch source templates and LLD rules
	$hostPrototypeSourceIds = getHostPrototypeSourceParentIds(zbx_objectValues($data['hostPrototypes'], 'hostid'));
	if ($hostPrototypeSourceIds) {
		$hostPrototypeSourceTemplates = DBfetchArrayAssoc(DBSelect(
			'SELECT h.hostid,h2.name,h2.hostid AS parent_hostid'.
			' FROM hosts h,host_discovery hd,items i,hosts h2'.
			' WHERE h.hostid=hd.hostid'.
				' AND hd.parent_itemid=i.itemid'.
				' AND i.hostid=h2.hostid'.
				' AND '.dbConditionInt('h.hostid', $hostPrototypeSourceIds)
		), 'hostid');
		foreach ($data['hostPrototypes'] as &$hostPrototype) {
			if ($hostPrototype['templateid']) {
				$sourceTemplate = $hostPrototypeSourceTemplates[$hostPrototypeSourceIds[$hostPrototype['hostid']]];
				$hostPrototype['sourceTemplate'] = [
					'hostid' => $sourceTemplate['parent_hostid'],
					'name' => $sourceTemplate['name']
				];
				$sourceDiscoveryRuleId = get_realrule_by_itemid_and_hostid($discoveryRule['itemid'], $sourceTemplate['hostid']);
				$hostPrototype['sourceDiscoveryRuleId'] = $sourceDiscoveryRuleId;
			}
		}
		unset($hostPrototype);
	}

	// render view
	$itemView = new CView('configuration.host.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

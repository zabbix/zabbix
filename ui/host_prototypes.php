<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
$page['scripts'] = ['effects.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>					[T_ZBX_INT, O_NO,	P_SYS,	DB_ID, '(isset({form}) && ({form} == "update")) || (isset({action}) && {action} == "hostprototype.updatediscover")'],
	'parent_discoveryid' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null],
	'host' =>					[T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({add}) || isset({update})', _('Host name')],
	'name' =>					[T_ZBX_STR, O_OPT, null,		null,		'isset({add}) || isset({update})'],
	'status' =>					[T_ZBX_INT, O_OPT, null,		IN([HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED]), null],
	'discover' =>				[T_ZBX_INT, O_OPT, null, IN([ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), null],
	'inventory_mode' =>			[T_ZBX_INT, O_OPT, null, IN([HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]), null],
	'templates' =>				[T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'add_templates' =>			[T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'group_links' =>			[T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'group_prototypes' =>		[T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'unlink' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null],
	'group_hostid' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'show_inherited_macros' =>	[T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	'tags' =>					[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'macros' =>					[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'custom_interfaces' =>		[T_ZBX_INT, O_OPT, null, IN([HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]), null],
	'interfaces' =>				[T_ZBX_STR, O_OPT, null, null,		null],
	'mainInterfaces' =>			[T_ZBX_INT, O_OPT, null, DB_ID,		null],
	'context' =>				[T_ZBX_STR, O_MAND, P_SYS,	IN('"host", "template"'),	null],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"hostprototype.massdelete","hostprototype.massdisable",'.
										'"hostprototype.massenable","hostprototype.updatediscover"'
									),
									null
								],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"name","status","discover"'),						null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$hostid = getRequest('hostid', 0);

// permissions
if (getRequest('parent_discoveryid')) {
	$discoveryRule = API::DiscoveryRule()->get([
		'itemids' => getRequest('parent_discoveryid'),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['flags'],
		'editable' => true
	]);
	$discoveryRule = reset($discoveryRule);
	if (!$discoveryRule || $discoveryRule['hosts'][0]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		access_deny();
	}

	if ($hostid != 0) {
		$hostPrototype = API::HostPrototype()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectTemplates' => ['templateid', 'name'],
			'selectParentHost' => ['hostid'],
			'selectMacros' => ['hostmacroid', 'macro', 'value', 'type', 'description'],
			'selectTags' => ['tag', 'value'],
			'selectInterfaces' => ['type', 'main', 'useip', 'ip', 'dns', 'port', 'details'],
			'hostids' => $hostid,
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

$tags = getRequest('tags', []);
foreach ($tags as $key => $tag) {
	// remove empty new tag lines
	if ($tag['tag'] === '' && $tag['value'] === '') {
		unset($tags[$key]);
	}
}

// Remove inherited macros data (actions: 'add', 'update' and 'form').
$macros = cleanInheritedMacros(getRequest('macros', []));

// Remove empty new macro lines.
$macros = array_filter($macros, function($macro) {
	$keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

	return (bool) array_filter(array_intersect_key($macro, $keys));
});

/*
 * Actions
 */
if (getRequest('unlink')) {
	foreach (getRequest('unlink') as $templateId => $value) {
		unset($_REQUEST['templates'][$templateId]);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])) {
	DBstart();
	$result = API::HostPrototype()->delete([$hostid]);
	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}
	show_messages($result, _('Host prototype deleted'), _('Cannot delete host prototypes'));

	unset($_REQUEST['hostid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])) {
	unset($_REQUEST['hostid']);

	if ($macros && in_array(ZBX_MACRO_TYPE_SECRET, array_column($macros, 'type'))) {
		// Reset macro type and value.
		$macros = array_map(function($value) {
			return ($value['type'] == ZBX_MACRO_TYPE_SECRET)
				? ['value' => '', 'type' => ZBX_MACRO_TYPE_TEXT] + $value
				: $value;
		}, $macros);

		warning(_('The cloned host prototype contains user defined macros with type "Secret text". The value and type of these macros were reset.'));
	}

	$macros = array_map(function(array $macro): array {
		return array_diff_key($macro, array_flip(['hostmacroid']));
	}, $macros);

	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	DBstart();

	if ($hostid == 0 || $hostPrototype['templateid'] == 0) {
		$newHostPrototype = [
			'host' => getRequest('host', ''),
			'name' => (getRequest('name', '') === '') ? getRequest('host', '') : getRequest('name', ''),
			'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
			'discover' => getRequest('discover', DB::getDefault('hosts', 'discover')),
			'groupLinks' => [],
			'groupPrototypes' => [],
			'tags' => $tags,
			'macros' => $macros,
			'templates' => array_merge(getRequest('templates', []), getRequest('add_templates', [])),
			'custom_interfaces' => getRequest('custom_interfaces', DB::getDefault('hosts', 'custom_interfaces'))
		];

		if (hasRequest('inventory_mode')) {
			$newHostPrototype['inventory_mode'] = getRequest('inventory_mode');
		}

		// API requires 'templateid' property.
		if ($newHostPrototype['templates']) {
			$newHostPrototype['templates'] = zbx_toObject($newHostPrototype['templates'], 'templateid');
		}

		// add custom group prototypes
		foreach (getRequest('group_prototypes', []) as $groupPrototype) {
			if ($groupPrototype['name'] !== '') {
				$newHostPrototype['groupPrototypes'][] = $groupPrototype;
			}
		}

		if ($newHostPrototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
			$interfaces = getRequest('interfaces', []);

			foreach ($interfaces as &$interface) {
				// Process SNMP interface fields.
				if ($interface['type'] == INTERFACE_TYPE_SNMP) {
					$interface['details']['bulk'] = array_key_exists('bulk', $interface['details'])
						? SNMP_BULK_ENABLED
						: SNMP_BULK_DISABLED;

					switch ($interface['details']['version']) {
						case SNMP_V1:
						case SNMP_V2C:
							$interface['details'] = array_intersect_key($interface['details'],
								array_flip(['version', 'bulk', 'community'])
							);
							break;

						case SNMP_V3:
							$field_names = array_flip(['version', 'bulk', 'contextname', 'securityname',
								'securitylevel'
							]);

							if ($interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
								$field_names += array_flip(['authprotocol', 'authpassphrase']);
							}
							elseif ($interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
								$field_names +=
									array_flip(['authprotocol', 'authpassphrase', 'privprotocol', 'privpassphrase']);
							}

							$interface['details'] = array_intersect_key($interface['details'], $field_names);
							break;
					}
				}

				unset($interface['isNew'], $interface['items'], $interface['interfaceid']);
				$interface['main'] = 0;
			}
			unset($interface);

			$main_interfaces = getRequest('mainInterfaces', []);

			foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $type) {
				if (array_key_exists($type, $main_interfaces)
						&& array_key_exists($main_interfaces[$type], $interfaces)) {
					$interfaces[$main_interfaces[$type]]['main'] = INTERFACE_PRIMARY;
				}
			}

			$newHostPrototype['interfaces'] = $interfaces;
		}
	}
	else {
		$newHostPrototype = [
			'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
			'discover' => getRequest('discover', DB::getDefault('hosts', 'discover'))
		];
	}

	if ($hostid != 0) {
		$newHostPrototype['hostid'] = $hostid;

		if (!$hostPrototype['templateid']) {
			// add group prototypes based on existing host groups
			$groupPrototypesByGroupId = zbx_toHash($hostPrototype['groupLinks'], 'groupid');
			unset($groupPrototypesByGroupId[0]);
			foreach (getRequest('group_links', []) as $groupId) {
				$newHostPrototype['groupLinks'][] = [
					'groupid' => array_key_exists($groupId, $groupPrototypesByGroupId)
						? $groupPrototypesByGroupId[$groupId]['groupid']
						: $groupId
				];

			}
		}
		else {
			unset($newHostPrototype['groupPrototypes'], $newHostPrototype['groupLinks']);
		}

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

		if (count($newHostPrototype['groupPrototypes']) === 0) {
			unset($newHostPrototype['groupPrototypes']);
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
elseif ($hostid != 0 && getRequest('action', '') === 'hostprototype.updatediscover') {
	$result = API::HostPrototype()->update([
		'hostid' => $hostid,
		'discover' => getRequest('discover', DB::getDefault('hosts', 'discover'))
	]);

	show_messages($result, _('Host prototype updated'), _('Cannot update host prototype'));
}
// GO
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['hostprototype.massenable', 'hostprototype.massdisable']) && hasRequest('group_hostid')) {
	$status = (getRequest('action') == 'hostprototype.massenable') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
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

	$messageSuccess = _n('Host prototype updated', 'Host prototypes updated', $updated);
	$messageFailed = _n('Cannot update host prototype', 'Cannot update host prototypes', $updated);

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

if (hasRequest('action') && hasRequest('group_hostid') && !$result) {
	$host_prototypes = API::HostPrototype()->get([
		'output' => [],
		'hostids' => getRequest('group_hostid'),
		'editable' => true
	]);
	uncheckTableRows($discoveryRule['itemid'], zbx_objectValues($host_prototypes, 'hostid'));
}

/*
 * Display
 */
if (hasRequest('form')) {
	// During clone "hostid" could've been reset.
	$hostid = getRequest('hostid', 0);

	$data = [
		'discovery_rule' => $discoveryRule,
		'host_prototype' => [
			'hostid' => $hostid,
			'templateid' => ($hostid == 0) ? 0 : $hostPrototype['templateid'],
			'host' => getRequest('host'),
			'name' => getRequest('name'),
			'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
			'discover' => getRequest('discover', DB::getDefault('hosts', 'discover')),
			'templates' => [],
			'add_templates' => [],
			'inventory_mode' => getRequest('inventory_mode',
				CSettingsHelper::get(CSettingsHelper::DEFAULT_INVENTORY_MODE)
			),
			'groupPrototypes' => getRequest('group_prototypes', []),
			'macros' => $macros,
			'custom_interfaces' => getRequest('custom_interfaces', DB::getDefault('hosts', 'custom_interfaces')),
			'interfaces' => getRequest('interfaces', []),
			'main_interfaces' => getRequest('mainInterfaces', [])
		],
		'show_inherited_macros' => getRequest('show_inherited_macros', 0),
		'readonly' => ($hostid != 0 && $hostPrototype['templateid']),
		'groups' => [],
		'tags' => $tags,
		'context' => getRequest('context'),
		// Parent discovery rules.
		'templates' => []
	];

	// Add already linked and new templates.
	$templates = [];
	$request_templates = getRequest('templates', []);
	$request_add_templates = getRequest('add_templates', []);

	if ($request_templates || $request_add_templates) {
		$templates = API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => array_merge($request_templates, $request_add_templates),
			'preservekeys' => true
		]);

		$data['host_prototype']['templates'] = array_intersect_key($templates, array_flip($request_templates));
		CArrayHelper::sort($data['host_prototype']['templates'], ['name']);

		$data['host_prototype']['add_templates'] = array_intersect_key($templates, array_flip($request_add_templates));

		foreach ($data['host_prototype']['add_templates'] as &$template) {
			$template = CArrayHelper::renameKeys($template, ['templateid' => 'id']);
		}
		unset($template);
	}

	// add parent host
	$parentHost = API::Host()->get([
		'output' => API_OUTPUT_EXTEND,
		'selectGroups' => ['groupid', 'name'],
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'hostids' => $discoveryRule['hostid'],
		'templated_hosts' => true
	]);
	$parentHost = reset($parentHost);
	$data['parent_host'] = $parentHost;
	$data['parent_hostid'] = $parentHost['hostid'];

	if (getRequest('group_links')) {
		$data['groups'] = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
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

	if (!hasRequest('form_refresh')) {
		if ($data['host_prototype']['hostid'] != 0) {
			// When opening existing host prototype, display all values from database.
			$data['host_prototype'] = array_merge($data['host_prototype'], $hostPrototype);

			$groupids = zbx_objectValues($data['host_prototype']['groupLinks'], 'groupid');
			$data['groups'] = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids,
				'preservekeys' => true
			]);

			$n = 0;
			foreach ($groupids as $groupid) {
				if (!array_key_exists($groupid, $data['groups'])) {
					$postfix = (++$n > 1) ? ' ('.$n.')' : '';
					$data['groups'][$groupid] = [
						'groupid' => $groupid,
						'name' => _('Inaccessible group').$postfix,
						'inaccessible' => true
					];
				}
			}

			$data['tags'] = $data['host_prototype']['tags'];
		}
		else {
			// Set default values for new host prototype.
			$data['host_prototype']['status'] = HOST_STATUS_MONITORED;
		}
	}
	else {
		foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $type) {
			if (array_key_exists($type, $data['host_prototype']['main_interfaces'])) {
				$interfaceid = $data['host_prototype']['main_interfaces'][$type];
				$data['host_prototype']['interfaces'][$interfaceid]['main'] = INTERFACE_PRIMARY;
			}
		}
		$data['host_prototype']['interfaces'] = array_values($data['host_prototype']['interfaces']);
	}

	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	$data['templates'] = makeHostPrototypeTemplatesHtml($data['host_prototype']['hostid'],
		getHostPrototypeParentTemplates([$data['host_prototype']]), $data['allowed_ui_conf_templates']
	);

	// Select writable templates
	$templateids = zbx_objectValues($data['host_prototype']['templates'], 'templateid');
	$data['host_prototype']['writable_templates'] = [];

	if ($templateids) {
		$data['host_prototype']['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $templateids,
			'editable' => true,
			'preservekeys' => true
		]);
	}

	// tags
	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}
	else {
		CArrayHelper::sort($data['tags'], ['tag', 'value']);
	}

	$macros = $data['host_prototype']['macros'];

	if ($data['show_inherited_macros']) {
		$macros = mergeInheritedMacros($macros, getInheritedMacros(array_keys($templates), $data['parent_hostid']));
	}

	// Sort only after inherited macros are added. Otherwise the list will look chaotic.
	$data['macros'] = array_values(order_macros($macros, 'macro'));

	if (!$data['macros'] && !$data['readonly']) {
		$macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => ZBX_MACRO_TYPE_TEXT];

		if ($data['show_inherited_macros']) {
			$macro['inherited_type'] = ZBX_PROPERTY_OWN;
		}

		$data['macros'][] = $macro;
	}

	// This data is used in common.template.edit.js.php.
	$data['macros_tab'] = [
		'linked_templates' => array_map('strval', $templateids),
		'add_templates' => array_map('strval', array_keys($data['host_prototype']['add_templates']))
	];

	$data['groups_ms'] = [];

	foreach ($data['groups'] as $group) {
		$data['groups_ms'][] = [
			'id' => $group['groupid'],
			'name' => $group['name'],
			'inaccessible' => (array_key_exists('inaccessible', $group) && $group['inaccessible'])
		];
	}

	// Render view.
	echo (new CView('configuration.host.prototype.edit', $data))->getOutput();
}
else {
	$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';

	$sortField = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update($prefix.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update($prefix.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = [
		'form' => getRequest('form'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'discovery_rule' => $discoveryRule,
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'context' => getRequest('context')
	];

	// get items
	$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
	$data['hostPrototypes'] = API::HostPrototype()->get([
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'selectTemplates' => ['templateid', 'name'],
		'selectTags' => ['tag', 'value'],
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $limit
	]);

	order_result($data['hostPrototypes'], $sortField, $sortOrder);

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $data['hostPrototypes'], $sortOrder,
		(new CUrl('host_prototypes.php'))
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('context', $data['context'])
	);

	$data['parent_templates'] = getHostPrototypeParentTemplates($data['hostPrototypes']);

	// Fetch templates linked to the prototypes.
	$templateids = [];
	foreach ($data['hostPrototypes'] as $hostPrototype) {
		$templateids = array_merge($templateids, zbx_objectValues($hostPrototype['templates'], 'templateid'));
	}
	$templateids = array_keys(array_flip($templateids));

	$linkedTemplates = API::Template()->get([
		'output' => ['templateid', 'name'],
		'selectParentTemplates' => ['templateid', 'name'],
		'templateids' => $templateids
	]);
	$data['linkedTemplates'] = zbx_toHash($linkedTemplates, 'templateid');

	foreach ($data['linkedTemplates'] as $linked_template) {
		foreach ($linked_template['parentTemplates'] as $parent_template) {
			$templateids[] = $parent_template['templateid'];
		}
	}

	// Select writable template IDs.
	$data['writable_templates'] = [];

	if ($templateids) {
		$data['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $templateids,
			'editable' => true,
			'preservekeys' => true
		]);
	}

	$data['tags'] = makeTags($data['hostPrototypes'], true, 'hostid');
	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

	// render view
	echo (new CView('configuration.host.prototype.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';

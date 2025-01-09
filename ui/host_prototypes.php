<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of host prototypes');
$page['file'] = 'host_prototypes.php';
$page['scripts'] = ['effects.js', 'items.js', 'multilineinput.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>					[T_ZBX_INT, O_NO,	P_SYS,	DB_ID, '(isset({form}) && ({form} == "update")) || (isset({action}) && {action} == "hostprototype.updatediscover")'],
	'parent_discoveryid' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null],
	'host' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Host name')],
	'name' =>					[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'status' =>					[T_ZBX_INT, O_OPT, null,	IN([HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED]), null],
	'discover' =>				[T_ZBX_INT, O_OPT, null,	IN([ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), null],
	'inventory_mode' =>			[T_ZBX_INT, O_OPT, null,	IN([HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]), null],
	'templates' =>				[T_ZBX_STR, O_OPT, P_ONLY_ARRAY, NOT_EMPTY,		null],
	'add_templates' =>			[T_ZBX_STR, O_OPT, P_ONLY_ARRAY, NOT_EMPTY,		null],
	'group_links' =>			[T_ZBX_STR, O_OPT, P_ONLY_ARRAY, NOT_EMPTY,		null],
	'group_prototypes' =>		[T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY, NOT_EMPTY,	null],
	'unlink' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT|P_ONLY_ARRAY,	null,	null],
	'group_hostid' =>			[T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,		null],
	'show_inherited_macros' =>	[T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	'tags' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ONLY_TD_ARRAY,	null,	null],
	'macros' =>					[null,      O_OPT, P_SYS|P_ONLY_TD_ARRAY,	null,	null],
	'custom_interfaces' =>		[T_ZBX_INT, O_OPT, null, IN([HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]), null],
	'interfaces' =>				[null,      O_OPT, P_ONLY_TD_ARRAY,	null,	null],
	'mainInterfaces' =>			[T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,	null],
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
	'form_refresh' =>			[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'backurl' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS,	IN('"name","status","discover"'),				null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS,	IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
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
			'selectGroupPrototypes' => ['group_prototypeid', 'name'],
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

// Validate backurl.
if (hasRequest('backurl') && !CHtmlUrlValidator::validateSameSite(getRequest('backurl'))) {
	access_deny();
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

	if (hasRequest('group_prototypes')) {
		foreach ($_REQUEST['group_prototypes'] as &$group_prototype) {
			unset($group_prototype['group_prototypeid']);
		}
		unset($group_prototype);
	}

	$warnings = [];

	// Reset macro type and value.
	$secret_macro_reset = false;

	foreach ($macros as &$macro) {
		if (array_key_exists('allow_revert', $macro) && array_key_exists('value', $macro)) {
			$macro['deny_revert'] = true;

			unset($macro['allow_revert']);
		}
	}
	unset($macro);

	foreach ($macros as &$macro) {
		if ($macro['type'] == ZBX_MACRO_TYPE_SECRET && !array_key_exists('value', $macro)) {
			$macro = [
				'type' => ZBX_MACRO_TYPE_TEXT,
				'value' => ''
			] + $macro;

			$secret_macro_reset = true;

			unset($macro['allow_revert']);
		}
	}
	unset($macro);

	if ($secret_macro_reset) {
		$warnings[] = _('The cloned host prototype contains user defined macros with type "Secret text". The value and type of these macros were reset.');
	}

	$macros = array_map(function(array $macro): array {
		return array_diff_key($macro, array_flip(['hostmacroid']));
	}, $macros);

	if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN && getRequest('group_links')) {
		$editable_groups_count = API::HostGroup()->get([
			'countOutput' => true,
			'groupids' => getRequest('group_links'),
			'editable' => true
		]);

		if ($editable_groups_count != count(getRequest('group_links'))) {
			$warnings[] = _("The host being cloned belongs to a host group you don't have write permissions to. Non-writable group has been removed from the new host.");
		}
	}

	if ($warnings) {
		if (count($warnings) > 1) {
			CMessageHelper::setWarningTitle(_('Cloned host parameter values have been modified.'));
		}

		array_map('CMessageHelper::addWarning', $warnings);
	}

	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	try {
		$input = [
			'host' => getRequest('host', DB::getDefault('hosts', 'host')),
			'name' => getRequest(getRequest('name', '') === '' ? 'host' : 'name', DB::getDefault('hosts', 'name')),
			'custom_interfaces' => getRequest('custom_interfaces', DB::getDefault('hosts', 'custom_interfaces')),
			'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
			'discover' => getRequest('discover', DB::getDefault('hosts', 'discover')),
			'interfaces' => prepareHostPrototypeInterfaces(
				getRequest('interfaces', []), getRequest('mainInterfaces', [])
			),
			'groupLinks' => prepareHostPrototypeGroupLinks(getRequest('group_links', [])),
			'groupPrototypes' => prepareHostPrototypeGroupPrototypes(getRequest('group_prototypes', [])),
			'templates' => zbx_toObject(
				array_merge(getRequest('templates', []), getRequest('add_templates', [])),
				'templateid'
			),
			'tags' => prepareHostPrototypeTags(getRequest('tags', [])),
			'macros' => prepareHostPrototypeMacros($macros),
			'inventory_mode' => getRequest('inventory_mode', HOST_INVENTORY_DISABLED)
		];

		$result = true;

		if (hasRequest('add')) {
			$host = ['ruleid' => getRequest('parent_discoveryid')] + getSanitizedHostPrototypeFields(
				['templateid' => 0] + $input
			);

			$response = API::HostPrototype()->create($host);

			if ($response === false) {
				throw new Exception();
			}
		}

		if (hasRequest('update')) {
			$host = ['hostid' => $hostid] + getSanitizedHostPrototypeFields(
				['templateid' => $hostPrototype['templateid']] + $input
			);

			$response = API::HostPrototype()->update($host);

			if ($response === false) {
				throw new Exception();
			}
		}
	} catch (Exception $e) {
		$result = false;
	}

	if (hasRequest('add')) {
		show_messages($result, _('Host prototype added'), _('Cannot add host prototype'));
	}
	else {
		show_messages($result, _('Host prototype updated'), _('Cannot update host prototype'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		uncheckTableRows($discoveryRule['itemid']);
	}
}
elseif ($hostid != 0 && getRequest('action', '') === 'hostprototype.updatediscover') {
	$result = API::HostPrototype()->update([
		'hostid' => $hostid,
		'discover' => getRequest('discover', DB::getDefault('hosts', 'discover'))
	]);

	if ($result) {
		CMessageHelper::setSuccessTitle(_('Host prototype updated'));
	}
	else {
		CMessageHelper::setErrorTitle(_('Cannot update host prototype'));
	}

	if (hasRequest('backurl')) {
		$response = new CControllerResponseRedirect(getRequest('backurl'));
		$response->redirect();
	}
}
// GO
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['hostprototype.massenable', 'hostprototype.massdisable']) && hasRequest('group_hostid')) {
	$status = (getRequest('action') == 'hostprototype.massenable') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$update = [];

	foreach ((array) getRequest('group_hostid') as $hostPrototypeId) {
		$update[] = [
			'hostid' => $hostPrototypeId,
			'status' => $status
		];
	}

	$result = (bool) API::HostPrototype()->update($update);

	$updated = count($update);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);

		CMessageHelper::setSuccessTitle(_n('Host prototype updated', 'Host prototypes updated', $updated));
	}
	else {
		CMessageHelper::setErrorTitle(_n('Cannot update host prototype', 'Cannot update host prototypes', $updated));
	}

	if (hasRequest('backurl')) {
		$response = new CControllerResponseRedirect(getRequest('backurl'));
		$response->redirect();
	}
}
elseif (hasRequest('action') && getRequest('action') == 'hostprototype.massdelete' && getRequest('group_hostid')) {
	DBstart();
	$result = API::HostPrototype()->delete(getRequest('group_hostid'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}

	$host_prototypes_count = count(getRequest('group_hostid'));
	$messageSuccess = _n('Host prototype deleted', 'Host prototypes deleted', $host_prototypes_count);
	$messageFailed = _n('Cannot delete host prototype', 'Cannot delete host prototypes', $host_prototypes_count);

	show_messages($result, $messageSuccess, $messageFailed);
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
		'form_refresh' => getRequest('form_refresh', 0),
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
		'tags' => getRequest('tags', []),
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
		'output' => ['hostid', 'monitored_by', 'proxyid', 'proxy_groupid', 'status', 'ipmi_authtype', 'ipmi_privilege',
			'ipmi_username', 'ipmi_password', 'tls_accept', 'tls_connect', 'tls_issuer', 'tls_subject'
		],
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

	$data['ms_proxy'] = [];
	$data['ms_proxy_group'] = [];

	if ($parentHost['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
		$data['ms_proxy'] = CArrayHelper::renameObjectsKeys(API::Proxy()->get([
			'output' => ['proxyid', 'name'],
			'proxyids' => $parentHost['proxyid']
		]), ['proxyid' => 'id']);
	}
	elseif ($parentHost['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
		$data['ms_proxy_group'] = CArrayHelper::renameObjectsKeys(API::ProxyGroup()->get([
			'output' => ['proxy_groupid', 'name'],
			'proxy_groupids' => $parentHost['proxy_groupid']
		]), ['proxy_groupid' => 'id']);
	}

	if (!hasRequest('form_refresh')) {
		if ($data['host_prototype']['hostid'] != 0) {
			// When opening existing host prototype, display all values from database.
			$data['host_prototype'] = array_merge($data['host_prototype'], $hostPrototype);

			foreach ($data['host_prototype']['macros'] as &$macro) {
				if ($macro['type'] == ZBX_MACRO_TYPE_SECRET
						&& !array_key_exists('deny_revert', $macro) && !array_key_exists('value', $macro)) {
					$macro['allow_revert'] = true;
				}
			}
			unset($macro);

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

	foreach ($data['macros'] as &$macro) {
		$macro['discovery_state'] = CControllerHostMacrosList::DISCOVERY_STATE_MANUAL;
	}
	unset($macro);

	$data['macros_tab'] = [
		'linked_templates' => array_map('strval', $templateids),
		'add_templates' => array_map('strval', array_keys($data['host_prototype']['add_templates']))
	];

	// Editable host groups.
	$groups_rw = ($data['groups'] && CWebUser::getType() != USER_TYPE_SUPER_ADMIN)
		? API::HostGroup()->get([
			'output' => [],
			'groupids' => array_keys($data['groups']),
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['groups_ms'] = [];

	foreach ($data['groups'] as $group) {
		$data['groups_ms'][] = [
			'id' => $group['groupid'],
			'name' => $group['name'],
			'inaccessible' => array_key_exists('inaccessible', $group) && $group['inaccessible'],
			'disabled' => CWebUser::getType() != USER_TYPE_SUPER_ADMIN
					&& !array_key_exists($group['groupid'], $groups_rw)
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

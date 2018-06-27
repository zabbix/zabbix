<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';

if (hasRequest('action') && getRequest('action') == 'template.export' && hasRequest('templates')) {
	$exportData = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_export_templates.xml';
}
else {
	$exportData = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = _('Configuration of templates');
	$page['file'] = 'templates.php';
	$page['scripts'] = ['multiselect.js'];
}

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR						TYPE		OPTIONAL FLAGS			VALIDATION	EXCEPTION
$fields = [
	'groups'			=> [T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})'],
	'clear_templates'	=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'templates'			=> [T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'add_templates'		=> [T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'add_template' 		=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'templateid'		=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	'isset({form}) && {form} == "update"'],
	'template_name'		=> [T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({add}) || isset({update})', _('Template name')],
	'visiblename'		=> [T_ZBX_STR, O_OPT, null,		null,	'isset({add}) || isset({update})'],
	'groupid'			=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'description'		=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'macros'			=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'show_inherited_macros' => [T_ZBX_INT, O_OPT, null,	IN([0,1]), null],
	// actions
	'action'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"template.export","template.massdelete","template.massdeleteclear"'),
								null
							],
	'unlink'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'unlink_and_clear'	=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'add'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'update'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'clone'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'full_clone'		=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete_and_clear'	=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'cancel'			=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form'				=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form_refresh'		=> [T_ZBX_INT, O_OPT, null,		null,	null],
	// filter
	'filter_set'		=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst'		=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name'		=> [T_ZBX_STR, O_OPT, null,		null,		null],
	// sort and sortorder
	'sort'				=> [T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),									null],
	'sortorder'			=> [T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !isWritableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('templateid')) {
	$templates = API::Template()->get([
		'output' => [],
		'templateids' => getRequest('templateid'),
		'editable' => true
	]);

	if (!$templates) {
		access_deny();
	}
}

$templateIds = getRequest('templates', []);

if ($exportData) {
	$export = new CConfigurationExport(['templates' => $templateIds]);
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();

	if (hasErrorMesssages()) {
		show_messages();
	}
	else {
		print($exportData);
	}

	exit;
}

// remove inherited macros data (actions: 'add', 'update' and 'form')
if (hasRequest('macros')) {
	$_REQUEST['macros'] = cleanInheritedMacros($_REQUEST['macros']);

	// remove empty new macro lines
	foreach ($_REQUEST['macros'] as $idx => $macro) {
		if (!array_key_exists('hostmacroid', $macro) && $macro['macro'] === '' && $macro['value'] === '') {
			unset($_REQUEST['macros'][$idx]);
		}
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['add_template']) && isset($_REQUEST['add_templates'])) {
	$_REQUEST['templates'] = array_merge($templateIds, $_REQUEST['add_templates']);
}
if (hasRequest('unlink') || hasRequest('unlink_and_clear')) {
	$unlinkTemplates = [];

	if (hasRequest('unlink') && is_array(getRequest('unlink'))) {
		$unlinkTemplates = array_keys(getRequest('unlink'));
	}
	elseif (hasRequest('unlink_and_clear') && is_array(getRequest('unlink_and_clear'))) {
		$unlinkTemplates = array_keys(getRequest('unlink_and_clear'));
		$_REQUEST['clear_templates'] = array_merge($unlinkTemplates, getRequest('clear_templates', []));
	}

	foreach ($unlinkTemplates as $id) {
		unset($_REQUEST['templates'][array_search($id, $_REQUEST['templates'])]);
	}
}
elseif ((hasRequest('clone') || hasRequest('full_clone')) && hasRequest('templateid')) {
	$_REQUEST['form'] = hasRequest('clone') ? 'clone' : 'full_clone';

	$groups = getRequest('groups', []);
	$groupids = [];

	// Remove inaccessible groups from request, but leave "new".
	foreach ($groups as $group) {
		if (!is_array($group)) {
			$groupids[] = $group;
		}
	}

	if ($groupids) {
		$groups_allowed = API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($groups as $idx => $group) {
			if (!is_array($group) && !array_key_exists($group, $groups_allowed)) {
				unset($groups[$idx]);
			}
		}

		$_REQUEST['groups'] = $groups;
	}

	if (hasRequest('clone')) {
		unset($_REQUEST['templateid']);
	}
}
elseif (hasRequest('add') || hasRequest('update')) {
	try {
		DBstart();

		$templateId = getRequest('templateid', 0);
		$cloneTemplateId = 0;

		if (getRequest('form') === 'full_clone') {
			$cloneTemplateId = $templateId;
			$templateId = 0;
		}

		if ($templateId == 0) {
			$messageSuccess = _('Template added');
			$messageFailed = _('Cannot add template');
			$auditAction = AUDIT_ACTION_ADD;
		}
		else {
			$messageSuccess = _('Template updated');
			$messageFailed = _('Cannot update template');
			$auditAction = AUDIT_ACTION_UPDATE;
		}

		// Add new group.
		$groups = getRequest('groups', []);
		$new_groups = [];

		foreach ($groups as $idx => $group) {
			if (is_array($group) && array_key_exists('new', $group)) {
				$new_groups[] = ['name' => $group['new']];
				unset($groups[$idx]);
			}
		}

		if ($new_groups) {
			$new_groupid = API::HostGroup()->create($new_groups);

			if (!$new_groupid) {
				throw new Exception();
			}

			$groups = array_merge($groups, $new_groupid['groupids']);
		}

		// linked templates
		$linkedTemplates = getRequest('templates', []);
		$templates = [];
		foreach ($linkedTemplates as $linkedTemplateId) {
			$templates[] = ['templateid' => $linkedTemplateId];
		}

		$templatesClear = getRequest('clear_templates', []);
		$templatesClear = zbx_toObject($templatesClear, 'templateid');
		$templateName = getRequest('template_name', '');

		// create / update template
		$template = [
			'host' => $templateName,
			'name' => getRequest('visiblename', ''),
			'groups' => zbx_toObject($groups, 'groupid'),
			'templates' => $templates,
			'macros' => getRequest('macros', []),
			'description' => getRequest('description', '')
		];

		if ($templateId == 0) {
			$result = API::Template()->create($template);

			if ($result) {
				$templateId = reset($result['templateids']);
			}
			else {
				throw new Exception();
			}
		}
		else {
			$template['templateid'] = $templateId;
			$template['templates_clear'] = $templatesClear;

			$result = API::Template()->update($template);

			if (!$result) {
				throw new Exception();
			}
		}

		// full clone
		if ($cloneTemplateId != 0 && getRequest('form') === 'full_clone') {
			if (!copyApplications($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			/*
			 * First copy web scenarios with web items, so that later regular items can use web item as their master
			 * item.
			 */
			if (!copyHttpTests($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			if (!copyItems($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			// copy triggers
			$dbTriggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			if ($dbTriggers) {
				$result &= copyTriggersToHosts(zbx_objectValues($dbTriggers, 'triggerid'),
						$templateId, $cloneTemplateId);

				if (!$result) {
					throw new Exception();
				}
			}

			// copy graphs
			$dbGraphs = API::Graph()->get([
				'output' => ['graphid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			foreach ($dbGraphs as $dbGraph) {
				copyGraphToHost($dbGraph['graphid'], $templateId);
			}

			// copy discovery rules
			$dbDiscoveryRules = API::DiscoveryRule()->get([
				'output' => ['itemid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			if ($dbDiscoveryRules) {
				$result &= API::DiscoveryRule()->copy([
					'discoveryids' => zbx_objectValues($dbDiscoveryRules, 'itemid'),
					'hostids' => [$templateId]
				]);

				if (!$result) {
					throw new Exception();
				}
			}

			// copy template screens
			$dbTemplateScreens = API::TemplateScreen()->get([
				'output' => ['screenid'],
				'templateids' => $cloneTemplateId,
				'preservekeys' => true,
				'inherited' => false
			]);

			if ($dbTemplateScreens) {
				$result &= API::TemplateScreen()->copy([
					'screenIds' => zbx_objectValues($dbTemplateScreens, 'screenid'),
					'templateIds' => $templateId
				]);

				if (!$result) {
					throw new Exception();
				}
			}
		}

		if ($result) {
			add_audit_ext($auditAction, AUDIT_RESOURCE_TEMPLATE, $templateId, $templateName, 'hosts', null, null);
		}

		unset($_REQUEST['form'], $_REQUEST['templateid']);
		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message($messageFailed);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$result = API::Template()->massUpdate([
		'templates' => zbx_toObject($_REQUEST['templateid'], 'templateid'),
		'hosts' => []
	]);
	if ($result) {
		$result = API::Template()->delete([getRequest('templateid')]);
	}

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
		uncheckTableRows();
	}
	unset($_REQUEST['delete']);
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}
elseif (isset($_REQUEST['delete_and_clear']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$result = API::Template()->delete([getRequest('templateid')]);

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
		uncheckTableRows();
	}
	unset($_REQUEST['delete']);
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['template.massdelete', 'template.massdeleteclear']) && hasRequest('templates')) {
	$templates = getRequest('templates');

	DBstart();

	$result = true;

	if (getRequest('action') === 'template.massdelete') {
		$result = API::Template()->massUpdate([
			'templates' => zbx_toObject($templates, 'templateid'),
			'hosts' => []
		]);
	}

	if ($result) {
		$result = API::Template()->delete($templates);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}

/*
 * Display
 */
$pageFilter = new CPageFilter([
	'config' => [
		'individual' => 1
	],
	'groups' => [
		'templated_hosts' => true,
		'editable' => true
	],
	'groupid' => getRequest('groupid')
]);
$_REQUEST['groupid'] = $pageFilter->groupid;

if (hasRequest('form')) {
	$data = [
		'form' => getRequest('form'),
		'templateid' => getRequest('templateid', 0),
		'show_inherited_macros' => getRequest('show_inherited_macros', 0)
	];

	if ($data['templateid'] != 0) {
		$dbTemplates = API::Template()->get([
			'templateids' => $data['templateid'],
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => ['templateid', 'name'],
			'selectMacros' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		]);
		$data['dbTemplate'] = reset($dbTemplates);

		$data['original_templates'] = [];
		foreach ($data['dbTemplate']['parentTemplates'] as $parentTemplate) {
			$data['original_templates'][$parentTemplate['templateid']] = $parentTemplate['templateid'];
		}
	}
	else {
		$data['original_templates'] = [];
	}

	// description
	$data['description'] = ($data['templateid'] != 0 && !hasRequest('form_refresh'))
		? $data['dbTemplate']['description']
		: getRequest('description');

	$templateIds = getRequest('templates', hasRequest('form_refresh') ? [] : $data['original_templates']);

	// Get linked templates.
	$data['linkedTemplates'] = API::Template()->get([
		'output' => ['templateid', 'name'],
		'templateids' => $templateIds,
		'preservekeys' => true
	]);

	$data['writable_templates'] = API::Template()->get([
		'output' => ['templateid'],
		'templateids' => $templateIds,
		'editable' => true,
		'preservekeys' => true
	]);

	CArrayHelper::sort($data['linkedTemplates'], ['name']);

	$groups = [];

	if (!hasRequest('form_refresh')) {
		if ($data['templateid'] != 0) {
			$groups = zbx_objectValues($data['dbTemplate']['groups'], 'groupid');
		}
		elseif (getRequest('groupid', 0) != 0) {
			$groups[] = getRequest('groupid');
		}
	}
	else {
		$groups = getRequest('groups', []);
	}

	$groupids = [];

	foreach ($groups as $group) {
		if (is_array($group) && array_key_exists('new', $group)) {
			continue;
		}

		$groupids[] = $group;
	}

	// Groups with R and RW permissions.
	$groups_all = $groupids
		? API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $groupids,
			'preservekeys' => true
		])
		: [];

	// Groups with RW permissions.
	$groups_rw = $groupids && (CWebUser::getType() != USER_TYPE_SUPER_ADMIN)
		? API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['groups_ms'] = [];

	// Prepare data for multiselect.
	foreach ($groups as $group) {
		if (is_array($group) && array_key_exists('new', $group)) {
			$data['groups_ms'][] = [
				'id' => $group['new'],
				'name' => $group['new'].' ('._x('new', 'new element in multiselect').')',
				'isNew' => true
			];
		}
		elseif (array_key_exists($group, $groups_all)) {
			$data['groups_ms'][] = [
				'id' => $group,
				'name' => $groups_all[$group]['name'],
				'disabled' => (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) && !array_key_exists($group, $groups_rw)
			];
		}
	}
	CArrayHelper::sort($data['groups_ms'], ['name']);

	$view = new CView('configuration.template.edit', $data);
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.templates.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.templates.filter_name');
	}

	$filter = [
		'name' => CProfile::get('web.templates.filter_name', '')
	];

	$config = select_config();

	// get templates
	$templates = [];

	if ($pageFilter->groupsSelected) {
		$templates = API::Template()->get([
			'output' => ['templateid', $sortField],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'groupids' => $pageFilter->groupids,
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		]);
	}
	order_result($templates, $sortField, $sortOrder);

	$url = (new CUrl('templates.php'))
		->setArgument('groupid', getRequest('groupid', 0));

	$paging = getPagingLine($templates, $sortOrder, $url);

	$templates = API::Template()->get([
		'output' => ['templateid', 'name'],
		'selectHosts' => ['hostid', 'name', 'status'],
		'selectTemplates' => ['templateid', 'name', 'status'],
		'selectParentTemplates' => ['templateid', 'name', 'status'],
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'templateids' => zbx_objectValues($templates, 'templateid'),
		'editable' => true
	]);

	order_result($templates, $sortField, $sortOrder);

	// Select writable templates:
	$linked_template_ids = [];
	$writable_templates = [];
	$linked_hosts_ids = [];
	$writable_hosts = [];
	foreach ($templates as $template) {
		$linked_template_ids = array_merge(
			$linked_template_ids,
			zbx_objectValues($template['parentTemplates'], 'templateid'),
			zbx_objectValues($template['templates'], 'templateid'),
			zbx_objectValues($template['hosts'], 'hostid')
		);

		$linked_hosts_ids = array_merge(
			$linked_hosts_ids,
			zbx_objectValues($template['hosts'], 'hostid')
		);
	}
	if ($linked_template_ids) {
		$linked_template_ids = array_unique($linked_template_ids);
		$writable_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $linked_template_ids,
			'editable' => true,
			'preservekeys' => true
		]);
	}
	if ($linked_hosts_ids) {
		$linked_hosts_ids = array_unique($linked_hosts_ids);
		$writable_hosts = API::Host()->get([
			'output' => ['hostid'],
			'hostsids' => $linked_hosts_ids,
			'editable' => true,
			'preservekeys' => true
		]);
	}

	$data = [
		'pageFilter' => $pageFilter,
		'templates' => $templates,
		'paging' => $paging,
		'filter' => $filter,
		'sortField' => $sortField,
		'sortOrder' => $sortOrder,
		'config' => [
			'max_in_table' => $config['max_in_table']
		],
		'writable_templates' => $writable_templates,
		'writable_hosts' => $writable_hosts,
		'profileIdx' => 'web.templates.filter',
		'active_tab' => CProfile::get('web.templates.filter.active', 1)
	];

	$view = new CView('configuration.template.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';

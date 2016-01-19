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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
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
	'hosts'				=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'groups'			=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'clear_templates'	=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'templates'			=> [T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'add_templates'		=> [T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'add_template' 		=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'templateid'		=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	'isset({form}) && {form} == "update"'],
	'template_name'		=> [T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({add}) || isset({update})', _('Template name')],
	'visiblename'		=> [T_ZBX_STR, O_OPT, null,		null,	'isset({add}) || isset({update})'],
	'groupid'			=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'twb_groupid'		=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'newgroup'			=> [T_ZBX_STR, O_OPT, null,		null,	null],
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
	// sort and sortorder
	'sort'				=> [T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),									null],
	'sortorder'			=> [T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isWritable([$_REQUEST['groupid']])) {
	access_deny();
}
if (getRequest('templateid') && !API::Template()->isWritable([$_REQUEST['templateid']])) {
	access_deny();
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
	$_REQUEST['clear_templates'] = getRequest('clear_templates', []);

	$unlinkTemplates = [];

	if (hasRequest('unlink') && is_array(getRequest('unlink'))) {
		$unlinkTemplates = array_keys(getRequest('unlink'));
	}
	elseif (hasRequest('unlink_and_clear') && is_array(getRequest('unlink_and_clear'))) {
		$unlinkTemplates = array_keys(getRequest('unlink_and_clear'));
		$_REQUEST['clear_templates'] = array_merge(getRequest('unlink_and_clear'), $unlinkTemplates);
	}

	foreach ($unlinkTemplates as $id) {
		unset($_REQUEST['templates'][array_search($id, $_REQUEST['templates'])]);
	}
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['templateid'])) {
	$_REQUEST['form'] = 'clone';
	unset($_REQUEST['templateid'], $_REQUEST['hosts']);
}
elseif (isset($_REQUEST['full_clone']) && isset($_REQUEST['templateid'])) {
	$_REQUEST['form'] = 'full_clone';
	$_REQUEST['hosts'] = [];
}
elseif (hasRequest('add') || hasRequest('update')) {
	$templateId = getRequest('templateid');

	try {
		DBstart();

		$templates = getRequest('templates', []);
		$templateName = getRequest('template_name', '');

		// clone template id
		$cloneTemplateId = null;
		$templatesClear = getRequest('clear_templates', []);

		if (getRequest('form') === 'full_clone') {
			$cloneTemplateId = $templateId;
			$templateId = null;
		}

		// macros
		$macros = getRequest('macros', []);

		// groups
		$groups = getRequest('groups', []);
		$groups = zbx_toObject($groups, 'groupid');

		// create new group
		$newGroup = getRequest('newgroup');

		if (!zbx_empty($newGroup)) {
			$result = API::HostGroup()->create([
				'name' => $newGroup
			]);

			$newGroup = API::HostGroup()->get([
				'groupids' => $result['groupids'],
				'output' => API_OUTPUT_EXTEND
			]);

			if ($newGroup) {
				$groups = array_merge($groups, $newGroup);
			}
			else {
				throw new Exception();
			}
		}

		// linked templates
		$linkedTemplates = $templates;
		$templates = [];
		foreach ($linkedTemplates as $linkedTemplateId) {
			$templates[] = ['templateid' => $linkedTemplateId];
		}

		$templatesClear = zbx_toObject($templatesClear, 'templateid');

		// discovered hosts
		$dbHosts = API::Host()->get([
			'output' => ['hostid'],
			'hostids' => getRequest('hosts', []),
			'templated_hosts' => true,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		// create / update template
		$template = [
			'host' => $templateName,
			'name' => getRequest('visiblename', ''),
			'groups' => $groups,
			'templates' => $templates,
			'hosts' => $dbHosts,
			'macros' => $macros,
			'description' => getRequest('description', '')
		];

		if ($templateId) {
			$template['templateid'] = $templateId;
			$template['templates_clear'] = $templatesClear;

			$messageSuccess = _('Template updated');
			$messageFailed = _('Cannot update template');
			$auditAction = AUDIT_ACTION_UPDATE;

			$result = API::Template()->update($template);
			if (!$result) {
				throw new Exception();
			}
		}
		else {
			$messageSuccess = _('Template added');
			$messageFailed = _('Cannot add template');
			$auditAction = AUDIT_ACTION_ADD;

			$result = API::Template()->create($template);

			if ($result) {
				$templateId = reset($result['templateids']);
			}
			else {
				throw new Exception();
			}
		}

		// full clone
		if ($templateId && $cloneTemplateId && getRequest('form') === 'full_clone') {
			if (!copyApplications($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			if (!copyItems($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			// copy web scenarios
			if (!copyHttpTests($cloneTemplateId, $templateId)) {
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
$templateWidget = (new CWidget())->setTitle(_('Templates'));

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

	if ($templateId = getRequest('templateid', 0)) {
		$templateWidget->addItem(get_header_host_table('', $templateId));
	}

	$data = [
		'form' => getRequest('form'),
		'groupId' => getRequest('groupid', 0),
		'groupIds' => getRequest('groups', []),
		'show_inherited_macros' => getRequest('show_inherited_macros', 0)
	];

	if ($templateId) {
		$dbTemplates = API::Template()->get([
			'templateids' => $templateId,
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
	$data['description'] = ($templateId && !hasRequest('form_refresh'))
		? $data['dbTemplate']['description']
		: getRequest('description');

	$templateIds = getRequest('templates', hasRequest('form_refresh') ? [] : $data['original_templates']);

	// Get linked templates.
	$data['linkedTemplates'] = API::Template()->get([
		'templateids' => $templateIds,
		'output' => ['templateid', 'name']
	]);
	CArrayHelper::sort($data['linkedTemplates'], ['name']);

	// Get user allowed host groups and sort them by name.
	$data['groupsAllowed'] = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'editable' => true,
		'preservekeys' => true
	]);
	CArrayHelper::sort($data['groupsAllowed'], ['name']);

	// Get other host groups that user has also read permissions and sort by name.
	$data['groupsAll'] = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'preservekeys' => true
	]);
	CArrayHelper::sort($data['groupsAll'], ['name']);

	// "Other | group" tweenbox selector for hosts and templates
	$data['twb_groupid'] = getRequest('twb_groupid', 0);
	if ($data['twb_groupid'] == 0) {
		$group = reset($data['groupsAllowed']);
		$data['twb_groupid'] = $group['groupid'];
	}

	// Get allowed hosts from selected twb_groupid combobox.
	$data['hostsAllowedToAdd'] = API::Host()->get([
		'output' => ['hostid', 'name'],
		'groupids' => $data['twb_groupid'],
		'templated_hosts' => true,
		'editable' => true,
		'preservekeys' => true,
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
	]);
	CArrayHelper::sort($data['hostsAllowedToAdd'], ['name']);

	if ($templateId != 0 && !hasRequest('form_refresh')) {
		$data['groupIds'] = zbx_objectValues($data['dbTemplate']['groups'], 'groupid');

		// Get template hosts from DB.
		$hostIdsLinkedTo = API::Host()->get([
			'output' => ['hostid'],
			'templateids' => $templateId,
			'templated_hosts' => true,
			'preservekeys' => true
		]);
		$hostIdsLinkedTo = array_keys($hostIdsLinkedTo);
	}
	else {
		if ($data['groupId'] != 0 && !$data['groupIds']) {
			$data['groupIds'][] = $data['groupId'];
		}
		$hostIdsLinkedTo = getRequest('hosts', []);
	}

	if ($data['groupIds']) {
		$data['groupIds'] = array_combine($data['groupIds'], $data['groupIds']);
	}

	if ($hostIdsLinkedTo) {
		$hostIdsLinkedTo = array_combine($hostIdsLinkedTo, $hostIdsLinkedTo);
	}

	// Select allowed selected hosts.
	$data['hostsAllowed'] = API::Host()->get([
		'output' => ['hostid', 'name', 'flags'],
		'hostids' => $hostIdsLinkedTo,
		'templated_hosts' => true,
		'editable' => true,
		'preservekeys' => true,
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
	]);
	CArrayHelper::sort($data['hostsAllowed'], ['name']);

	// Select selected hosts including read only.
	$data['hostsAll'] = API::Host()->get([
		'output' => ['hostid', 'name', 'flags'],
		'hostids' => $hostIdsLinkedTo,
		'templated_hosts' => true
	]);
	CArrayHelper::sort($data['hostsAll'], ['name']);

	$data['hostIdsLinkedTo'] = $hostIdsLinkedTo;
	$data['templateId'] = $templateId;

	$templateForm = new CView('configuration.template.edit', $data);
	$templateWidget->addItem($templateForm->render());
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$controls = (new CList())
		->addItem([_('Group'), SPACE, $pageFilter->getGroupsCB()])
		->addItem(new CSubmit('form', _('Create template')))
		->addItem(
			(new CButton('form', _('Import')))
				->onClick('redirect("conf.import.php?rules_preset=template")')
		);
	$frmForm = (new CForm('get'))
		->cleanItems()
		->addItem($controls);

	$templateWidget->setControls($frmForm);

	$form = (new CForm())->setName('templates');

	$table = (new CTableInfo())
		->setHeader([
			(new CColHeader(
				(new CCheckBox('all_templates'))->onClick("checkAll('".$form->getName()."', 'all_templates', 'templates');")
			))->addClass(ZBX_STYLE_CELL_WIDTH),
			make_sorting_header(_('Templates'), 'name', $sortField, $sortOrder),
			_('Applications'),
			_('Items'),
			_('Triggers'),
			_('Graphs'),
			_('Screens'),
			_('Discovery'),
			_('Web'),
			_('Linked templates'),
			_('Linked to')
		]);

	// get templates
	$templates = [];

	if ($pageFilter->groupsSelected) {
		$templates = API::Template()->get([
			'output' => ['templateid', $sortField],
			'groupids' => ($pageFilter->groupid > 0) ? $pageFilter->groupid : null,
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		]);
	}

	// sorting && paging
	order_result($templates, $sortField, $sortOrder);
	$paging = getPagingLine($templates, $sortOrder);

	$templates = API::Template()->get([
		'templateids' => zbx_objectValues($templates, 'templateid'),
		'editable' => true,
		'output' => ['name', 'proxy_hostid'],
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
		'nopermissions' => true
	]);

	order_result($templates, $sortField, $sortOrder);

	foreach ($templates as $template) {
		$templatesOutput = [];

		if ($template['proxy_hostid']) {
			$proxy = get_host_by_hostid($template['proxy_hostid']);

			$templatesOutput[] = $proxy['host'].NAME_DELIMITER;
		}

		$templatesOutput[] = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid'].url_param('groupid'));

		$linkedTemplatesOutput = [];
		$linkedToOutput = [];

		$i = 0;

		order_result($template['parentTemplates'], 'name');

		foreach ($template['parentTemplates'] as $parentTemplate) {
			$i++;

			if ($i > $config['max_in_table']) {
				$linkedTemplatesOutput[] = ' &hellip;';

				break;
			}

			if ($linkedTemplatesOutput) {
				$linkedTemplatesOutput[] = ', ';
			}

			$url = 'templates.php?form=update&templateid='.$parentTemplate['templateid'].url_param('groupid');

			$linkedTemplatesOutput[] = (new CLink($parentTemplate['name'], $url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}

		$i = 0;

		$linkedToObjects = array_merge($template['hosts'], $template['templates']);
		order_result($linkedToObjects, 'name');

		foreach ($linkedToObjects as $linkedToObject) {
			$i++;

			if ($i > $config['max_in_table']) {
				$linkedToOutput[] = ' &hellip;';

				break;
			}

			if ($linkedToOutput) {
				$linkedToOutput[] = ', ';
			}

			if ($linkedToObject['status'] == HOST_STATUS_TEMPLATE) {
				$url = 'templates.php?form=update&templateid='.$linkedToObject['templateid'].url_param('groupid');
			}
			else {
				$url = 'hosts.php?form=update&hostid='.$linkedToObject['hostid'].url_param('groupid');
			}

			$link = (new CLink($linkedToObject['name'], $url))->addClass(ZBX_STYLE_LINK_ALT);

			if ($linkedToObject['status'] == HOST_STATUS_TEMPLATE) {
				$link->addClass(ZBX_STYLE_GREY);
			}
			else {
				$link->addClass($linkedToObject['status'] == HOST_STATUS_MONITORED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
			}

			$linkedToOutput[] = $link;
		}

		$table->addRow([
			new CCheckBox('templates['.$template['templateid'].']', $template['templateid']),
			(new CCol($templatesOutput))->addClass(ZBX_STYLE_NOWRAP),
			[
				new CLink(_('Applications'), 'applications.php?hostid='.$template['templateid'].url_param('groupid')),
				CViewHelper::showNum($template['applications'])
			],
			[
				new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$template['templateid'].url_param('groupid')),
				CViewHelper::showNum($template['items'])
			],
			[
				new CLink(_('Triggers'), 'triggers.php?hostid='.$template['templateid'].url_param('groupid')),
				CViewHelper::showNum($template['triggers'])
			],
			[
				new CLink(_('Graphs'), 'graphs.php?hostid='.$template['templateid'].url_param('groupid')),
				CViewHelper::showNum($template['graphs'])
			],
			[
				new CLink(_('Screens'), 'screenconf.php?templateid='.$template['templateid']),
				CViewHelper::showNum($template['screens'])
			],
			[
				new CLink(_('Discovery'), 'host_discovery.php?hostid='.$template['templateid']),
				CViewHelper::showNum($template['discoveries'])
			],
			[
				new CLink(_('Web'), 'httpconf.php?hostid='.$template['templateid'].url_param('groupid')),
				CViewHelper::showNum($template['httpTests'])
			],
			$linkedTemplatesOutput,
			$linkedToOutput
		]);
	}

	$footer = new CActionButtonList('action', 'templates', [
		'template.export' => ['name' => _('Export')],
		'template.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected templates?')],
		'template.massdeleteclear' => ['name' => _('Delete and clear'),
			'confirm' => _('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)')
		]
	]);

	$form->addItem([$table, $paging, $footer]);
	$templateWidget->addItem($form);
}

$templateWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';

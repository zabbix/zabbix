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
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';

if (isset($_REQUEST['go']) && $_REQUEST['go'] == 'export' && isset($_REQUEST['templates'])) {
	$exportData = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_export_templates.xml';
}
else {
	$exportData = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = _('Configuration of templates');
	$page['file'] = 'templates.php';
	$page['hist_arg'] = array('groupid');
	$page['scripts'] = array('multiselect.js');
}

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR						TYPE		OPTIONAL FLAGS			VALIDATION	EXCEPTION
$fields = array(
	'hosts'				=> array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null),
	'groups'			=> array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null),
	'clear_templates'	=> array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null),
	'templates'			=> array(T_ZBX_INT, O_OPT, null,		DB_ID,	null),
	'add_templates'		=> array(T_ZBX_INT, O_OPT, null,		DB_ID,	null),
	'add_template' 		=> array(T_ZBX_STR, O_OPT, null,		null,	null),
	'templateid'		=> array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	'isset({form})&&{form}=="update"'),
	'template_name'		=> array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({save})', _('Template name')),
	'visiblename'		=> array(T_ZBX_STR, O_OPT, null,		null,	'isset({save})'),
	'groupid'			=> array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null),
	'twb_groupid'		=> array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null),
	'newgroup'			=> array(T_ZBX_STR, O_OPT, null,		null,	null),
	'macros_rem'		=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'macros'			=> array(T_ZBX_STR, O_OPT, P_SYS,		null,	null),
	'macro_new'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
	'value_new'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
	'macro_add'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	// actions
	'go'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'unlink'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'unlink_and_clear'	=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'save'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'clone'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'full_clone'		=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete_and_clear'	=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'cancel'			=> array(T_ZBX_STR, O_OPT, P_SYS,		null,	null),
	'form'				=> array(T_ZBX_STR, O_OPT, P_SYS,		null,	null),
	'form_refresh'		=> array(T_ZBX_STR, O_OPT, null,		null,	null)
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
if (get_request('templateid') && !API::Template()->isWritable(array($_REQUEST['templateid']))) {
	access_deny();
}

$templateIds = get_request('templates', array());

if ($exportData) {
	$export = new CConfigurationExport(array('templates' => $templateIds));
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();

	if (hasErrorMesssages()) {
		show_messages();
	}
	else {
		print($exportData);
	}
	exit();
}

/*
 * Actions
 */
if (isset($_REQUEST['add_template']) && isset($_REQUEST['add_templates'])) {
	$_REQUEST['templates'] = array_merge($templateIds, $_REQUEST['add_templates']);
}
if (hasRequest('unlink') || hasRequest('unlink_and_clear')) {
	$_REQUEST['clear_templates'] = getRequest('clear_templates', array());

	$unlinkTemplates = array();

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
	$_REQUEST['hosts'] = array();
}
elseif (isset($_REQUEST['save'])) {
	try {
		DBstart();

		$macros = get_request('macros', array());
		$groups = get_request('groups', array());
		$templates = get_request('templates', array());
		$templatesClear = get_request('clear_templates', array());
		$templateId = get_request('templateid', 0);
		$newGroup = get_request('newgroup', 0);
		$templateName = get_request('template_name', '');
		$visibleName = get_request('visiblename', '');
		$cloneTemplateId = false;

		if ($_REQUEST['form'] == 'full_clone') {
			$cloneTemplateId = $templateId;
			$templateId = null;
		}

		if ($templateId) {
			$msgOk = _('Template updated');
			$msgFail = _('Cannot update template');
		}
		else {
			$msgOk = _('Template added');
			$msgFail = _('Cannot add template');
		}

		foreach ($macros as $key => $macro) {
			if (zbx_empty($macro['macro']) && zbx_empty($macro['value'])) {
				unset($macros[$key]);
			}
		}

		foreach ($macros as $key => $macro) {
			// transform macros to uppercase {$aaa} => {$AAA}
			$macros[$key]['macro'] = zbx_strtoupper($macro['macro']);
		}

		// create new group
		$groups = zbx_toObject($groups, 'groupid');

		if (!zbx_empty($newGroup)) {
			$result = API::HostGroup()->create(array('name' => $newGroup));

			if (!$result) {
				throw new Exception();
			}

			$newGroup = API::HostGroup()->get(array(
				'groupids' => $result['groupids'],
				'output' => API_OUTPUT_EXTEND
			));

			if ($newGroup) {
				$groups = array_merge($groups, $newGroup);
			}
			else {
				throw new Exception();
			}
		}

		$linkedTemplates = $templates;
		$templates = array();
		foreach ($linkedTemplates as $linkedTemplateId) {
			$templates[] = array('templateid' => $linkedTemplateId);
		}

		$templatesClear = zbx_toObject($templatesClear, 'templateid');

		// skip discovered hosts
		$hosts = API::Host()->get(array(
			'hostids' => get_request('hosts', array()),
			'output' => array('hostid'),
			'templated_hosts' => true,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
		));

		$template = array(
			'host' => $templateName,
			'name' => $visibleName,
			'groups' => $groups,
			'templates' => $templates,
			'hosts' => $hosts,
			'macros' => $macros
		);

		// create/update template
		if ($templateId) {
			$created = false;
			$template['templateid'] = $templateId;
			$template['templates_clear'] = $templatesClear;

			if (!API::Template()->update($template)) {
				throw new Exception();
			}
		}
		else {
			$created = true;
			$result = API::Template()->create($template);

			if ($result) {
				$templateId = reset($result['templateids']);
			}
			else {
				throw new Exception();
			}
		}

		// full clone
		if (!zbx_empty($templateId) && $templateId && $cloneTemplateId && $_REQUEST['form'] == 'full_clone') {
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

			// clone triggers
			$triggers = API::Trigger()->get(array(
				'hostids' => $cloneTemplateId,
				'output' => array('triggerid'),
				'inherited' => false
			));
			if ($triggers) {
				if (!copyTriggersToHosts(zbx_objectValues($triggers, 'triggerid'), $templateId, $cloneTemplateId)) {
					throw new Exception();
				}
			}

			// host graphs
			$dbGraphs = API::Graph()->get(array(
				'hostids' => $cloneTemplateId,
				'inherited' => false,
				'output' => API_OUTPUT_REFER
			));

			$result = true;
			foreach ($dbGraphs as $dbGraph) {
				$result &= (bool) copyGraphToHost($dbGraph['graphid'], $templateId);
			}

			if (!$result) {
				throw new Exception();
			}

			// clone discovery rules
			$discoveryRules = API::DiscoveryRule()->get(array(
				'hostids' => $cloneTemplateId,
				'inherited' => false
			));
			if ($discoveryRules) {
				$copyDiscoveryRules = API::DiscoveryRule()->copy(array(
					'discoveryids' => zbx_objectValues($discoveryRules, 'itemid'),
					'hostids' => array($templateId)
				));

				if (!$copyDiscoveryRules) {
					throw new Exception();
				}
			}

			// clone screens
			$screens = API::TemplateScreen()->get(array(
				'templateids' => $cloneTemplateId,
				'output' => array('screenid'),
				'preservekeys' => true,
				'inherited' => false
			));
			if ($screens) {
				$screensCopied = API::TemplateScreen()->copy(array(
					'screenIds' => zbx_objectValues($screens, 'screenid'),
					'templateIds' => $templateId
				));

				if (!$screensCopied) {
					throw new Exception();
				}
			}
		}

		DBend(true);

		show_messages(true, $msgOk, $msgFail);
		clearCookies(true);

		if ($created) {
			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TEMPLATE, $templateId, $templateName, 'hosts', null, null);
		}
		unset($_REQUEST['form'], $_REQUEST['templateid']);

	}
	catch (Exception $e) {
		DBend(false);

		show_messages(false, $msgOk, $msgFail);
	}
	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$goResult = true;

	$result = API::Template()->massUpdate(array(
		'templates' => zbx_toObject($_REQUEST['templateid'], 'templateid'),
		'hosts' => array()
	));
	if ($result) {
		$result = API::Template()->delete($_REQUEST['templateid']);
	}

	$result = DBend($result);

	show_messages($result, _('Template deleted'), _('Cannot delete template'));
	clearCookies($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
	}
	unset($_REQUEST['delete']);
}
elseif (isset($_REQUEST['delete_and_clear']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$goResult = true;
	$result = API::Template()->delete($_REQUEST['templateid']);

	$result = DBend($result);

	show_messages($result, _('Template deleted'), _('Cannot delete template'));
	clearCookies($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
	}
	unset($_REQUEST['delete']);
}
elseif (str_in_array($_REQUEST['go'], array('delete', 'delete_and_clear')) && isset($_REQUEST['templates'])) {
	$templates = get_request('templates', array());

	DBstart();

	$goResult = true;

	if ($_REQUEST['go'] == 'delete') {
		$goResult = API::Template()->massUpdate(array(
			'templates' => zbx_toObject($templates, 'templateid'),
			'hosts' => array()
		));
	}

	if ($goResult) {
		$goResult = API::Template()->delete($templates);
	}

	$goResult = DBend($goResult);

	show_messages($goResult, _('Template deleted'), _('Cannot delete template'));
	clearCookies($goResult);
}

/*
 * Display
 */
$templateWidget = new CWidget();

$pageFilter = new CPageFilter(array(
	'config' => array(
		'individual' => 1
	),
	'groups' => array(
		'templated_hosts' => true,
		'editable' => true
	),
	'groupid' => get_request('groupid', null)
));
$_REQUEST['groupid'] = $pageFilter->groupid;

if (isset($_REQUEST['form'])) {
	$templateWidget->addPageHeader(_('CONFIGURATION OF TEMPLATES'));

	if ($templateId = get_request('templateid', 0)) {
		$templateWidget->addItem(get_header_host_table('', $templateId));
	}

	$data = array();

	if ($templateId) {
		$dbTemplates = API::Template()->get(array(
			'templateids' => $templateId,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => array('templateid', 'name'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		));
		$data['dbTemplate'] = reset($dbTemplates);

		$data['original_templates'] = array();
		foreach ($data['dbTemplate']['parentTemplates'] as $parentTemplate) {
			$data['original_templates'][$parentTemplate['templateid']] = $parentTemplate['templateid'];
		}
	}
	else {
		$data['original_templates'] = array();
	}

	$templateIds = getRequest('templates', hasRequest('form_refresh') ? array() : $data['original_templates']);

	$data['linkedTemplates'] = API::Template()->get(array(
		'templateids' => $templateIds,
		'output' => array('templateid', 'name')
	));

	CArrayHelper::sort($data['linkedTemplates'], array('name'));

	$templateForm = new CView('configuration.template.edit', $data);
	$templateWidget->addItem($templateForm->render());
}
else {
	$displayNodes = (is_array(get_current_nodeid()) && $pageFilter->groupid == 0);

	$frmForm = new CForm();
	$frmForm->cleanItems();
	$frmForm->addItem(new CDiv(array(
		new CSubmit('form', _('Create template')),
		new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=template")')
	)));
	$frmForm->addItem(new CVar('groupid', $_REQUEST['groupid'], 'filter_groupid_id'));

	$templateWidget->addPageHeader(_('CONFIGURATION OF TEMPLATES'), $frmForm);

	$frmGroup = new CForm('get');
	$frmGroup->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB(true)));

	$templateWidget->addHeader(_('Templates'), $frmGroup);
	$templateWidget->addHeaderRowNumber();

	$form = new CForm();
	$form->setName('templates');

	$table = new CTableInfo(_('No templates found.'));
	$table->setHeader(array(
		new CCheckBox('all_templates', null, "checkAll('".$form->getName()."', 'all_templates', 'templates');"),
		$displayNodes ? _('Node') : null,
		make_sorting_header(_('Templates'), 'name'),
		_('Applications'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Screens'),
		_('Discovery'),
		_('Web'),
		_('Linked templates'),
		_('Linked to')
	));

	// get templates
	$templates = array();

	$sortfield = getPageSortField('name');
	$sortorder = getPageSortOrder();

	if ($pageFilter->groupsSelected) {
		$templates = API::Template()->get(array(
			'groupids' => ($pageFilter->groupid > 0) ? $pageFilter->groupid : null,
			'editable' => true,
			'sortfield' => $sortfield,
			'limit' => $config['search_limit'] + 1
		));
	}

	// sorting && paging
	order_result($templates, $sortfield, $sortorder);
	$paging = getPagingLine($templates, array('templateid'));

	$templates = API::Template()->get(array(
		'templateids' => zbx_objectValues($templates, 'templateid'),
		'editable' => true,
		'output' => array('name', 'proxy_hostid'),
		'selectHosts' => array('hostid', 'name', 'status'),
		'selectTemplates' => array('hostid', 'name', 'status'),
		'selectParentTemplates' => array('hostid', 'name', 'status'),
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'nopermissions' => true
	));

	order_result($templates, $sortfield, $sortorder);

	foreach ($templates as $template) {
		$templatesOutput = array();

		if ($template['proxy_hostid']) {
			$proxy = get_host_by_hostid($template['proxy_hostid']);

			$templatesOutput[] = $proxy['host'].NAME_DELIMITER;
		}

		$templatesOutput[] = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid'].url_param('groupid'));

		$applications = array(new CLink(_('Applications'), 'applications.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
			' ('.$template['applications'].')');
		$items = array(new CLink(_('Items'), 'items.php?filter_set=1&groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
			' ('.$template['items'].')');
		$triggers = array(new CLink(_('Triggers'), 'triggers.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
			' ('.$template['triggers'].')');
		$graphs = array(new CLink(_('Graphs'), 'graphs.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
			' ('.$template['graphs'].')');
		$screens = array(new CLink(_('Screens'), 'screenconf.php?templateid='.$template['templateid']),
			' ('.$template['screens'].')');
		$discoveries = array(new CLink(_('Discovery'), 'host_discovery.php?&hostid='.$template['templateid']),
			' ('.$template['discoveries'].')');
		$httpTests = array(new CLink(_('Web'), 'httpconf.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
			' ('.$template['httpTests'].')');

		order_result($template['parentTemplates'], 'name');

		$i = 0;
		$linkedTemplatesOutput = array();

		foreach ($template['parentTemplates'] as $linkedTemplate) {
			$i++;

			if ($i > $config['max_in_table']) {
				$linkedTemplatesOutput[] = '...';
				$linkedTemplatesOutput[] = '//empty element for array_pop';
				break;
			}

			$url = 'templates.php?form=update&templateid='.$linkedTemplate['templateid'].url_param('groupid');

			$linkedTemplatesOutput[] = new CLink($linkedTemplate['name'], $url, 'unknown');
			$linkedTemplatesOutput[] = ', ';
		}
		array_pop($linkedTemplatesOutput);

		$i = 0;
		$linkedToOutput = array();
		$linkedToObjects = array();

		foreach ($template['hosts'] as $h) {
			$h['objectid'] = $h['hostid'];
			$linkedToObjects[] = $h;
		}

		foreach ($template['templates'] as $h) {
			$h['objectid'] = $h['templateid'];
			$linkedToObjects[] = $h;
		}

		order_result($linkedToObjects, 'name');

		foreach ($linkedToObjects as $linkedToHost) {
			if (++$i > $config['max_in_table']) {
				$linkedToOutput[] = '...';
				$linkedToOutput[] = '//empty element for array_pop';
				break;
			}

			switch ($linkedToHost['status']) {
				case HOST_STATUS_NOT_MONITORED:
					$style = 'on';
					$url = 'hosts.php?form=update&hostid='.$linkedToHost['objectid'].'&groupid='.$_REQUEST['groupid'];
					break;

				case HOST_STATUS_TEMPLATE:
					$style = 'unknown';
					$url = 'templates.php?form=update&templateid='.$linkedToHost['objectid'];
					break;

				default:
					$style = null;
					$url = 'hosts.php?form=update&hostid='.$linkedToHost['objectid'].'&groupid='.$_REQUEST['groupid'];
			}

			$linkedToOutput[] = new CLink($linkedToHost['name'], $url, $style);
			$linkedToOutput[] = ', ';
		}
		array_pop($linkedToOutput);

		$table->addRow(array(
			new CCheckBox('templates['.$template['templateid'].']', null, null, $template['templateid']),
			$displayNodes ? get_node_name_by_elid($template['templateid'], true) : null,
			$templatesOutput,
			$applications,
			$items,
			$triggers,
			$graphs,
			$screens,
			$discoveries,
			$httpTests,
			$linkedTemplatesOutput ? new CCol($linkedTemplatesOutput, 'wraptext') : '-',
			$linkedToOutput ? new CCol($linkedToOutput, 'wraptext') : '-'
		));
	}

	$goBox = new CComboBox('go');
	$goBox->addItem('export', _('Export selected'));
	$goOption = new CComboItem('delete', _('Delete selected'));
	$goOption->setAttribute('confirm', _('Delete selected templates?'));
	$goBox->addItem($goOption);
	$goOption = new CComboItem('delete_and_clear', _('Delete selected with linked elements'));
	$goOption->setAttribute('confirm', _('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)'));
	$goBox->addItem($goOption);
	$goButton = new CSubmit('goButton', _('Go').' (0)');
	$goButton->setAttribute('id', 'goButton');

	zbx_add_post_js('chkbxRange.pageGoName = "templates";');

	$footer = get_table_header(array($goBox, $goButton));

	$form->addItem(array($paging, $table, $paging, $footer));
	$templateWidget->addItem($form);
}

$templateWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';

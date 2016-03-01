<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

if(isset($_REQUEST['go']) && ($_REQUEST['go'] == 'export') && isset($_REQUEST['templates'])){
	$EXPORT_DATA = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_export_templates.xml';
}
else{
	$EXPORT_DATA = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = _('Configuration of templates');
	$page['file'] = 'templates.php';
	$page['hist_arg'] = array('groupid');
}

require_once dirname(__FILE__).'/include/page_header.php';


//		VAR						TYPE		OPTIONAL FLAGS			VALIDATION	EXCEPTION
$fields = array(
	'hosts'				=> array(T_ZBX_INT,	O_OPT,	P_SYS,		DB_ID, 		null),
	'groups'			=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID, 		null),
	'clear_templates'	=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID, 		null),
	'templates'			=> array(T_ZBX_STR, O_OPT,	null,		null,		null),
	'templateid'		=> array(T_ZBX_INT,	O_OPT,	P_SYS,		DB_ID,		'isset({form})&&({form}=="update")'),
	'template_name'		=> array(T_ZBX_STR,	O_OPT,	NOT_EMPTY,	null,		'isset({save})'),
	'visiblename'		=> array(T_ZBX_STR,	O_OPT,	null,		null,		'isset({save})'),
	'groupid'			=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID,		null),
	'twb_groupid'		=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID,		null),
	'newgroup'			=> array(T_ZBX_STR, O_OPT,	null,		null,		null),

	'macros_rem'		=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'macros'			=> array(T_ZBX_STR, O_OPT, P_SYS,		null,	null),
	'macro_new'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
	'value_new'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
	'macro_add'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// actions
	'go'				=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
//form
	'unlink'			=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'unlink_and_clear'	=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'save'				=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'clone'				=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'full_clone'		=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'delete'			=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'delete_and_clear'	=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'cancel'			=> array(T_ZBX_STR, O_OPT,	P_SYS,			null,		null),
// other
	'form'				=> array(T_ZBX_STR, O_OPT,	P_SYS,			null,		null),
	'form_refresh'		=> array(T_ZBX_STR, O_OPT,	null,			null,		null),
);

// OUTER DATA
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

// PERMISSIONS
if(get_request('groupid', 0) > 0){
	$groupids = available_groups($_REQUEST['groupid'], 1);
	if(empty($groupids)) access_deny();
}

if(get_request('templateid', 0) > 0){
	$hostids = available_hosts($_REQUEST['templateid'], 1);
	if(empty($hostids)) access_deny();
}


$templateids = get_request('templates', array());

if ($EXPORT_DATA) {
	$export = new CConfigurationExport(array('templates' => $templateids));
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();
	if (no_errors()) {
		print($exportData);
	}
	else {
		show_messages();
	}
	exit();
}


/**********************************/
/* <<<--- TEMPLATE ACTIONS --->>> */
/**********************************/
/**
 * Unlink, unlink_and_clear
 */
if (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear'])) {
	$_REQUEST['clear_templates'] = get_request('clear_templates', array());

	if (isset($_REQUEST['unlink'])) {
		$unlink_templates = array_keys($_REQUEST['unlink']);
	}
	else {
		$unlink_templates = array_keys($_REQUEST['unlink_and_clear']);
		$_REQUEST['clear_templates'] = zbx_array_merge($_REQUEST['clear_templates'], $unlink_templates);
	}
	foreach ($unlink_templates as $id) {
		unset($_REQUEST['templates'][$id]);
	}
}
/**
 * Clone
 */
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['templateid'])) {
	unset($_REQUEST['templateid']);
	unset($_REQUEST['hosts']);
	$_REQUEST['form'] = 'clone';
}
/**
 * Full_clone
 */
elseif (isset($_REQUEST['full_clone']) && isset($_REQUEST['templateid'])) {
	$_REQUEST['form'] = 'full_clone';
	$_REQUEST['hosts'] = array();
}
/**
 * Save
 */
elseif (isset($_REQUEST['save'])) {
	if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
		access_deny();
	}

	try {
		DBstart();

		$macros = get_request('macros', array());
		$groups = get_request('groups', array());
		$hosts = get_request('hosts', array());
		$templates = get_request('templates', array());
		$templates_clear = get_request('clear_templates', array());
		$templateid = get_request('templateid', 0);
		$newgroup = get_request('newgroup', 0);
		$template_name = get_request('template_name', '');
		$visiblename = get_request('visiblename', '');

		$clone_templateid = false;
		if ($_REQUEST['form'] == 'full_clone') {
			$clone_templateid = $templateid;
			$templateid = null;
		}

		if ($templateid) {
			$msg_ok = _('Template updated');
			$msg_fail = _('Cannot update template');
		}
		else {
			$msg_ok = _('Template added');
			$msg_fail = _('Cannot add template');
		}

		foreach ($macros as $mnum => $macro) {
			if (zbx_empty($macro['macro']) && zbx_empty($macro['value'])) {
				unset($macros[$mnum]);
			}
		}

		foreach ($macros as $mnum => $macro) {
			// transform macros to uppercase {$aaa} => {$AAA}
			$macros[$mnum]['macro'] = zbx_strtoupper($macro['macro']);
		}

		// Create new group
		$groups = zbx_toObject($groups, 'groupid');
		if (!zbx_empty($newgroup)) {
			$result = API::HostGroup()->create(array('name' => $newgroup));
			if (!$result) {
				throw new Exception();
			}
			$newgroup = API::HostGroup()->get(array(
				'groupids' => $result['groupids'],
				'output' => API_OUTPUT_EXTEND
			));
			if ($newgroup) {
				$groups = array_merge($groups, $newgroup);
			}
			else {
				throw new Exception();
			}
		}

		$templates = array_keys($templates);
		$templates = zbx_toObject($templates, 'templateid');
		$templates_clear = zbx_toObject($templates_clear, 'templateid');

		$hosts = zbx_toObject($hosts, 'hostid');

		$template = array(
			'host' => $template_name,
			'name' => $visiblename,
			'groups' => $groups,
			'templates' => $templates,
			'hosts' => $hosts,
			'macros' => $macros
		);

		// Create/update template
		if ($templateid) {
			$created = false;
			$template['templateid'] = $templateid;
			$template['templates_clear'] = $templates_clear;
			if (!API::Template()->update($template)) {
				throw new Exception();
			}
		}
		else {
			$created = true;
			$result = API::Template()->create($template);
			if ($result) {
				$templateid = reset($result['templateids']);
			}
			else {
				throw new Exception();
			}
		}

		// Full clone
		if (!zbx_empty($templateid) && $templateid && $clone_templateid && $_REQUEST['form'] == 'full_clone') {
			if (!copyApplications($clone_templateid, $templateid)) {
				throw new Exception();
			}

			if (!copyItems($clone_templateid, $templateid)) {
				throw new Exception();
			}

			// clone triggers
			$triggers = API::Trigger()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'hostids' => $clone_templateid,
				'inherited' => false
			));
			if ($triggers) {
				if (!copyTriggersToHosts(zbx_objectValues($triggers, 'triggerid'), $templateid, $clone_templateid)) {
					throw new Exception();
				}
			}

			// Host graphs
			$db_graphs = API::Graph()->get(array(
				'hostids' => $clone_templateid,
				'inherited' => false,
				'output' => API_OUTPUT_REFER
			));
			$result = true;
			foreach ($db_graphs as $db_graph) {
				$result &= (bool) copy_graph_to_host($db_graph['graphid'], $templateid);
			}
			if (!$result) {
				throw new Exception();
			}

			// clone discovery rules
			$discoveryRules = API::DiscoveryRule()->get(array(
				'hostids' => $clone_templateid,
				'inherited' => false
			));
			if ($discoveryRules) {
				$copyDiscoveryRules = API::DiscoveryRule()->copy(array(
					'discoveryids' => zbx_objectValues($discoveryRules, 'itemid'),
					'hostids' => array($templateid)
				));
				if (!$copyDiscoveryRules) {
					throw new Exception();
				}
			}

			// clone screens
			$screens = API::TemplateScreen()->get(array(
				'templateids' => $clone_templateid,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true,
				'inherited' => false
			));
			if ($screens) {
				$screensCopied = API::TemplateScreen()->copy(array(
					'screenIds' => zbx_objectValues($screens, 'screenid'),
					'templateIds' => $templateid
				));
				if (!$screensCopied) {
					throw new Exception();
				}
			}
		}

		DBend(true);
		show_messages(true, $msg_ok, $msg_fail);

		if ($created) {
			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TEMPLATE, $templateid, $template_name, 'hosts', NULL, NULL);
		}
		unset($_REQUEST['form']);
		unset($_REQUEST['templateid']);

	}
	catch (Exception $e) {
		DBend(false);
		show_messages(false, $msg_ok, $msg_fail);
	}
	unset($_REQUEST['save']);
}
/**
 * Delete
 */
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$go_result = true;
	$result = API::Template()->massUpdate(array(
		'templates' => zbx_toObject($_REQUEST['templateid'], 'templateid'),
		'hosts' => array()
	));
	if ($result) {
		$result = API::Template()->delete($_REQUEST['templateid']);
	}

	$result = DBend($result);

	show_messages($result, _('Template deleted'), _('Cannot delete template'));
	if ($result) {
		unset($_REQUEST['form']);
		unset($_REQUEST['templateid']);
	}
	unset($_REQUEST['delete']);
}
/**
 * Delete_and_clear
 */
elseif (isset($_REQUEST['delete_and_clear']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$go_result = true;
	$result = API::Template()->delete($_REQUEST['templateid']);

	$result = DBend($result);

	show_messages($result, _('Template deleted'), _('Cannot delete template'));
	if ($result) {
		unset($_REQUEST['form']);
		unset($_REQUEST['templateid']);
	}
	unset($_REQUEST['delete']);
}
// ---------- GO ---------
else if(str_in_array($_REQUEST['go'], array('delete', 'delete_and_clear')) && isset($_REQUEST['templates'])){

	$templates = get_request('templates', array());
	DBstart();

	$go_result = true;
	if ($_REQUEST['go'] == 'delete') {
		$go_result = API::Template()->massUpdate(array(
			'templates' => zbx_toObject($templates, 'templateid'),
			'hosts' => array()
		));
	}
	if($go_result)
		$go_result = API::Template()->delete($templates);

	$go_result = DBend($go_result);

	show_messages($go_result, _('Template deleted'), _('Cannot delete template'));
}

if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
	uncheckTableRows();
}


$template_wdgt = new CWidget();

$options = array(
	'config' => array(
		'individual' => 1
	),
	'groups' => array(
		'templated_hosts' => 1,
		'editable' => 1,
	),
	'groupid' => get_request('groupid', null),
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;

if (isset($_REQUEST['form'])) {
	$template_wdgt->addPageHeader(_('CONFIGURATION OF TEMPLATES'));

	if ($templateid = get_request('templateid', 0)) {
		$template_wdgt->addItem(get_header_host_table('', $templateid));
	}

	$templateForm = new CView('configuration.template.edit');
	$template_wdgt->addItem($templateForm->render());
}
else {
	$frmForm = new CForm();

	$frmForm->cleanItems();
	$buttons = new CDiv(array(
		new CSubmit('form', _('Create template')),
		new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=template")')
	));
	$frmForm->addItem($buttons);
	$frmForm->addItem(new CVar('groupid', $_REQUEST['groupid'], 'filter_groupid_id'));

	$template_wdgt->addPageHeader(_('CONFIGURATION OF TEMPLATES'), $frmForm);

	$frmGroup = new CForm('get');
	$frmGroup->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB()));

	$template_wdgt->addHeader(_('Templates'), $frmGroup);
	$template_wdgt->addHeaderRowNumber();

	$form = new CForm();
	$form->setName('templates');

	$table = new CTableInfo(_('No templates defined.'));

	$table->setHeader(array(
		new CCheckBox('all_templates', NULL, "checkAll('".$form->getName()."', 'all_templates', 'templates');"),
		make_sorting_header(_('Templates'), 'name'),
		_('Applications'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Screens'),
		_('Discovery'),
		_('Linked templates'),
		_('Linked to')
	));


// get templates
	$templates = array();

	$sortfield = getPageSortField('name');
	$sortorder = getPageSortOrder();

	if ($pageFilter->groupsSelected) {
		$options = array(
			'editable' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);

		if ($pageFilter->groupid > 0) $options['groupids'] = $pageFilter->groupid;

		$templates = API::Template()->get($options);
	}

// sorting && paging
	order_result($templates, $sortfield, $sortorder);
	$paging = getPagingLine($templates);
//--------

	$options = array(
		'templateids' => zbx_objectValues($templates, 'templateid'),
		'editable' => 1,
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
		'nopermissions' => 1,
	);

	$templates = API::Template()->get($options);
	order_result($templates, $sortfield, $sortorder);
//-----

	foreach($templates as $template){
		$templates_output = array();
		if($template['proxy_hostid']){
			$proxy = get_host_by_hostid($template['proxy_hostid']);
			$templates_output[] = $proxy['host'].':';
		}
		$templates_output[] = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid'].url_param('groupid'));

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
		$discoveries = array(new CLink(_('Discovery'), 'host_discovery.php?&hostid='.$template['hostid']),
			' ('.$template['discoveries'].')');


		$i = 0;
		$linked_templates_output = array();
		order_result($template['parentTemplates'], 'name');
		foreach($template['parentTemplates'] as $linked_template){
			$i++;
			if($i > $config['max_in_table']){
				$linked_templates_output[] = '...';
				$linked_templates_output[] = '//empty element for array_pop';
				break;
			}

			$url = 'templates.php?form=update&templateid='.$linked_template['templateid'].url_param('groupid');
			$linked_templates_output[] = new CLink($linked_template['name'], $url, 'unknown');
			$linked_templates_output[] = ', ';
		}
		array_pop($linked_templates_output);


		$i = 0;
		$linked_to_output = array();
		$linked_to_objects = array();
		foreach($template['hosts'] as $h){
			$h['objectid'] = $h['hostid'];
			$linked_to_objects[] = $h;
		}
		foreach($template['templates'] as $h){
			$h['objectid'] = $h['templateid'];
			$linked_to_objects[] = $h;
		}

		order_result($linked_to_objects, 'name');
		foreach($linked_to_objects as $linked_to_host){
			if(++$i > $config['max_in_table']){
				$linked_to_output[] = '...';
				$linked_to_output[] = '//empty element for array_pop';
				break;
			}

			switch($linked_to_host['status']){
				case HOST_STATUS_NOT_MONITORED:
					$style = 'on';
					$url = 'hosts.php?form=update&hostid='.$linked_to_host['hostid'].'&groupid='.$_REQUEST['groupid'];
				break;
				case HOST_STATUS_TEMPLATE:
					$style = 'unknown';
					$url = 'templates.php?form=update&templateid='.$linked_to_host['hostid'];
				break;
				default:
					$style = null;
					$url = 'hosts.php?form=update&hostid='.$linked_to_host['hostid'].'&groupid='.$_REQUEST['groupid'];
				break;
			}

			$linked_to_output[] = new CLink($linked_to_host['name'], $url, $style);
			$linked_to_output[] = ', ';
		}
		array_pop($linked_to_output);


		$table->addRow(array(
			new CCheckBox('templates['.$template['templateid'].']', NULL, NULL, $template['templateid']),
			$templates_output,
			$applications,
			$items,
			$triggers,
			$graphs,
			$screens,
			$discoveries,
			(empty($linked_templates_output) ? '-' : new CCol($linked_templates_output, 'wraptext')),
			(empty($linked_to_output) ? '-' : new CCol($linked_to_output, 'wraptext'))
		));
	}

// GO{
	$goBox = new CComboBox('go');
	$goBox->addItem('export', _('Export selected'));

	$goOption = new CComboItem('delete', _('Delete selected'));
	$goOption->setAttribute('confirm', _('Delete selected templates?'));
	$goBox->addItem($goOption);

	$goOption = new CComboItem('delete_and_clear', _('Delete selected with linked elements'));
	$goOption->setAttribute('confirm', _('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)'));
	$goBox->addItem($goOption);

// goButton name is necessary!!!
	$goButton = new CSubmit('goButton', _('Go').' (0)');
	$goButton->setAttribute('id', 'goButton');

	zbx_add_post_js('chkbxRange.pageGoName = "templates";');

	$footer = get_table_header(array($goBox, $goButton));
// }GO

	$form->addItem(array($paging,$table,$paging,$footer));
	$template_wdgt->addItem($form);
}

$template_wdgt->show();


require_once dirname(__FILE__).'/include/page_footer.php';

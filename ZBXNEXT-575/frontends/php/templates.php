<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/screens.inc.php');
require_once('include/forms.inc.php');
require_once('include/ident.inc.php');

if(isset($_REQUEST['go']) && ($_REQUEST['go'] == 'export') && isset($_REQUEST['templates'])){
	$EXPORT_DATA = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_templates_export.xml';

	require_once('include/export.inc.php');
}
else{
	$EXPORT_DATA = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = 'S_TEMPLATES';
	$page['file'] = 'templates.php';
	$page['hist_arg'] = array('groupid');
}

include_once('include/page_header.php');
?>
<?php
//		VAR						TYPE		OPTIONAL FLAGS			VALIDATION	EXCEPTION
	$fields=array(
		'hosts'				=> array(T_ZBX_INT,	O_OPT,	P_SYS,		DB_ID, 		NULL),
		'groups'			=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID, 		NULL),
		'clear_templates'	=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID, 		NULL),
		'templates'			=> array(T_ZBX_STR, O_OPT,	NULL,		NULL,		NULL),
		'templateid'		=> array(T_ZBX_INT,	O_OPT,	P_SYS,		DB_ID,		'isset({form})&&({form}=="update")'),
		'template_name'		=> array(T_ZBX_STR,	O_OPT,	NOT_EMPTY,	NULL,		'isset({save})'),
		'groupid'			=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID,		NULL),
		'twb_groupid'		=> array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID,		NULL),
		'newgroup'			=> array(T_ZBX_STR, O_OPT,	NULL,		NULL,		NULL),

		'macros_rem'		=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'macros'			=> array(T_ZBX_STR, O_OPT, P_SYS,		NULL,	NULL),
		'macro_new'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	'isset({macro_add})'),
		'value_new'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	'isset({macro_add})'),
		'macro_add'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'macros_del'		=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

// actions
		'go'				=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),

//form
		'unlink'			=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),
		'unlink_and_clear'	=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),
		'save'				=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),
		'clone'				=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),
		'full_clone'		=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),
		'delete'			=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),
		'delete_and_clear'	=> array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	NULL,		NULL),
		'cancel'			=> array(T_ZBX_STR, O_OPT,	P_SYS,			NULL,		NULL),

// other
		'form'				=> array(T_ZBX_STR, O_OPT,	P_SYS,			NULL,		NULL),
		'form_refresh'		=> array(T_ZBX_STR, O_OPT,	NULL,			NULL,		NULL),

// Import
		'rules' =>				array(T_ZBX_STR, O_OPT,	null,			DB_ID,	null),
		'import' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null)
	);

// OUTER DATA
	check_fields($fields);
	validate_sort_and_sortorder('host', ZBX_SORT_UP);

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
?>
<?php
	$templateids = get_request('templates', array());

	if($EXPORT_DATA){
// SELECT TEMPLATES
		$params = array(
			'templateids' => $templateids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$templates = CTemplate::get($params);
		order_result($templates, 'host');

// SELECT HOST GROUPS
		$params = array(
			'hostids' => $templateids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$groups = CHostGroup::get($params);

// SELECT GRAPHS
		$params = array(
			'hostids' => $templateids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$graphs = CGraph::get($params);

// SELECT GRAPH ITEMS
		$graphids = zbx_objectValues($graphs, 'graphid');
		$params = array(
			'graphids' => $graphids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
			'expandData' => 1
		);
		$gitems = CGraphItem::get($params);

		foreach($gitems as $gnum => $gitem){
			$gitems[$gitem['gitemid']]['host_key_'] = $gitem['host'].':'.$gitem['key_'];
		}
// SELECT TEMPLATES
		$params = array(
			'hostids' => $templateids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$parentTemplates = CTemplate::get($params);

// SELECT MACROS
		$params = array(
			'hostids' => $templateids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$macros = CUserMacro::get($params);

// SELECT SCREENS
		$params = array(
			'templateids' => $templateids,
			'select_screenitems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'noInheritance' => true
		);
		$screens = CTemplateScreen::get($params);

		prepareScreenExport($screens);

// SELECT ITEMS
		$params = array(
			'hostids' => $templateids,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$items = CItem::get($params);

// SELECT APPLICATIONS
		$itemids = zbx_objectValues($items, 'itemid');
//sdii($itemids);
		$params = array(
			'itemids' => $itemids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$applications = Capplication::get($params);
//sdii($applications);

// SELECT TRIGGERS
		$params = array(
			'hostids' => $templateids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
			'select_dependencies' => API_OUTPUT_EXTEND,
			'expandData' => 1
		);
		$triggers = CTrigger::get($params);
		foreach($triggers as $tnum => $trigger){
			$triggers[$trigger['triggerid']]['expression'] = explode_exp($trigger['expression'], false);
		}

// SELECT TRIGGER DEPENDENCIES
		$dependencies = array();
		foreach($triggers as $tnum => $trigger){
			if(!empty($trigger['dependencies'])){
				if(!isset($dependencies[$trigger['triggerid']])) $dependencies[$trigger['triggerid']] = array();

				$dependencies[$trigger['triggerid']]['trigger'] = $trigger;
				$dependencies[$trigger['triggerid']]['depends_on'] = $trigger['dependencies'];
			}
		}

// we do custom fields for export
		foreach($dependencies as $triggerid => $dep_data){
			$dependencies[$triggerid]['trigger']['host_description'] = $triggers[$triggerid]['host'].':'.$triggers[$triggerid]['description'];
			foreach($dep_data['depends_on'] as $dep_triggerid => $dep_trigger){
				$dependencies[$triggerid]['depends_on'][$dep_triggerid]['host_description'] = $dep_trigger['host'].':'.$dep_trigger['description'];
			}
		}


		$data = array(
			'hosts' => $templates,
			'items' => $items,
			'items_applications' => $applications,
			'graphs' => $graphs,
			'graphs_items' => $gitems,
			'templates' => $parentTemplates,
			'macros' => $macros,
			'hosts_groups' => $groups,
			'triggers' => $triggers,
			'dependencies' => $dependencies,
			'screens' => $screens,
		);

		$xml = zbxXML::export($data);

		print($xml);
		exit();
	}

// IMPORT ///////////////////////////////////
	$rules = get_request('rules', array());
	if(!isset($_REQUEST['form_refresh'])){
		foreach(array('host', 'template', 'item', 'trigger', 'graph') as $key){
			$rules[$key]['exist'] = 1;
			$rules[$key]['missed'] = 1;
		}
	}

	if(isset($_FILES['import_file']) && is_file($_FILES['import_file']['tmp_name'])){
		require_once('include/export.inc.php');
		DBstart();
		$result = zbxXML::import($_FILES['import_file']['tmp_name']);
		if($result) $result = zbxXML::parseMain($rules);
		$result = DBend($result);
		show_messages($result, S_IMPORTED.SPACE.S_SUCCESSEFULLY_SMALL, S_IMPORT.SPACE.S_FAILED_SMALL);
	}
?>
<?php
/**********************************/
/* <<<--- TEMPLATE ACTIONS --->>> */
/**********************************/
// unlink, unlink_and_clear
	if((isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear']))){
		$_REQUEST['clear_templates'] = get_request('clear_templates', array());

		if(isset($_REQUEST['unlink'])){
			$unlink_templates = array_keys($_REQUEST['unlink']);
		}
		else{
			$unlink_templates = array_keys($_REQUEST['unlink_and_clear']);
			$_REQUEST['clear_templates'] = zbx_array_merge($_REQUEST['clear_templates'], $unlink_templates);
		}
		foreach($unlink_templates as $id) unset($_REQUEST['templates'][$id]);
	}
// clone
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['templateid'])){
		unset($_REQUEST['templateid']);
		unset($_REQUEST['hosts']);
		$_REQUEST['form'] = 'clone';
	}
// full_clone
	else if(isset($_REQUEST['full_clone']) && isset($_REQUEST['templateid'])){
		$_REQUEST['form'] = 'full_clone';
		$_REQUEST['hosts'] = array();
	}
// save
	else if(isset($_REQUEST['save'])){

		$macros = get_request('macros', array());
		$groups = get_request('groups', array());
		$hosts = get_request('hosts', array());
		$templates = get_request('templates', array());
		$templates_clear = get_request('clear_templates', array());
		$templateid = get_request('templateid', 0);
		$newgroup = get_request('newgroup', 0);
		$template_name = get_request('template_name', '');

		$result = true;

		if(!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY)))
			access_deny();

		$clone_templateid = false;
		if($_REQUEST['form'] == 'full_clone'){
			$clone_templateid = $templateid;
			$templateid = null;
		}

		foreach($macros as $mnum => $macro){
			if(zbx_empty($macro['value'])){
				unset($macros[$mnum]);
				continue;
			}

			if($macro['new'] == 'create') unset($macros[$mnum]['macroid']);
			unset($macros[$mnum]['new']);
		}

		DBstart();

// CREATE NEW GROUP
		$groups = zbx_toObject($groups, 'groupid');
		if(!empty($newgroup)){
			$result = CHostGroup::create(array('name' => $newgroup));
			$options = array(
				'groupids' => $result['groupids'],
				'output' => API_OUTPUT_EXTEND
			);
			$newgroup = CHostGroup::get($options);
			if($newgroup){
				$groups = array_merge($groups, $newgroup);
			}
			else{
				$result = false;
			}
		}

		$templates = array_keys($templates);
		$templates = zbx_toObject($templates, 'templateid');
		$templates_clear = zbx_toObject($templates_clear, 'templateid');

		$hosts = zbx_toObject($hosts, 'hostid');

		$template = array(
			'host' => $template_name,
			'groups' => $groups,
			'templates' => $templates,
			'hosts' => $hosts,
			'macros' => $macros
		);

// CREATE/UPDATE TEMPLATE {{{
		if($templateid){
			$created = 0;
			$template['templateid'] = $templateid;
			$template['templates_clear'] = $templates_clear;

			$result = CTemplate::update($template);
			if(!$result){
				error(CTemplate::resetErrors());
				$result = false;
			}

			$msg_ok = S_TEMPLATE_UPDATED;
			$msg_fail = S_CANNOT_UPDATE_TEMPLATE;
		}
		else{
			$created = 1;
			$result = CTemplate::create($template);

			if($result){
				$templateid = reset($result['templateids']);
			}
			else{
				error(CTemplate::resetErrors());
				$result = false;
			}
			$msg_ok = S_TEMPLATE_ADDED;
			$msg_fail = S_CANNOT_ADD_TEMPLATE;
		}
// }}} CREATE/UPDATE TEMPLATE

// FULL_CLONE {{{
		if(!zbx_empty($templateid) && $templateid && $clone_templateid && ($_REQUEST['form'] == 'full_clone')){

			if(!copy_applications($clone_templateid, $templateid)) $result = false;

			if(!copyItems($clone_templateid, $templateid)) $result = false;

			if(!copy_triggers($clone_templateid, $templateid)) $result = false;

// Host graphs
			$options = array(
				'hostids' => $clone_templateid,
				'inherited' => 0,
				'output' => API_OUTPUT_REFER
			);
			$db_graphs = CGraph::get($options);
			foreach($db_graphs as $gnum => $db_graph){
				$result &= (bool) copy_graph_to_host($db_graph['graphid'], $templateid);
			}
		}
// }}} FULL_CLONE

		$result = DBend($result);

		show_messages($result, $msg_ok, $msg_fail);

		if($result){
			if($created){
				add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TEMPLATE, $templateid, $template_name, 'hosts', NULL, NULL);
			}
			unset($_REQUEST['form']);
			unset($_REQUEST['templateid']);
		}
		unset($_REQUEST['save']);
	}
// delete, delete_and_clear
	else if((isset($_REQUEST['delete']) || isset($_REQUEST['delete_and_clear'])) && isset($_REQUEST['templateid'])){
		DBstart();

		$go_result = true;
		if(isset($_REQUEST['delete'])){
			$result = CTemplate::massUpdate(array(
				'templates' => zbx_toObject($_REQUEST['templateid'], 'templateid'),
				'hosts' => array()
			));
		}
		if($result)
			$result = CTemplate::delete($_REQUEST['templateid']);

		$result = DBend($result);

		show_messages($result, S_TEMPLATE_DELETED, S_CANNOT_DELETE_TEMPLATE);
		if($result){
/*				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,'Host ['.$host['host'].']');*/
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
		if(isset($_REQUEST['delete'])){
			$go_result = CTemplate::massUpdate(array(
				'templateids' => $templates,
				'hosts' => array()
			));
		}
		if($go_result)
			$go_result = CTemplate::delete($templates);

		$go_result = DBend($go_result);

		show_messages($go_result, S_TEMPLATE_DELETED, S_CANNOT_DELETE_TEMPLATE);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php
	$frmForm = new CForm();
	if(!isset($_REQUEST['form'])){
		$frmForm->cleanItems();
		$buttons = new CDiv(array(
			new CSubmit('form', S_CREATE),
			new CSubmit('form', S_IMPORT)
		));
		$buttons->useJQueryStyle();
		$frmForm->addItem($buttons);
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
?>
<?php
	if(isset($_REQUEST['form'])){
		if($_REQUEST['form'] == S_IMPORT){
			$template_wdgt->addItem(import_host_form(true));
		}
		else{
			$template_wdgt->addItem(get_header_host_table(get_request('templateid',0), 'template'));

			$templateForm = new CGetForm('template.edit');
			$template_wdgt->addItem($templateForm->render());
		}
	}
	else{
// TABLE WITH TEMPLATES

		$frmGroup = new CForm('get');
		$frmGroup->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB()));

// table header
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$template_wdgt->addHeader(S_CONFIGURATION_OF_TEMPLATES, $frmGroup);
		$template_wdgt->addHeader($numrows, $frmForm);
//------

		$form = new CForm();
		$form->setName('templates');

		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);

		$table->setHeader(array(
			new CCheckBox('all_templates', NULL, "checkAll('".$form->getName()."', 'all_templates', 'templates');"),
			make_sorting_header(S_TEMPLATES, 'host'),
			S_APPLICATIONS,
			S_ITEMS,
			S_TRIGGERS,
			S_GRAPHS,
			S_SCREENS,
			S_DISCOVERY,
			S_LINKED_TEMPLATES,
			S_LINKED_TO
		));


// get templates
		$templates = array();

		$sortfield = getPageSortField('host');
		$sortorder = getPageSortOrder();

		if($pageFilter->groupsSelected){
			$options = array(
				'editable' => 1,
				'sortfield' => $sortfield,
				'sortorder' => $sortorder,
				'limit' => ($config['search_limit']+1)
			);

			if($pageFilter->groupid > 0) $options['groupids'] = $pageFilter->groupid;

			$templates = CTemplate::get($options);
		}

// sorting && paging
		order_result($templates, $sortfield, $sortorder);
		$paging = getPagingLine($templates);
//--------

		$options = array(
			'templateids' => zbx_objectValues($templates, 'templateid'),
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('hostid','host','status'),
			'select_templates' => array('hostid','host','status'),
			'selectParentTemplates' => array('hostid','host','status'),
			'selectItems' => API_OUTPUT_COUNT,
			'select_triggers' => API_OUTPUT_COUNT,
			'select_graphs' => API_OUTPUT_COUNT,
			'select_applications' => API_OUTPUT_COUNT,
			'selectDiscoveries' => API_OUTPUT_COUNT,
			'selectScreens' => API_OUTPUT_COUNT,
			'nopermissions' => 1,
		);

		$templates = CTemplate::get($options);
		order_result($templates, $sortfield, $sortorder);
//-----

		foreach($templates as $tnum => $template){
			$templates_output = array();
			if($template['proxy_hostid']){
				$proxy = get_host_by_hostid($template['proxy_hostid']);
				$templates_output[] = $proxy['host'].':';
			}
			$templates_output[] = new CLink($template['host'], 'templates.php?form=update&templateid='.$template['templateid'].url_param('groupid'));

			$applications = array(new CLink(S_APPLICATIONS,'applications.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
				' ('.$template['applications'].')');
			$items = array(new CLink(S_ITEMS,'items.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
				' ('.$template['items'].')');
			$triggers = array(new CLink(S_TRIGGERS,'triggers.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
				' ('.$template['triggers'].')');
			$graphs = array(new CLink(S_GRAPHS,'graphs.php?groupid='.$_REQUEST['groupid'].'&hostid='.$template['templateid']),
				' ('.$template['graphs'].')');
			$screens = array(new CLink(S_SCREENS,'screenconf.php?templateid='.$template['templateid']),
				' ('.$template['screens'].')');
			$discoveries = array(new CLink(S_DISCOVERY, 'host_discovery.php?&hostid='.$template['hostid']),
				' ('.$template['discoveries'].')');


			$i = 0;
			$linked_templates_output = array();
			order_result($template['parentTemplates'], 'host');
			foreach($template['parentTemplates'] as $snum => $linked_template){
				$i++;
				if($i > $config['max_in_table']){
					$linked_templates_output[] = '...';
					$linked_templates_output[] = '//empty element for array_pop';
					break;
				}

				$url = 'templates.php?form=update&templateid='.$linked_template['templateid'].url_param('groupid');
				$linked_templates_output[] = new CLink($linked_template['host'], $url, 'unknown');
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

			order_result($linked_to_objects, 'host');
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

				$linked_to_output[] = new CLink($linked_to_host['host'], $url, $style);
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
				(empty($linked_templates_output) ? '-' : new CCol($linked_templates_output,'wraptext')),
				(empty($linked_to_output) ? '-' : new CCol($linked_to_output,'wraptext'))
			));
		}

// GO{
		$goBox = new CComboBox('go');
		$goBox->addItem('export', S_EXPORT_SELECTED);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_TEMPLATES_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete_and_clear',S_DELETE_SELECTED_WITH_LINKED_ELEMENTS);
		$goOption->setAttribute('confirm',S_WARNING_THIS_DELETE_TEMPLATES_AND_CLEAR);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CSubmit('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "templates";');

		$footer = get_table_header(array($goBox, $goButton));
// }GO

		$form->addItem(array($paging,$table,$paging,$footer));
		$template_wdgt->addItem($form);
	}

	$template_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>

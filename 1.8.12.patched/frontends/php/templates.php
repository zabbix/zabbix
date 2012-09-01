<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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

// SELECT ITEMS
		$params = array(
			'hostids' => $templateids,
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
			$triggers[$trigger['triggerid']]['expression'] = explode_exp($trigger['expression']);
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
			'dependencies' => $dependencies
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
	/**
	 * Unlink, unlink_and_clear
	 */
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
	/**
	 * Clone
	 */
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['templateid'])){
		unset($_REQUEST['templateid']);
		$_REQUEST['form'] = 'clone';
	}
	/**
	 * Full_clone
	 */
	else if(isset($_REQUEST['full_clone']) && isset($_REQUEST['templateid'])){
		$_REQUEST['form'] = 'full_clone';
		$_REQUEST['hosts'] = array();
	}
	/**
	 * Save
	 */
	else if(isset($_REQUEST['save'])){
		$groups = get_request('groups', array());
		$hosts = get_request('hosts', array());
		$templates = get_request('templates', array());
		$templates_clear = get_request('clear_templates', array());
		$templateid = get_request('templateid', 0);
		$template_name = get_request('template_name', '');

		if(!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY)))
			access_deny();

		$clone_templateid = false;
		if($_REQUEST['form'] == 'full_clone'){
			$clone_templateid = $templateid;
			$templateid = null;
		}

		if($templateid){
			$msg_ok = S_TEMPLATE_UPDATED;
			$msg_fail = S_CANNOT_UPDATE_TEMPLATE;
		}
		else{
			$msg_ok = S_TEMPLATE_ADDED;
			$msg_fail = S_CANNOT_ADD_TEMPLATE;
		}

		try{
			DBstart();

			// create new group
			if(!zbx_empty($_REQUEST['newgroup'])){
				$newGroup = CHostGroup::create(array('name' => $_REQUEST['newgroup']));

				if(!$newGroup){
					throw new Exception();
				}
				$groups[] = reset($newGroup['groupids']);
			}
			$groups = zbx_toObject($groups, 'groupid');

			$templates = array_keys($templates);
			$templates = zbx_toObject($templates, 'templateid');
			$templates_clear = zbx_toObject($templates_clear, 'templateid');

			$hosts = zbx_toObject($hosts, 'hostid');

			$macros = get_request('macros', array());
			foreach($macros as $mnum => $macro){
				if (zbx_empty($macro['value']) && zbx_empty($macro['macro'])) {
					unset($macros[$mnum]);
				}
			}

			$duplicatedMacros = array();
			foreach ($macros as $mnum => $macro) {
				// transform macros to uppercase {$aaa} => {$AAA}
				$macros[$mnum]['macro'] = zbx_strtoupper($macro['macro']);

				// search for duplicates items in new macros array
				foreach ($macros as $duplicateNumber => $duplicateNewMacro) {
					if ($mnum != $duplicateNumber && $macro['macro'] == $duplicateNewMacro['macro']) {
						$duplicatedMacros[] = '"'.$duplicateNewMacro['macro'].'"';
					}
				}
			}

			// validate duplicates macros
			if (!empty($duplicatedMacros)) {
				error(S_DUPLICATED_MACRO_FOUND.SPACE.implode(', ', array_unique($duplicatedMacros)));
				throw new Exception();
			}

			$template = array(
				'host' => $template_name,
				'groups' => $groups,
				'templates' => $templates,
				'hosts' => $hosts,
				'macros' => $macros
			);

			// CREATE/UPDATE TEMPLATE
			if($templateid){
				$created = 0;
				$template['templateid'] = $templateid;
				$template['templates_clear'] = $templates_clear;

				if(!CTemplate::update($template)){
					error(CTemplate::resetErrors());
					throw new Exception();
				}
			}
			else{
				$created = 1;
				$result = CTemplate::create($template);
				if($result){
					$templateid = reset($result['templateids']);
				}
				else{
					error(CTemplate::resetErrors());
					throw new Exception();
				}
			}

			// FULL_CLONE
			if(!zbx_empty($templateid) && $templateid && $clone_templateid && ($_REQUEST['form'] == 'full_clone')){
				// Host applications
				$sql = 'SELECT * FROM applications WHERE hostid='.$clone_templateid.' AND templateid=0';
				$res = DBselect($sql);
				while($db_app = DBfetch($res)){
					add_application($db_app['name'], $templateid, 0);
				}

				// Host items
				$sql = 'SELECT DISTINCT i.itemid, i.description '.
						' FROM items i '.
						' WHERE i.hostid='.$clone_templateid.
							' AND i.templateid=0 '.
						' ORDER BY i.description';
				$res = DBselect($sql);
				$result = true;
				while($db_item = DBfetch($res)){
					$result &= (bool) copy_item_to_host($db_item['itemid'], $templateid, true);
				}
				if(!$result) throw new Exception();

				// Host triggers
				if(!copy_triggers($clone_templateid, $templateid)) throw new Exception();

				// Host graphs
				$options = array(
					'hostids' => $clone_templateid,
					'inherited' => 0,
					'output' => API_OUTPUT_REFER
				);
				$db_graphs = CGraph::get($options);
				$result = true;
				foreach($db_graphs as $gnum => $db_graph){
					$result &= (bool) copy_graph_to_host($db_graph['graphid'], $templateid, true);
				}
				if(!$result) throw new Exception();
			}

			if(!DBend(true)){
				throw new Exception();
			}

			show_messages(true, $msg_ok, $msg_fail);

			if($created){
				add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TEMPLATE, $templateid, $template_name, 'hosts', NULL, NULL);
			}
			unset($_REQUEST['form']);
			unset($_REQUEST['templateid']);

		}
		catch(Exception $e){
			DBend(false);
			show_messages(false, $msg_ok, $msg_fail);
		}

		unset($_REQUEST['save']);
	}
	/**
	 * Delete, delete and clear
	 */
	else if((isset($_REQUEST['delete']) || isset($_REQUEST['delete_and_clear'])) && isset($_REQUEST['templateid'])){
		$unlink_mode = false;
		if(isset($_REQUEST['delete'])){
			$unlink_mode =  true;
		}

		//$host = get_host_by_hostid($_REQUEST['templateid']);

		DBstart();
		$result = delete_host($_REQUEST['templateid'], $unlink_mode);
		$result = DBend($result);

		show_messages($result, S_TEMPLATE_DELETED, S_CANNOT_DELETE_TEMPLATE);
		if($result){
		/*	add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,'Host ['.$host['host'].']');*/
			unset($_REQUEST['form']);
			unset($_REQUEST['templateid']);
		}
		unset($_REQUEST['delete']);
	}
	/**
	 * Go: delete, delete and clear
	 */
	else if(str_in_array($_REQUEST['go'], array('delete', 'delete_and_clear')) && isset($_REQUEST['templates'])){
		$unlink_mode = false;
		if($_REQUEST['go'] == 'delete'){
			$unlink_mode = true;
		}

		DBstart();
		$go_result = true;
		$templates = get_request('templates', array());
		$del_hosts = CTemplate::get(array('templateids' => $templates, 'editable' => 1));
		$del_hosts = zbx_objectValues($del_hosts, 'templateid');


		$go_result = delete_host($del_hosts, $unlink_mode);
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
		$frmForm->addItem(new CButton('form', S_CREATE_TEMPLATE));
		$frmForm->addItem(new CButton('form', S_IMPORT_TEMPLATE));
	}

	$template_wdgt = new CWidget();
	$template_wdgt->addPageHeader(S_CONFIGURATION_OF_TEMPLATES, $frmForm);

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
		if($_REQUEST['form'] == S_IMPORT_TEMPLATE){
			$template_wdgt->addItem(import_host_form(true));
		}
		else{

			$templateid = get_request('templateid', 0);
			$template_name = get_request('template_name', '');
			$newgroup = get_request('newgroup', '');
			$templates = get_request('templates', array());
			$clear_templates = get_request('clear_templates', array());

			$frm_title = S_TEMPLATE;

			if($templateid > 0){
				$db_host = get_host_by_hostid($templateid);
				$template_name = $db_host['host'];
				$frm_title .= SPACE.' ['.$template_name.']';

				$original_templates = get_templates_by_hostid($templateid);
			}
			else{
				$original_templates = array();
			}

			$frmHost = new CForm('templates.php');
			$frmHost->setName('tpl_for');

			$frmHost->addVar('form', get_request('form', 1));
			$from_rfr = get_request('form_refresh', 0);
			$frmHost->addVar('form_refresh', $from_rfr+1);
			$frmHost->addVar('clear_templates', $clear_templates);
			$frmHost->addVar('groupid', $_REQUEST['groupid']);

			if($templateid){
				$frmHost->addVar('templateid', $templateid);
			}

			if(($templateid > 0) && !isset($_REQUEST['form_refresh'])){
				// get template groups from db
				$options = array(
					'hostids' => $templateid,
					'editable' => 1
				);
				$groups = CHostGroup::get($options);
				$groups = zbx_objectValues($groups, 'groupid');

				// get template hosts from db
				$params = array(
					'templateids' => $templateid,
					'editable' => 1,
					'templated_hosts' => 1
				);
				$hosts_linked_to = CHost::get($params);
				$hosts_linked_to = zbx_objectValues($hosts_linked_to, 'hostid');
				$hosts_linked_to = zbx_toHash($hosts_linked_to, 'hostid');
				$templates = $original_templates;
			}
			else{
				$groups = get_request('groups', array());
				if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0) && !uint_in_array($_REQUEST['groupid'], $groups)){
					array_push($groups, $_REQUEST['groupid']);
				}
				$hosts_linked_to = get_request('hosts', array());
			}

			$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
			$clear_templates = array_diff($clear_templates, array_keys($templates));
			natcasesort($templates);
			$frmHost->addVar('clear_templates', $clear_templates);

// TEMPLATE WIDGET {
			$template_tbl = new CTable('', 'tablestripped');
			$template_tbl->setOddRowClass('form_odd_row');
			$template_tbl->setEvenRowClass('form_even_row');
// FORM ITEM : Template name text box [  ]
			$template_tbl->addRow(array(S_NAME, new CTextBox('template_name', $template_name, 54)));

// FORM ITEM : Groups tween box [  ] [  ]
// get all Groups
			$group_tb = new CTweenBox($frmHost, 'groups', $groups, 10);
			$options = array('editable' => 1, 'extendoutput' => 1);
			$all_groups = CHostGroup::get($options);
			order_result($all_groups, 'name');

			foreach($all_groups as $gnum => $group){
				$group_tb->addItem($group['groupid'], $group['name']);
			}
			$template_tbl->addRow(array(S_GROUPS, $group_tb->get(S_IN.SPACE.S_GROUPS,S_OTHER.SPACE.S_GROUPS)));


// FORM ITEM : new group text box [  ]
			$template_tbl->addRow(array(S_NEW_GROUP, new CTextBox('newgroup', $newgroup)));

// FORM ITEM : linked Hosts tween box [  ] [  ]
			// $options = array('editable' => 1, 'extendoutput' => 1);
			// $twb_groups = CHostGroup::get($options);
			$twb_groupid = get_request('twb_groupid', 0);
			if($twb_groupid == 0){
				$gr = reset($all_groups);
				$twb_groupid = $gr['groupid'];
			}
			$cmbGroups = new CComboBox('twb_groupid', $twb_groupid, 'submit()');
			foreach($all_groups as $gnum => $group){
				$cmbGroups->addItem($group['groupid'], $group['name']);
			}

			$host_tb = new CTweenBox($frmHost, 'hosts', $hosts_linked_to, 25);

// get hosts from selected twb_groupid combo
			$params = array(
				'groupids' => $twb_groupid,
				'templated_hosts' => 1,
				'editable' => 1,
				'extendoutput' => 1);
			$db_hosts = CHost::get($params);
			order_result($db_hosts, 'host');

			foreach($db_hosts as $hnum => $db_host){
				if(isset($hosts_linked_to[$db_host['hostid']])) continue;// add all except selected hosts
				$host_tb->addItem($db_host['hostid'], $db_host['host']);
			}

// select selected hosts and add them
			$params = array(
				'hostids' => $hosts_linked_to,
				'templated_hosts' => 1,
				'editable' => 1,
				'extendoutput' => 1);
			$db_hosts = CHost::get($params);
			order_result($db_hosts, 'host');
			foreach($db_hosts as $hnum => $db_host){
				$host_tb->addItem($db_host['hostid'], $db_host['host']);
			}

			$template_tbl->addRow(array(S_HOSTS.'|'.S_TEMPLATES, $host_tb->Get(S_IN, array(S_OTHER.SPACE.'|'.SPACE.S_GROUP.SPACE,$cmbGroups))));

// FORM ITEM : linked Template table
			$tpl_table = new CTable();
			$tpl_table->setCellPadding(0);
			$tpl_table->setCellSpacing(0);
			foreach($templates as $tid => $tname){
				$frmHost->addVar('templates['.$tid.']', $tname);
				$tpl_table->addRow(array(
					$tname,
					new CButton('unlink['.$tid.']', S_UNLINK),
					isset($original_templates[$tid]) ? new CButton('unlink_and_clear['.$tid.']', S_UNLINK_AND_CLEAR) : SPACE
				));
			}

			$template_tbl->addRow(array(S_LINK_WITH_TEMPLATE, array(
				$tpl_table,
				new CButton('add_template', S_ADD,
					'return PopUp("popup.php?dstfrm='.$frmHost->GetName().
					'&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host&excludeids['.$templateid.']='.$templateid.
					url_param($templates,false,"existed_templates").'",450,450)', 'T')
			)));

// FULL CLONE {
			if($_REQUEST['form'] == 'full_clone'){
// FORM ITEM : Template items
				$items_lbx = new CListBox('items', null, 8);
				$items_lbx->setAttribute('disabled', 'disabled');

				$options = array(
					'editable' => 1,
					'hostids' => $templateid,
					'output' => API_OUTPUT_EXTEND
				);
				$template_items = CItem::get($options);

				if(empty($template_items)){
					$items_lbx->setAttribute('style', 'width: 200px;');
				}
				else{
					foreach($template_items as $inum => $titem){
						$item_description = item_description($titem);
						$items_lbx->addItem($titem['itemid'], $item_description);
					}
				}
				$template_tbl->addRow(array(S_ITEMS, $items_lbx));


// FORM ITEM : Template triggers
				$trig_lbx = new CListBox('triggers', null, 8);
				$trig_lbx->setAttribute('disabled', 'disabled');

				$options = array('editable' => 1, 'hostids' => $templateid, 'extendoutput' => 1);
				$template_triggers = CTrigger::get($options);

				if(empty($template_triggers)){
					$trig_lbx->setAttribute('style','width: 200px;');
				}
				else{
					foreach($template_triggers as $tnum => $ttrigger){
						$trigger_description = expand_trigger_description($ttrigger['triggerid']);
						$trig_lbx->addItem($ttrigger['triggerid'], $trigger_description);
					}
				}
				$template_tbl->addRow(array(S_TRIGGERS, $trig_lbx));


// FORM ITEM : Host graphs
				$graphs_lbx = new CListBox('graphs', null, 8);
				$graphs_lbx->setAttribute('disabled', 'disabled');

				$options = array('editable' => 1, 'hostids' => $templateid, 'extendoutput' => 1);
				$template_graphs = CGraph::get($options);

				if(empty($template_graphs)){
					$graphs_lbx->setAttribute('style','width: 200px;');
				}
				else{
					foreach($template_graphs as $tnum => $tgraph){
						$graphs_lbx->addItem($tgraph['graphid'], $tgraph['name']);
					}
				}
				$template_tbl->addRow(array(S_GRAPHS, $graphs_lbx));
			}
// FULL CLONE }

			$host_footer = array();
			$host_footer[] = new CButton('save', S_SAVE);
			if(($templateid > 0) && ($_REQUEST['form'] != 'full_clone')){
				$host_footer[] = SPACE;
				$host_footer[] = new CButton('clone', S_CLONE);
				$host_footer[] = SPACE;
				$host_footer[] = new CButton('full_clone', S_FULL_CLONE);
				$host_footer[] = SPACE;
				$host_footer[] = new CButtonDelete(S_DELETE_TEMPLATE_Q, url_param('form').url_param('templateid').url_param('groupid'));
				$host_footer[] = SPACE;
				$host_footer[] = new CButtonQMessage('delete_and_clear', S_DELETE_AND_CLEAR, S_DELETE_AND_CLEAR_TEMPLATE_Q, url_param('form').
					url_param('templateid').url_param('groupid'));
			}
			array_push($host_footer, SPACE, new CButtonCancel(url_param('groupid')));

			$host_footer = new CCol($host_footer);
			$host_footer->setColSpan(2);
			$template_tbl->setFooter($host_footer);

			$tplForm_wdgt = new CWidget();
			$tplForm_wdgt->setClass('header');
			$tplForm_wdgt->addHeader($frm_title);
			$tplForm_wdgt->addItem($template_tbl);
// } TEMPLATE WIDGET


// MACROS WIDGET {
			$macros_wdgt = get_macros_widget($templateid);
// } MACROS WIDGET

			$left_table = new CTable();
			$left_table->setCellPadding(4);
			$left_table->setCellSpacing(4);
			$left_table->addRow($tplForm_wdgt);

			$right_table = new CTable();
			$right_table->setCellPadding(4);
			$right_table->setCellSpacing(4);
			$right_table->addRow($macros_wdgt);

			$td_l = new CCol($left_table);
			$td_l->setAttribute('valign','top');
			$td_r = new CCol($right_table);
			$td_r->setAttribute('valign','top');

			$outer_table = new CTable();
			$outer_table->addRow(array($td_l, $td_r));

			$frmHost->addItem($outer_table);

			$template_wdgt->addItem($frmHost);
		}
	}
	else{
// TABLE WITH TEMPLATES

		$frmForm = new CForm(null, 'get');
		$frmForm->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB()));

// table header
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$template_wdgt->addHeader(S_TEMPLATES_BIG, $frmForm);
		$template_wdgt->addHeader($numrows);
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
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => array('hostid','host','status'),
			'select_templates' => array('hostid','host','status'),
			'selectParentTemplates' => array('hostid','host','status'),
			'select_items' => API_OUTPUT_COUNT,
			'select_triggers' => API_OUTPUT_COUNT,
			'select_graphs' => API_OUTPUT_COUNT,
			'select_applications' => API_OUTPUT_COUNT,
			'nopermissions' => 1
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
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "templates";');

		$footer = get_table_header(new CCol(array($goBox, $goButton)));
// }GO

// PAGING FOOTER
		$table = array($paging,$table,$paging,$footer);
//---------

		$form->addItem($table);
		$template_wdgt->addItem($form);
	}

	$template_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>

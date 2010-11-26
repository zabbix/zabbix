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
// include JS + templates
	include('include/templates/macros.js.php');
?>
<?php
	$divTabs = new CTabView(array('remember'=>1));
	if(!isset($_REQUEST['form_refresh']))
		$divTabs->setSelected(0);


	$templateid = get_request('templateid', 0);
	$host = get_request('template_name', '');
	$newgroup = get_request('newgroup', '');
	$templates = get_request('templates', array());
	$clear_templates = get_request('clear_templates', array());
	$macros = get_request('macros',array());

	$frm_title = S_TEMPLATE;

	if($templateid > 0){
		$dbTemplates = CTemplate::get(array(
			'templateids' => $templateid,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'selectMacros' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		));
		$dbTemplate = reset($dbTemplates);

		$frm_title .= SPACE.' ['.$dbTemplate['host'].']';

		$original_templates = array();
		foreach($dbTemplate['parentTemplates'] as $tnum => $tpl){
			$original_templates[$tpl['templateid']] = $tpl['host'];
		}
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
		$host = $dbTemplate['host'];

// get template groups from db
		$groups = $dbTemplate['groups'];
		$groups = zbx_objectValues($groups, 'groupid');

		$macros = $dbTemplate['macros'];

// get template hosts from db
		$hosts_linked_to = CHost::get(array(
			'templateids' => $templateid,
			'editable' => 1,
			'templated_hosts' => 1
		));

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
	$templateList = new CFormList('hostlist');

// FORM ITEM : Template name text box [  ]
	$templateList->addRow(S_NAME, new CTextBox('template_name', $host, 54));

// FORM ITEM : Groups tween box [  ] [  ]
// get all Groups
	$group_tb = new CTweenBox($frmHost, 'groups', $groups, 10);
	$options = array('editable' => 1, 'output' => API_OUTPUT_EXTEND);
	$all_groups = CHostGroup::get($options);
	order_result($all_groups, 'name');

	foreach($all_groups as $gnum => $group){
		$group_tb->addItem($group['groupid'], $group['name']);
	}
	$templateList->addRow(S_GROUPS, $group_tb->get(S_IN.SPACE.S_GROUPS,S_OTHER.SPACE.S_GROUPS));


// FORM ITEM : new group text box [  ]
	$templateList->addRow(array(S_NEW_GROUP, BR(), new CTextBox('newgroup', $newgroup)));

// FORM ITEM : linked Hosts tween box [  ] [  ]
	$twb_groupid = get_request('twb_groupid', 0);
	if($twb_groupid == 0){
		$gr = reset($all_groups);
		$twb_groupid = $gr['groupid'];
	}
	$cmbGroups = new CComboBox('twb_groupid', $twb_groupid, 'submit()');
	foreach($all_groups as $gnum => $group){
		$cmbGroups->addItem($group['groupid'], $group['name']);
	}

	$host_tb = new CTweenBox($frmHost, 'hosts', $hosts_linked_to, 20);

// get hosts from selected twb_groupid combo
	$params = array(
		'groupids' => $twb_groupid,
		'templated_hosts' => 1,
		'editable' => 1,
		'output' => API_OUTPUT_EXTEND
	);
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
		'output' => API_OUTPUT_EXTEND
	);
	$db_hosts = CHost::get($params);
	order_result($db_hosts, 'host');
	foreach($db_hosts as $hnum => $db_host){
		$host_tb->addItem($db_host['hostid'], $db_host['host']);
	}

	$templateList->addRow(S_HOSTS.' / '.S_TEMPLATES, $host_tb->Get(S_IN, array(S_OTHER.SPACE.'|'.SPACE.S_GROUP.SPACE,$cmbGroups)));

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
		$templateList->addRow(S_ITEMS, $items_lbx);


// FORM ITEM : Template triggers
		$trig_lbx = new CListBox('triggers', null, 8);
		$trig_lbx->setAttribute('disabled', 'disabled');

		$template_triggers = CTrigger::get(array(
			'hostids' => $templateid,
			'editable' => true, 
			'output' => API_OUTPUT_EXTEND
		));

		if(empty($template_triggers)){
			$trig_lbx->setAttribute('style','width: 200px;');
		}
		else{
			foreach($template_triggers as $tnum => $ttrigger){
				$trigger_description = expand_trigger_description($ttrigger['triggerid']);
				$trig_lbx->addItem($ttrigger['triggerid'], $trigger_description);
			}
		}
		$templateList->addRow(S_TRIGGERS, $trig_lbx);


// FORM ITEM : Host graphs
		$graphs_lbx = new CListBox('graphs', null, 8);
		$graphs_lbx->setAttribute('disabled', 'disabled');

		$options = array('editable' => 1, 'hostids' => $templateid, 'output' => API_OUTPUT_EXTEND);
		$template_graphs = CGraph::get($options);

		if(empty($template_graphs)){
			$graphs_lbx->setAttribute('style','width: 200px;');
		}
		else{
			foreach($template_graphs as $tnum => $tgraph){
				$graphs_lbx->addItem($tgraph['graphid'], $tgraph['name']);
			}
		}
		$templateList->addRow(S_GRAPHS, $graphs_lbx);
	}

	$divTabs->addTab('templateTab', S_TEMPLATE, $templateList);
// FULL CLONE }

// } TEMPLATE WIDGET

// TEMPLATES{
	$tmplList = new CFormList('tmpllist');
	foreach($templates as $tid => $temp_name){
		$frmHost->addVar('templates['.$tid.']', $temp_name);
		$tmplList->addRow($temp_name, array(
				new CSubmit('unlink['.$tid.']', S_UNLINK, null, 'link_menu'),
				SPACE, SPACE,
				isset($original_templates[$tid]) ? new CSubmit('unlink_and_clear['.$tid.']', S_UNLINK_AND_CLEAR, null, 'link_menu') : SPACE
		));
	}

	$tmplAdd = new CButton('add', S_ADD, 'return PopUp("popup.php?dstfrm='.$frmHost->getName().
			'&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host&excludeids['.$templateid.']='.$templateid.
			url_param($templates,false,"existed_templates").'",450,450)',
			'link_menu');

	$tmplList->addRow($tmplAdd, SPACE);

	$divTabs->addTab('tmplTab', S_LINKED_TEMPLATES, $tmplList);
// } TEMPLATES

// MACROS WIDGET {
// macros
	if(empty($macros)){
		$macros = array(array(
			'macro' => '',
			'value' => ''
		));
	}

	$macroTab = new CTable();
	$macroTab->addRow(array(S_MACRO, SPACE, S_VALUE));
	$macroTab->setAttribute('id', 'userMacros');

	$jsInsert = '';
	foreach($macros as $inum => $macro){
		if(!empty($jsInsert) && zbx_empty($macro['macro']) && zbx_empty($macro['value'])) continue;

		$jsInsert.= 'addMacroRow('.zbx_jsvalue($macro).');';
	}
	zbx_add_post_js($jsInsert);

	$addButton = new CButton('add', S_ADD, 'javascript: addMacroRow({});');
	$addButton->setAttribute('class', 'link_menu');

	$col = new CCol(array($addButton));
	$col->setAttribute('colspan', 4);

	$buttonRow = new CRow($col);
	$buttonRow->setAttribute('id', 'userMacroFooter');

	$macroTab->addRow($buttonRow);

	$macrolist = new CFormList('macrolist');
	$macrolist->addRow($macroTab);

	$divTabs->addTab('macroTab', S_MACROS, $macrolist);
// } MACROS WIDGET

	$frmHost->addItem($divTabs);

// Footer
	$host_footer = new CDiv();
	$host_footer->addItem(new CSubmit('save', S_SAVE));

	if(($templateid > 0) && ($_REQUEST['form'] != 'full_clone')){
		$host_footer->addItem(new CSubmit('clone', S_CLONE));
		$host_footer->addItem(new CSubmit('full_clone', S_FULL_CLONE));
		$host_footer->addItem(new CButtonDelete(S_DELETE_TEMPLATE_Q,  url_param('form').url_param('templateid').url_param('groupid')));
		$host_footer->addItem(new CButtonQMessage('delete_and_clear', S_DELETE_AND_CLEAR, S_DELETE_AND_CLEAR_TEMPLATE_Q, url_param('form').url_param('templateid').url_param('groupid')));
	}
	$host_footer->addItem(new CButtonCancel(url_param('groupid')));
	$host_footer->useJQueryStyle();
	$frmHost->addItem(new CDiv($host_footer, 'objectlist'));

return $frmHost;
?>
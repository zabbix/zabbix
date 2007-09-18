<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
	include_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/triggers.inc.php";
	require_once "include/items.inc.php";

	$page['title'] = "S_TRIGGER_LOG";
	$page['file'] = 'tr_logform.php';
	
	define('ZBX_PAGE_NO_MENU', 1);
	
include_once "include/page_header.php";

//---------------------------------- CHECKS ------------------------------------

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

	$fields=array(
		'description'=>			array(T_ZBX_STR, O_OPT,  NULL,		NOT_EMPTY,	'isset({save_trigger})'),
		'itemid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,	'isset({save_trigger})'),
		'sform'=>			array(T_ZBX_INT, O_OPT,  NULL,	  	IN('0,1'),	null),
		'sitems'=>			array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	null),

		'groupid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,	null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,	null),
		'triggerid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,	null),
		
		'priority'=>		array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1,2,3,4,5'),	'isset({save_trigger})'),
		'expressions'=>		array(T_ZBX_STR, O_OPT,	 NULL,		NOT_EMPTY,	'isset({save_trigger})'),
		'expr_type'=>		array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	null),
		'comments'=>		array(T_ZBX_STR, O_OPT,  null,  	null, null),
		'url'=>				array(T_ZBX_STR, O_OPT,  null,  	null, null),
		'status'=>		array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
		'save_trigger'=>	array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,	null)
	);
	
	check_fields($fields);

	$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,get_current_nodeid());

	validate_group_with_host(PERM_READ_WRITE,
							array('always_select_first_host','only_current_node'),
							'web.last.conf.groupid', 
							'web.last.conf.hostid'
							);
							
	$itemid = get_request('itemid',0);
//----------------------------------------------------------------------

$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT);
echo '<script type="text/javascript" src="js/items.js"></script>';

//------------------------ <ACTIONS> ---------------------------
if(isset($_REQUEST['save_trigger'])){

	show_messages();
	
	$expression = construct_expression($_REQUEST['itemid'],get_request('expressions',array()));
	if($expression){

		if(!check_right_on_trigger_by_expression(PERM_READ_WRITE, $expression)) access_deny();

		$now=time();
		if(isset($_REQUEST["status"]))	{ $status=1; }
		else{ $status=0; }
	
		$deps = get_request("dependences",array());
	
		if(isset($_REQUEST["triggerid"]))
		{
			$trigger_data = get_trigger_by_triggerid($_REQUEST["triggerid"]);
			if($trigger_data['templateid'])
			{
				$_REQUEST["description"] = $trigger_data["description"];
				$expression = explode_exp($trigger_data["expression"],0);
			}
	
			$result=update_trigger($_REQUEST["triggerid"],
				$expression,$_REQUEST["description"],
				$_REQUEST["priority"],$status,$_REQUEST["comments"],$_REQUEST["url"],
				$deps, $trigger_data['templateid']);
	
			$triggerid = $_REQUEST["triggerid"];
			$audit_action = AUDIT_ACTION_UPDATE;
	
			show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
		} else {
			$triggerid=add_trigger($expression,$_REQUEST["description"],
				$_REQUEST["priority"],$status,$_REQUEST["comments"],$_REQUEST["url"],
				$deps);
	
			$result = $triggerid;
			$audit_action = AUDIT_ACTION_ADD;
	
			show_messages($triggerid, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
		}
	
		if($result){
			add_audit($audit_action, AUDIT_RESOURCE_TRIGGER, S_TRIGGER." [".$triggerid."] [".expand_trigger_description($triggerid)."] ");
			unset($_REQUEST["sform"]);

			zbx_add_post_js('closeform("items.php");');
			include_once "include/page_footer.php";
		}
	}
}
//------------------------ </ACTIONS> --------------------------

//------------------------ <ITEMS> ---------------------------
if(isset($_REQUEST['sitems'])){
//	$res = DBselect('SELECT * FROM items WHERE key_ like "%log[%]%"');

	$form = new CForm();
	$form->SetMethod('POST');
	$form->SetAction('tr_logform.php?sitems=1');
	
	$where_case = array();
	$from_tables['h'] = 'hosts h';
	$where_case[] = 'i.hostid=h.hostid';
	$where_case[] = 'h.hostid in ('.$accessible_hosts.')';

	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit();');
	$cmbGroup->AddItem(0,S_ALL_SMALL);

	$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
				' FROM groups as g,hosts_groups as hg '.
				' WHERE g.groupid=hg.groupid AND hg.hostid in ('.$accessible_hosts.') '.
				' ORDER BY name');
				
	while($row=DBfetch($result)){
		$cmbGroup->AddItem($row['groupid'],$row['name']);
	}
	
	$form->AddItem(S_GROUP.SPACE);
	$form->AddItem($cmbGroup);

	if(isset($_REQUEST['groupid']) && $_REQUEST['groupid']>0){
		$sql='SELECT DISTINCT h.hostid,h.host '.
			' FROM hosts as h,hosts_groups as hg'.
			' WHERE hg.groupid='.$_REQUEST['groupid'].
				' AND hg.hostid=h.hostid '.
				' AND h.hostid in ('.$accessible_hosts.') '.
				' AND h.status<>'.HOST_STATUS_DELETED.
			' GROUP BY h.hostid,h.host '.
			' ORDER BY h.host';
	}
	else{
		$sql='SELECT DISTINCT h.hostid,h.host '.
			' FROM hosts as h '.
			' WHERE h.status<>'.HOST_STATUS_DELETED.
				' AND h.hostid in ('.$accessible_hosts.') '.
			' GROUP BY h.hostid,h.host '.
			' ORDER BY h.host';
	}

	$result=DBselect($sql);

	$_REQUEST['hostid'] = get_request('hostid',0);
	$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'submit();');

	unset($correct_hostid);
	$first_hostid = -1;
	
	while($row=DBfetch($result)){
		$cmbHosts->AddItem($row['hostid'],$row['host']);

		if($_REQUEST['hostid']!=0){
			if($_REQUEST['hostid']==$row['hostid'])
				$correct_hostid = 'ok';
		}
		if($first_hostid <= 0)
			$first_hostid = $row['hostid'];
	}
	if(!isset($correct_hostid))
		$_REQUEST['hostid'] = $first_hostid;

	$form->AddItem(SPACE.S_HOST.SPACE);
	$form->AddItem($cmbHosts);

	if($host_info = DBfetch(DBselect('SELECT host FROM hosts WHERE hostid='.$_REQUEST['hostid']))){
		$form->AddVar('with_host', $host_info['host']);
	}
	$where_case[] = 'i.hostid='.$_REQUEST['hostid'];
	
	$show_host = 0;
	show_table_header(S_ITEMS_BIG, $form);

// TABLE
	$form = new CForm();
	$form->SetName('items');
	$form->SetMethod('POST');

	$table  = new CTableInfo();
	$table->SetHeader(array(
		$show_host ? S_HOST : null,
		S_DESCRIPTION,
		S_KEY,S_TYPE,S_STATUS
		));

	$from_tables['i'] = 'items i'; /* NOTE: must be added as last element to use left join */

	$db_items = DBselect('SELECT DISTINCT th.host as template_host,th.hostid as template_hostid, h.host, i.* '.
					' FROM '.implode(',', $from_tables).
						' LEFT JOIN items as ti ON i.templateid=ti.itemid '.
						' LEFT JOIN hosts as th ON ti.hostid=th.hostid '.
					' WHERE '.implode(' AND ', $where_case).
						' AND i.value_type='.ITEM_VALUE_TYPE_LOG.
						' AND i.key_ LIKE ("log[%") '.
					' ORDER BY h.host,i.description,i.key_,i.itemid');
					
	while($db_item = DBfetch($db_items)){
		$description = '';

		$item_description = item_description($db_item['description'],$db_item['key_']);

		if($db_item['templateid'])
		{
			$template_host = get_realhost_by_itemid($db_item['templateid']);
			$description.= $template_host['host'].':';
		}
		
		$description.= $item_description;
		
		$description = new CLink($description,'#',null,'javascript: window.opener.document.getElementById("item").value = "'.$description.'"; window.opener.document.getElementsByName("itemid")[0].value = "'.$db_item['itemid'].'"; self.close(); return false;');	
		
		$status=new CCol(item_status2str($db_item['status']),item_status2style($db_item['status']));

//		$db_item['itemid']
		$table->AddRow(array(
			$show_host ? $db_item['host'] : null,
			$description,
			$db_item['key_'],
			item_type2str($db_item['type']),
			$status
			));
	}
	$table->SetFooter(new CCol(SPACE));

	$form->AddItem($table);
	$form->Show();
}
//------------------------ </ITEMS> --------------------------


//------------------------ <FORM> ---------------------------

if(isset($_REQUEST['sform'])){

	$frmTRLog = new CFormTable(S_TRIGGER,'tr_logform.php','POST',null,'sform');
	$frmTRLog->SetHelp('web.triggerlog.service.php');
	$frmTRLog->SetTableClass('formlongtable');
	
	if(isset($_REQUEST['triggerid'])) $frmTRLog->AddVar('triggerid',$_REQUEST['triggerid']);
	
	if(isset($_REQUEST['triggerid']) && !isset($_REQUEST['form_refresh'])){
		$frmTRLog->AddVar('form_refresh',get_request('form_refresh',1));
		
		$sql = 'SELECT DISTINCT f.functionid, f.function, f.parameter, t.expression, '.
								' t.description, t.priority, t.comments, t.url, t.status'. 
					' FROM functions as f, triggers as t, items as i '.
					' WHERE t.triggerid='.$_REQUEST['triggerid'].
						' AND i.itemid=f.itemid AND f.triggerid = t.triggerid '.
						' AND i.value_type='.ITEM_VALUE_TYPE_LOG.
						' AND i.key_ LIKE ("log[%")';
					  
		$res = DBselect($sql);
		while($rows = DBfetch($res)){
			$description = $rows['description'];
			$expression = $rows['expression'];
			$priority = $rows['priority'];
			$comments = $rows['comments'];
			$url = $rows['url'];
			$status = $rows['status'];
			
			$functionid[] = '/\{'.$rows['functionid'].'\}/Uu';
			$functions[] = $rows['function'].'('.$rows['parameter'].')';
		}
		
		$expression = preg_replace($functionid,$functions,$expression);
		$expr_incase = $expression;

		$expression = explode(" | ",$expression);

		foreach($expression as $id => $expr){
			$expr = preg_replace("/^\((.*)\)$/u","$1",$expr);

			$value = preg_replace("/(.*)[=|#]0/iUu","$1",$expr);
			$expressions[$id]['value']=trim($value);
			$expressions[$id]['type']=(strpos($expr,'#0',strlen($expr)-3) === false)?(REGEXP_EXCLUDE):(REGEXP_INCLUDE);
		}
	}
	else{
		$description = get_request('description','');
		$expressions = get_request('expressions',array());
		$priority = get_request('priority',0);
		$comments = get_request('comments','');
		$url = get_request('url','');
		$status = get_request('status',0);
	}
	
	$frmTRLog->AddRow(S_DESCRIPTION,new CTextBox('description',$description,80));

	$item = '';
	$db_items = DBselect('SELECT DISTINCT * FROM items WHERE itemid='.$itemid);
	while($db_item = DBfetch($db_items)){
		if($db_item['templateid']){
			$template_host = get_realhost_by_itemid($db_item['templateid']);
			$item = $template_host['host'].':';
		}
	
		$item .= item_description($db_item['description'],$db_item['key_']);
	}
	
	$ctb = new CTextBox('item',$item,80);
	$ctb->AddOption('id','item');
	$ctb->AddOption('disabled','disabled');
	
	$cbtn = new CButton('select_item',S_SELECT,"javascript: openWinCentered('tr_logform.php?sitems=1','ZBX_Items_List',840,420,'scrollbars=1, toolbar=0, menubar=0, resizable=1');");
	
	$frmTRLog->AddRow(S_ITEM,array($ctb, $cbtn));
	$frmTRLog->AddVar('itemid',$itemid);

	
	$exp_select = new CComboBox('expr_type');
	$exp_select->AddOption('id','expr_type');
		$exp_select->AddItem(REGEXP_INCLUDE,S_INCLUDE);
		$exp_select->AddItem(REGEXP_EXCLUDE,S_EXCLUDE);

	
	$ctb = new CTextBox('expression','',80);
	$ctb->AddOption('id','logexpr');
	
	$cb = new CButton('add_exp',S_ADD,'javascript: add_logexpr();');
	$cb->SetType('button');
	
	$frmTRLog->AddRow(S_EXPRESSION,array($ctb,SPACE,$exp_select,SPACE, $cb));
	
	$table = new CTable();
	
	$table->SetClass('tableinfo');
	$table->AddOption('id','exp_list');
	
	$table->oddRowClass = 'even_row';
	$table->evenRowClass = 'even_row';
	$table->options['cellpadding'] = 3;
	$table->options['cellspacing'] = 1;
	$table->headerClass = 'header';
	$table->footerClass = 'footer';
	
	$table->SetHeader(array(S_EXPRESSION,S_TYPE, S_POSITION,new CLink(S_DELETE,'#')));

	$maxid=0;

	if(isset($_REQUEST['triggerid']) && !isset($_REQUEST['save_trigger']) && !validate_expression(construct_expression($itemid,$expressions)) && !isset($_REQUEST['form_refresh'])){
		unset($expressions);
		$expressions[0]['value'] = $expr_incase;
		$expressions[0]['type'] = 0;
	}
	foreach($expressions as $id => $expr){
		
		$imgup = new CImg('images/general/arrowup.gif','up',12,14);
		$imgup->AddOption('onclick','javascript:  element_up("logtr'.$id.'");');
		$imgup->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');

		$imgdn = new CImg('images/general/arrowdown.gif','down',12,14);
		$imgdn->AddOption('onclick','javascript:  element_down("logtr'.$id.'");');
		$imgdn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
	
		$del_url = new CLink(S_DELETE,'#','action','javascript: if(confirm("Delete expression?")) remove_expression("logtr'.$id.'"); return false;');

		$row = new CRow(array(htmlspecialchars($expr['value']),(($expr['type']==REGEXP_INCLUDE)?S_INCLUDE:S_EXCLUDE),array($imgup,SPACE,$imgdn),$del_url));
		$row->AddOption('id','logtr'.$id);
		$table->AddRow($row);

		$frmTRLog->AddVar('expressions['.$id.'][value]',$expr['value']);
		$frmTRLog->AddVar('expressions['.$id.'][type]',$expr['type']);

		$maxid = ($maxid<$id)?$id:$maxid;
	}
	zbx_add_post_js('logexpr_count='.($maxid+1));

	$frmTRLog->AddRow(SPACE,$table);
	
	
	$sev_select = new CComboBox('priority',null);
		$sev_select->AddItem(TRIGGER_SEVERITY_NOT_CLASSIFIED,S_NOT_CLASSIFIED,(($priority == TRIGGER_SEVERITY_NOT_CLASSIFIED)?'on':'off'));
		$sev_select->AddItem(TRIGGER_SEVERITY_INFORMATION,S_INFORMATION,(($priority == TRIGGER_SEVERITY_INFORMATION)?'on':'off'));
		$sev_select->AddItem(TRIGGER_SEVERITY_WARNING,S_WARNING,(($priority == TRIGGER_SEVERITY_WARNING)?'on':'off'));
		$sev_select->AddItem(TRIGGER_SEVERITY_AVERAGE,S_AVERAGE,(($priority == TRIGGER_SEVERITY_AVERAGE)?'on':'off'));
		$sev_select->AddItem(TRIGGER_SEVERITY_HIGH,S_HIGH,(($priority == TRIGGER_SEVERITY_HIGH)?'on':'off'));
		$sev_select->AddItem(TRIGGER_SEVERITY_DISASTER,S_DISASTER,(($priority == TRIGGER_SEVERITY_DISASTER)?'on':'off'));
			
	$frmTRLog->AddRow(S_SEVERITY,$sev_select);
	
	$frmTRLog->AddRow(S_COMMENTS,new CTextArea('comments',$comments));
	
	$frmTRLog->AddRow(S_URL,new CTextBox('url',$url));

	$frmTRLog->AddRow(S_DISABLED,new CCheckBox('status', (($status == TRIGGER_STATUS_DISABLED)?'yes':'no'), null,1));
	
	$frmTRLog->AddItemToBottomRow(new CButton('save_trigger',S_SAVE,'javascript: document.forms[0].action += \'?saction=1\';'));
	$frmTRLog->AddItemToBottomRow(SPACE);

	$cb = new CButton('cancel',S_CANCEL);
	$cb->SetType('button');
	$cb->SetAction('javascript: self.close();');
	
	$frmTRLog->AddItemToBottomRow($cb);
	$frmTRLog->Show();
}
//------------------------ </FORM> ---------------------------
	
?>
<?php

include_once "include/page_footer.php";

?>
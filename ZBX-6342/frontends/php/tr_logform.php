<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
require_once('include/triggers.inc.php');
require_once('include/items.inc.php');

$page['title'] = 'S_TRIGGER_LOG_FORM';
$page['file'] = 'tr_logform.php';
$page['scripts'] = array();
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');
?>
<?php
//---------------------------------- CHECKS ------------------------------------

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

	$fields=array(
		'description'=>		array(T_ZBX_STR, O_OPT,  NULL,		NOT_EMPTY,	'isset({save_trigger})'),
		'itemid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,	'isset({save_trigger})'),
		'sform'=>			array(T_ZBX_INT, O_OPT,  NULL,	  	IN('0,1'),	null),
		'sitems'=>			array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	null),

		'groupid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,	null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,	null),
		'triggerid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,	null),

		'type'=>			array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	null),
		'priority'=>		array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1,2,3,4,5'),	'isset({save_trigger})'),
		'expressions'=>		array(T_ZBX_STR, O_OPT,	 NULL,		NOT_EMPTY,	'isset({save_trigger})'),
		'expr_type'=>		array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	null),
		'comments'=>		array(T_ZBX_STR, O_OPT,  null,  	null, null),
		'url'=>				array(T_ZBX_STR, O_OPT,  null,  	null, null),
		'status'=>			array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	 NULL,		NULL,	NULL),
		'save_trigger'=>	array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,	null),
		'keys'=> 			array(T_ZBX_STR, O_OPT,  NULL,		NULL,	NULL),
	);

	check_fields($fields);

	$itemid = get_request('itemid',0);

//------------------------ <ACTIONS> ---------------------------
if(isset($_REQUEST['save_trigger'])){
	show_messages();

	$exprs = get_request('expressions', false);
	if($exprs && ($expression = construct_expression($_REQUEST['itemid'], $exprs))){
		if(!check_right_on_trigger_by_expression(PERM_READ_WRITE, $expression)) access_deny();

		$now=time();
		if(isset($_REQUEST['status']))	{ $status=TRIGGER_STATUS_DISABLED; }
		else{ $status=TRIGGER_STATUS_ENABLED; }

		//if(isset($_REQUEST['type']))	{ $type=TRIGGER_MULT_EVENT_ENABLED; }
		//else{ $type=TRIGGER_MULT_EVENT_DISABLED; }
		$type = TRIGGER_MULT_EVENT_ENABLED;

		if(isset($_REQUEST['triggerid'])){
			$options = array(
				'triggerids' => $_REQUEST['triggerid'],
				'output' => API_OUTPUT_EXTEND,
				'select_dependencies' => API_OUTPUT_REFER
			);
			$triggersData = CTrigger::get($options);
			$triggerData = reset($triggersData);

// Saving dependencies
// TODO: add dependencies to CTrigger::update
			$deps = array();
			foreach($triggerData['dependencies'] as $dnum => $depTrigger){
				$deps[] = array(
					'triggerid' => $triggerData['triggerid'],
					'dependsOnTriggerid' => $depTrigger['triggerid']
				);
			}
//---
			if($triggerData['templateid']){
				$_REQUEST['description'] = $triggerData['description'];
				$expression = explode_exp($triggerData['expression']);
			}

			$trigger = array();
			$trigger['triggerid'] = $_REQUEST['triggerid'];
			$trigger['expression'] = $expression;
			$trigger['description'] = $_REQUEST['description'];
			$trigger['type'] = $type;
			$trigger['priority'] = $_REQUEST['priority'];
			$trigger['status'] = $status;
			$trigger['comments'] = $_REQUEST['comments'];
			$trigger['url'] = $_REQUEST['url'];

			DBstart();
			$result = CTrigger::update($trigger);

			$result &= CTrigger::addDependencies($deps);
//REVERT
			$result = DBend($result);

			$triggerid = $_REQUEST['triggerid'];
			$audit_action = AUDIT_ACTION_UPDATE;

			show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
		}
		else{
			$trigger = array();
			$trigger['expression'] = $expression;
			$trigger['description'] = $_REQUEST['description'];
			$trigger['type'] = $type;
			$trigger['priority'] = $_REQUEST['priority'];
			$trigger['status'] = $status;
			$trigger['comments'] = $_REQUEST['comments'];
			$trigger['url'] = $_REQUEST['url'];

			DBstart();
			if($result = CTrigger::create($trigger)){
				if($result !== false){
					$options = array(
						'triggerids' => $result['triggerids'],
						'output' => API_OUTPUT_EXTEND
					);
					$db_triggers = CTrigger::get($options);

					$result = true;
					$db_triggers = reset($db_triggers);
					$triggerid = $db_triggers['triggerid'];
				}
				else{
					$result = false;
				}
			}

			$result = DBend($result);

			// $result = $triggerid;
			$audit_action = AUDIT_ACTION_ADD;

			show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
		}

		if($result){
			add_audit($audit_action, AUDIT_RESOURCE_TRIGGER, S_TRIGGER." [".$triggerid."] [".expand_trigger_description($triggerid)."] ");
			unset($_REQUEST["sform"]);

			zbx_add_post_js('closeForm("items.php");');
			include_once('include/page_footer.php');
		}
	}
}
//------------------------ </ACTIONS> --------------------------

//------------------------ <FORM> ---------------------------

if(isset($_REQUEST['sform'])){
	$frmTRLog = new CFormTable(S_TRIGGER,'tr_logform.php','POST',null,'sform');
	$frmTRLog->setHelp('web.triggerlog.service.php');
	$frmTRLog->setTableClass('formlongtable formtable');
	$frmTRLog->addVar('form_refresh',get_request('form_refresh',1));

	if(isset($_REQUEST['triggerid'])) $frmTRLog->addVar('triggerid',$_REQUEST['triggerid']);

	if(isset($_REQUEST['triggerid']) && !isset($_REQUEST['form_refresh'])){
		$frmTRLog->addVar('form_refresh',get_request('form_refresh',1));

		$sql = 'SELECT DISTINCT f.functionid, f.function, f.parameter, t.expression, '.
								' t.description, t.priority, t.comments, t.url, t.status, t.type'.
					' FROM functions f, triggers t, items i '.
					' WHERE t.triggerid='.$_REQUEST['triggerid'].
						' AND i.itemid=f.itemid '.
						' AND f.triggerid = t.triggerid '.
						' AND i.value_type IN ('.ITEM_VALUE_TYPE_LOG.' , '.ITEM_VALUE_TYPE_TEXT.', '.ITEM_VALUE_TYPE_STR.')';

		$res = DBselect($sql);
		while($rows = DBfetch($res)){
			$description = $rows['description'];
			$expression = $rows['expression'];
			$type = $rows['type'];
			$priority = $rows['priority'];
			$comments = $rows['comments'];
			$url = $rows['url'];
			$status = $rows['status'];

			$functionid[] = '/\{'.$rows['functionid'].'\}/Uu';
			$functions[] = $rows['function'].'('.$rows['parameter'].')';
		}

		$expr_v = $expression;
		$expression = preg_replace($functionid,$functions,$expression);
		$expr_incase = $expression;

		$expression = preg_replace('/\(\(\((.+?)\)\) &/i', '(($1) &', $expression);
		$expression = preg_replace('/\(\(\((.+?)\)\)$/i', '(($1)', $expression);

		$expr_v = preg_replace('/\(\(\((.+?)\)\) &/i', '(($1) &', $expr_v);
		$expr_v = preg_replace('/\(\(\((.+?)\)\)$/i', '(($1)', $expr_v);

		$expression = preg_split('/ [&|] /',$expression);
		$expr_v = preg_split('/ [&|] /',$expr_v);

		foreach($expression as $id => $expr){
			$expr = preg_replace('/^\((.*)\)$/u','$1',$expr);

			if(preg_match('/\([regexp|iregexp].+\)[=|#]0/U',$expr, $rr)){
				$value = preg_replace('/(\(([regexp|iregexp].*)\)[=|#]0)/U','$2',$expr);
			}

			$value = preg_replace('/([=|#]0)/','',$expr);
			$value = preg_replace('/^\((.*)\)$/u','$1',$value); // removing wrapping parentheses

			$expressions[$id]['value'] = trim($value);
			$expressions[$id]['type'] = (zbx_strpos($expr,'#0',zbx_strlen($expr)-3) === false)?(REGEXP_EXCLUDE):(REGEXP_INCLUDE);
		}

		foreach($expr_v as $id => $expr) {
			$expr = preg_replace('/^\((.*)\)$/u','$1',$expr);
			$value = preg_replace('/\((.*)\)[=|#]0/U','$1',$expr);
			$value = preg_replace('/^\((.*)\)$/u','$1',$value);

			if (zbx_strpos($expr,'#0',zbx_strlen($expr)-3) === false) {
//REGEXP_EXCLUDE
				$value = str_replace('&', ' OR ', $value);
				$value = str_replace('|', ' AND ', $value);
			} else {
//EGEXP_INCLUDE
				$value = str_replace('&', ' AND ', $value);
				$value = str_replace('|', ' OR ', $value);
			}

			$value = preg_replace($functionid,$functions,$value);
			$value = preg_replace('/([=|#]0)/','',$value);

			$expressions[$id]['view'] = trim($value);
		}
	}
	else{
		$description = get_request('description','');
		$expressions = get_request('expressions',array());
		$type = get_request('type',0);
		$priority = get_request('priority',0);
		$comments = get_request('comments','');
		$url = get_request('url','');
		$status = get_request('status',0);
	}

	$keys = get_request('keys',array());

//sdi('<pre>'.print_r($expressions, true).'</pre>');
	$frmTRLog->addRow(S_DESCRIPTION,new CTextBox('description',$description,80));

	$item = '';
	$db_items = DBselect('SELECT DISTINCT * FROM items WHERE itemid='.$itemid);
	while($db_item = DBfetch($db_items)){
		if($db_item['templateid']){
			$template_host = get_realhost_by_itemid($db_item['templateid']);
			$item = $template_host['host'].':';
		}

		$item .= item_description($db_item,$db_item['key_']);
	}

	$ctb = new CTextBox('item',$item,80);
	$ctb->setAttribute('id','item');
	$ctb->setAttribute('disabled','disabled');

	$script = "javascript: return PopUp('popup.php?dstfrm=".$frmTRLog->getName()."&dstfld1=itemid&dstfld2=item&srctbl=items&srcfld1=itemid&srcfld2=description',800,450);";
	$cbtn = new CButton('select_item',S_SELECT,$script);

	$frmTRLog->addRow(S_ITEM,array($ctb, $cbtn));
	$frmTRLog->addVar('itemid',$itemid);


	$exp_select = new CComboBox('expr_type');
	$exp_select->setAttribute('id','expr_type');
		$exp_select->addItem(REGEXP_INCLUDE,S_INCLUDE_S);
		$exp_select->addItem(REGEXP_EXCLUDE,S_EXCLUDE);


	$ctb = new CTextBox('expression','',80);
	$ctb->setAttribute('id','logexpr');

	$cb = new CButton('add_exp',S_ADD,'javascript: add_logexpr();');
	$cb->setType('button');
	$cb->setAttribute('id','add_exp');

	$cbAdd = new CButton('add_key_and',S_AND_BIG,'javascript: add_keyword_and();');
	$cbAdd->setType('button');
	$cbAdd->setAttribute('id','add_key_and');
	$cbOr = new CButton('add_key_or',S_OR_BIG,'javascript: add_keyword_or();');
	$cbOr->setType('button');
	$cbOr->setAttribute('id','add_key_or');

	$cbIregexp = new CCheckBox('iregexp', 'no', null,1);
	$cbIregexp->setAttribute('id','iregexp');

	$frmTRLog->addRow(S_EXPRESSION,array($ctb,BR(),$cbIregexp,'iregexp',SPACE,$cbAdd,SPACE,$cbOr,SPACE,$exp_select,SPACE, $cb));

	$keyTable = new CTableInfo(null);
	$keyTable->setAttribute('id','key_list');
	$keyTable->setHeader(array(S_KEYWORD,S_TYPE, S_ACTION));

	$table = new CTableInfo(null);
	$table->setAttribute('id','exp_list');
	$table->setHeader(array(S_EXPRESSION,S_TYPE, S_POSITION, S_ACTION));

	$maxid=0;

	$bExprResult = true;
	$expressionData = new CTriggerExpression();
	if (isset($_REQUEST['triggerid']) && !isset($_REQUEST['save_trigger'])
			&& !$expressionData->parse(empty($expressions) ? '' : construct_expression($itemid, $expressions))
			&& !isset($_REQUEST['form_refresh'])) {

		info($expressionData->error);

		unset($expressions);
		$expressions[0]['value'] = $expr_incase;
		$expressions[0]['type'] = 0;
		$expressions[0]['view'] = $expr_incase;
		$bExprResult = false;
	}

//sdi('<pre>'.print_r($expressions,true).'</pre>');
	foreach($expressions as $id => $expr){

		$imgup = new CImg('images/general/arrowup.gif','up',12,14);
		$imgup->setAttribute('onclick','javascript:  element_up("logtr'.$id.'");');
		$imgup->setAttribute('onmouseover','javascript: this.style.cursor = "pointer";');

		$imgdn = new CImg('images/general/arrowdown.gif','down',12,14);
		$imgdn->setAttribute('onclick','javascript:  element_down("logtr'.$id.'");');
		$imgdn->setAttribute('onmouseover','javascript: this.style.cursor = "pointer";');

		$del_url = new CSpan(S_DELETE,'link');
		$del_url->setAttribute('onclick', 'javascript: if(confirm("'.S_DELETE_EXPRESSION_Q.'")) remove_expression("logtr'.$id.'"); return false;');

		$row = new CRow(array(htmlspecialchars($expr['view']),(($expr['type']==REGEXP_INCLUDE)?S_INCLUDE_S:S_EXCLUDE),array($imgup,SPACE,$imgdn),$del_url));
		$row->setAttribute('id','logtr'.$id);
		$table->addRow($row);

		$frmTRLog->addVar('expressions['.$id.'][value]',$expr['value']);
		$frmTRLog->addVar('expressions['.$id.'][type]',$expr['type']);
		$frmTRLog->addVar('expressions['.$id.'][view]',$expr['view']);

		$maxid = ($maxid<$id)?$id:$maxid;
	}
	zbx_add_post_js('logexpr_count='.($maxid+1));

	$maxid=0;
	foreach($keys as $id => $val){

	  $del_url = new CLink(S_DELETE,'#','action','javascript: if(confirm("'.S_DELETE_KEYWORD_Q.'")) remove_keyword("keytr'.$id.'"); return false;');
	  $row = new CRow(array(htmlspecialchars($val['value']),$val['type'],$del_url));
	  $row->setAttribute('id','keytr'.$id);
	  $keyTable->addRow($row);

	  $frmTRLog->addVar('keys['.$id.'][value]',$val['value']);
	  $frmTRLog->addVar('keys['.$id.'][type]',$val['type']);

	  $maxid = ($maxid<$id)?$id:$maxid;
	}
	zbx_add_post_js('key_count='.($maxid+1));

	$frmTRLog->addRow(SPACE,$keyTable);
	$frmTRLog->addRow(SPACE,$table);

	$sev_select = new CComboBox('priority',null);
		$sev_select->addItem(TRIGGER_SEVERITY_NOT_CLASSIFIED,S_NOT_CLASSIFIED,(($priority == TRIGGER_SEVERITY_NOT_CLASSIFIED)?'on':'off'));
		$sev_select->addItem(TRIGGER_SEVERITY_INFORMATION,S_INFORMATION,(($priority == TRIGGER_SEVERITY_INFORMATION)?'on':'off'));
		$sev_select->addItem(TRIGGER_SEVERITY_WARNING,S_WARNING,(($priority == TRIGGER_SEVERITY_WARNING)?'on':'off'));
		$sev_select->addItem(TRIGGER_SEVERITY_AVERAGE,S_AVERAGE,(($priority == TRIGGER_SEVERITY_AVERAGE)?'on':'off'));
		$sev_select->addItem(TRIGGER_SEVERITY_HIGH,S_HIGH,(($priority == TRIGGER_SEVERITY_HIGH)?'on':'off'));
		$sev_select->addItem(TRIGGER_SEVERITY_DISASTER,S_DISASTER,(($priority == TRIGGER_SEVERITY_DISASTER)?'on':'off'));

	$frmTRLog->addRow(S_SEVERITY,$sev_select);

	$frmTRLog->addRow(S_COMMENTS,new CTextArea('comments',$comments));

	$frmTRLog->addRow(S_URL,new CTextBox('url',$url,80));

	$frmTRLog->addRow(S_DISABLED,new CCheckBox('status', (($status == TRIGGER_STATUS_DISABLED)?'yes':'no'), null,1));

	$frmTRLog->addItemToBottomRow(new CButton('save_trigger',S_SAVE,'javascript: document.forms[0].action += \'?saction=1\';'));
	$frmTRLog->addItemToBottomRow(SPACE);

	$cb = new CButton('cancel',S_CANCEL, 'javascript: self.close();');
	$cb->setType('button');

	$frmTRLog->addItemToBottomRow($cb);
	if($bExprResult){
		$frmTRLog->show();
	}
}
//------------------------ </FORM> ---------------------------

?>
<?php

include_once('include/page_footer.php');

?>

<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Trigger form');
$page['file'] = 'tr_logform.php';
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'description' =>	array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,			'isset({save_trigger})'),
	'itemid' =>			array(T_ZBX_INT, O_OPT,	 P_SYS,			DB_ID,				'isset({save_trigger})'),
	'sform' =>			array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),			null),
	'sitems' =>			array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),			null),
	'triggerid' =>		array(T_ZBX_INT, O_OPT,  P_SYS,			DB_ID,				null),
	'type' =>			array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),			null),
	'priority' =>		array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1,2,3,4,5'),	'isset({save_trigger})'),
	'expressions' =>	array(T_ZBX_STR, O_OPT,	 NULL,			NOT_EMPTY,			'isset({save_trigger})'),
	'expr_type' =>		array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),			null),
	'comments' =>		array(T_ZBX_STR, O_OPT,  null,			null,				null),
	'url' =>			array(T_ZBX_STR, O_OPT,  null,			null,				null),
	'status' =>			array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),			null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT,	 NULL,			NULL,				NULL),
	'save_trigger' =>	array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,				null),
	'keys '=> 			array(T_ZBX_STR, O_OPT,  NULL,			NULL,				NULL),
);
check_fields($fields);

/*
 * Permissions
 */
if (get_request('itemid') && !API::Item()->isWritable(array($_REQUEST['itemid']))
		|| get_request('triggerid') && !API::Trigger()->isWritable(array($_REQUEST['triggerid']))) {
	access_deny();
}

$itemid = get_request('itemid', 0);

//------------------------ <ACTIONS> ---------------------------
if (isset($_REQUEST['save_trigger'])) {
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
				'selectDependencies' => API_OUTPUT_REFER
			);
			$triggersData = API::Trigger()->get($options);
			$triggerData = reset($triggersData);

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
			$result = API::Trigger()->update($trigger);
//REVERT
			$result = DBend($result);

			$triggerid = $_REQUEST['triggerid'];
			$audit_action = AUDIT_ACTION_UPDATE;

			show_messages($result, _('Trigger updated'), _('Cannot update trigger'));
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
			if($result = API::Trigger()->create($trigger)){
				if($result !== false){
					$options = array(
						'triggerids' => $result['triggerids'],
						'output' => API_OUTPUT_EXTEND
					);
					$db_triggers = API::Trigger()->get($options);

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

			show_messages($result, _('Trigger added'), _('Cannot add trigger'));
		}

		if($result){
			add_audit($audit_action, AUDIT_RESOURCE_TRIGGER, _('Trigger').' ['.$triggerid.'] ['.$trigger['description'].']');
			unset($_REQUEST['sform']);

			zbx_add_post_js('closeForm("items.php");');
			require_once dirname(__FILE__).'/include/page_footer.php';
		}
	}
}
//------------------------ </ACTIONS> --------------------------

//------------------------ <FORM> ---------------------------

if(isset($_REQUEST['sform'])){
	$frmTRLog = new CFormTable(_('Trigger'),'tr_logform.php','POST',null,'sform');
	$frmTRLog->setHelp('web.triggerlog.service.php');
	$frmTRLog->setTableClass('formlongtable formtable');
	$frmTRLog->addVar('form_refresh',get_request('form_refresh',1));

	if(isset($_REQUEST['triggerid'])) $frmTRLog->addVar('triggerid',$_REQUEST['triggerid']);

	if(isset($_REQUEST['triggerid']) && !isset($_REQUEST['form_refresh'])){
		$frmTRLog->addVar('form_refresh',get_request('form_refresh',1));

		$sql = 'SELECT DISTINCT f.functionid, f.function, f.parameter, t.expression, '.
								' t.description, t.priority, t.comments, t.url, t.status, t.type'.
					' FROM functions f, triggers t, items i '.
					' WHERE t.triggerid='.zbx_dbstr($_REQUEST['triggerid']).
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

		$expression = splitByFirstLevel($expression);
		$expr_v = splitByFirstLevel($expr_v);

		foreach($expression as $id => $expr){
			$expr = preg_replace('/^\((.*)\)$/u','$1',$expr);

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

	$frmTRLog->addRow(_('Description'), new CTextBox('description', $description, 80));

	$itemName = '';

	$dbItems = DBfetchArray(DBselect(
		'SELECT itemid,hostid,name,key_,templateid'.
		' FROM items'.
		' WHERE itemid='.zbx_dbstr($itemid)
	));
	$dbItems = CMacrosResolverHelper::resolveItemNames($dbItems);
	$dbItem = reset($dbItems);

	if ($dbItem['templateid']) {
		$template = get_realhost_by_itemid($dbItem['templateid']);
		$itemName = $template['host'].NAME_DELIMITER.$dbItem['name_expanded'];
	}
	else {
		$itemName = $dbItem['name_expanded'];
	}

	$ctb = new CTextBox('item', $itemName, 80);
	$ctb->setAttribute('id','item');
	$ctb->setAttribute('disabled','disabled');

	$script = "javascript: return PopUp('popup.php?dstfrm=".$frmTRLog->getName()."&dstfld1=itemid&dstfld2=item&srctbl=items&srcfld1=itemid&srcfld2=name',800,450);";
	$cbtn = new CSubmit('select_item',_('Select'),$script);

	$frmTRLog->addRow(_('Item'), array($ctb, $cbtn));
	$frmTRLog->addVar('itemid',$itemid);


	$exp_select = new CComboBox('expr_type');
	$exp_select->setAttribute('id','expr_type');
		$exp_select->addItem(REGEXP_INCLUDE,_('Include'));
		$exp_select->addItem(REGEXP_EXCLUDE,_('Exclude'));


	$ctb = new CTextBox('expression','',80);
	$ctb->setAttribute('id','logexpr');

	$cb = new CButton('add_exp',_('Add'),'javascript: add_logexpr();');
	$cbAdd = new CButton('add_key_and', _('AND'), 'javascript: add_keyword_and();');
	$cbOr = new CButton('add_key_or', _('OR'), 'javascript: add_keyword_or();');
	$cbIregexp = new CCheckBox('iregexp', 'no', null,1);


	$frmTRLog->addRow(_('Expression'), array($ctb,BR(),$cbIregexp,'iregexp',SPACE,$cbAdd,SPACE,$cbOr,SPACE,$exp_select,SPACE, $cb));

	$keyTable = new CTableInfo(null);
	$keyTable->setAttribute('id','key_list');
	$keyTable->setHeader(array(_('Keyword'), _('Type'), _('Action')));

	$table = new CTableInfo(null);
	$table->setAttribute('id','exp_list');
	$table->setHeader(array(_('Expression'), _('Type'), _('Position'), _('Action')));

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

	foreach($expressions as $id => $expr){

		$imgup = new CImg('images/general/arrow_up.png','up',12,14);
		$imgup->setAttribute('onclick','javascript:  element_up("logtr'.$id.'");');
		$imgup->setAttribute('onmouseover','javascript: this.style.cursor = "pointer";');

		$imgdn = new CImg('images/general/arrow_down.png','down',12,14);
		$imgdn->setAttribute('onclick','javascript:  element_down("logtr'.$id.'");');
		$imgdn->setAttribute('onmouseover','javascript: this.style.cursor = "pointer";');

		$del_url = new CSpan(_('Delete'),'link');
		$del_url->setAttribute('onclick', 'javascript: if(confirm("'._('Delete expression?').'")) remove_expression("logtr'.$id.'"); return false;');

		$row = new CRow(array(htmlspecialchars($expr['view']),(($expr['type']==REGEXP_INCLUDE)?_('Include'):_('Exclude')),array($imgup,SPACE,$imgdn),$del_url));
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
		$del_url = new CLink(_('Delete'),'#','action','javascript: if(confirm("'._('Delete keyword?').'")) remove_keyword("keytr'.$id.'"); return false;');
		$row = new CRow(array(htmlspecialchars($val['value']),$val['type'],$del_url));
		$row->setAttribute('id','keytr'.$id);
		$keyTable->addRow($row);

		$frmTRLog->addVar('keys['.$id.'][value]',$val['value']);
		$frmTRLog->addVar('keys['.$id.'][type]',$val['type']);

		$maxid = ($maxid<$id)?$id:$maxid;
	}
	zbx_add_post_js('key_count='.($maxid+1));

	$frmTRLog->addRow(SPACE, $keyTable);
	$frmTRLog->addRow(SPACE, $table);

	$sev_select = new CComboBox('priority', $priority);
	$sev_select->addItems(getSeverityCaption());
	$frmTRLog->addRow(_('Severity'), $sev_select);
	$frmTRLog->addRow(_('Comments'), new CTextArea('comments', $comments));
	$frmTRLog->addRow(_('URL'), new CTextBox('url', $url, 80));
	$frmTRLog->addRow(_('Disabled'), new CCheckBox('status', $status == TRIGGER_STATUS_DISABLED ? 'yes' : 'no', null, 1));
	$frmTRLog->addItemToBottomRow(new CSubmit('save_trigger', _('Save'), 'javascript: document.forms[0].action += \'?saction=1\';'));
	$frmTRLog->addItemToBottomRow(SPACE);
	$frmTRLog->addItemToBottomRow(new CButton('cancel', _('Cancel'), 'javascript: self.close();'));

	if ($bExprResult) {
		$frmTRLog->show();
	}
}

require_once dirname(__FILE__).'/include/page_footer.php';

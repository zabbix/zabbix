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

include_once('include/config.inc.php');
include_once('include/services.inc.php');
include_once('include/triggers.inc.php');

$page['title'] = "S_IT_SERVICES";
$page['file'] = 'services_form.php';
$page['scripts'] = array('class.calendar.js');

define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');


//---------------------------------- CHECKS ------------------------------------

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

	$fields=array(

		'serviceid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		'group_serviceid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),

		'name'=>			array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,	'isset({save_service})'),
		'algorithm'=>		array(T_ZBX_INT, O_OPT,  NULL,	IN('0,1,2'),	'isset({save_service})'),
		'showsla'=>			array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1'),null),
		'goodsla'=>			array(T_ZBX_DBL, O_OPT,  NULL,  BETWEEN(0,100),		null),
		'sortorder'=>		array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,999),	null),
		'service_times'=>	array(T_ZBX_STR, O_OPT,  null,  null,			null),

		'linktrigger'=>		array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1'),null),
		'triggerid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,	NULL),
		'trigger'=>			array(T_ZBX_STR, O_OPT,  null,  null,			null), //??

		'serverid'=>		array(T_ZBX_INT, O_OPT,  null,  DB_ID,		'isset({add_server})'),

		'new_service_time'=>	array(T_ZBX_STR, O_OPT,  null,  null,			null),
		'rem_service_times'=>	array(T_ZBX_STR, O_OPT,  null,  null,			null),

		'childs'=>			array(T_ZBX_STR, O_OPT,	 P_SYS,	DB_ID,NULL),

		'parentid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		'parentname'=>		array(T_ZBX_STR, O_OPT,  null,  null, null),

/* actions */
		'saction'=>			array(T_ZBX_INT, O_OPT,  P_ACT,  IN('0,1'),	null),

		'save_service'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'add_server'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		'add_service_time'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'del_service_times'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		'delete'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */

		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_copy_to'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
		'sform'=>			array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1'),	null),
		'pservices'=>		array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1'),	null),
		'cservices'=>		array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1'),	null),
		'slink'=>			array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1'),	null)
	);

	check_fields($fields);

//----------------------------------------------------------------------

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array());

	if(isset($_REQUEST['serviceid']) && $_REQUEST['serviceid'] > 0){
		$sql = 'SELECT s.* '.
					' FROM services s '.
					' WHERE (s.triggerid IS NULL OR '.DBcondition('s.triggerid',$available_triggers).') '.
						' AND s.serviceid='.$_REQUEST['serviceid'];
		if(!$service = DBfetch(DBselect($sql))){
			access_deny();
		}
	}

/*-------------------------------------------- ACTIONS --------------------------------------------*/
if(isset($_REQUEST['saction'])){

	$_REQUEST['showsla']	= get_request('showsla',0);
	$_REQUEST['soft']	= get_request('soft', 0);

	if(isset($_REQUEST['delete']) && isset($_REQUEST['serviceid'])){

		$result = delete_service($service['serviceid']);
		show_messages($result, S_SERVICE_DELETED, S_CANNOT_DELETE_SERVICE);
		add_audit_if($result,AUDIT_ACTION_DELETE,AUDIT_RESOURCE_IT_SERVICE,' Name ['.$service['name'].'] id ['.$service['serviceid'].']');
		unset($service);
	}
	else if(isset($_REQUEST['save_service'])){

		$service_times = get_request('service_times',array());
		$childs = get_request('childs',array());

		$triggerid = (isset($_REQUEST['linktrigger']))?($_REQUEST['triggerid']):(null);

		DBstart();
		if(isset($service['serviceid'])){
			$result = update_service($service['serviceid'],
				$_REQUEST['name'],$triggerid,$_REQUEST['algorithm'],
				$_REQUEST['showsla'],$_REQUEST['goodsla'],$_REQUEST['sortorder'],
				$service_times,$_REQUEST['parentid'],$childs);
		}
		else {
			$result = add_service(
				$_REQUEST['name'],$triggerid,$_REQUEST['algorithm'],
				$_REQUEST['showsla'],$_REQUEST['goodsla'],$_REQUEST['sortorder'],
				$service_times,$_REQUEST['parentid'],$childs);
		}

		$result = DBend()?$result:false;

		if(isset($service['serviceid'])){
			show_messages($result, S_SERVICE_UPDATED, S_CANNOT_UPDATE_SERVICE);
			$serviceid = $service['serviceid'];
			$audit_acrion = AUDIT_ACTION_UPDATE;
		}
		else{
			show_messages($result, S_SERVICE_ADDED, S_CANNOT_ADD_SERVICE);
			$serviceid = $result;
			$audit_acrion = AUDIT_ACTION_ADD;
		}

		add_audit_if($result,$audit_acrion,AUDIT_RESOURCE_IT_SERVICE,' Name ['.$_REQUEST['name'].'] id ['.$serviceid.']');

	}
	else if(isset($_REQUEST['add_server'])){
		$sql = 'SELECT h.* '.
				' FROM hosts h '.
				' WHERE '.DBin_node('h.hostid').
					' AND '.DBcondition('h.hostid',$available_hosts).
					' AND h.hostid='.$_REQUEST['serverid'];
		if(!$host_data = DBfetch(DBselect($sql))){
			access_deny();
		}

		$result = add_host_to_services($_REQUEST['serverid'], $service['serviceid']);
		add_audit_if($result,AUDIT_ACTION_ADD,AUDIT_RESOURCE_IT_SERVICE,' Host ['.$host_data['host'].'] id ['.$_REQUEST['serverid'].']');
		show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
	}
	if($result){
		zbx_add_post_js("closeForm('services.php');");
		include_once('include/page_footer.php');
	}
}
//-------------------------------------------- </ACTIONS> --------------------------------------------
//----------------------------------------- <PARENT SERVICES LIST> ------------------------------------------

if(isset($_REQUEST['pservices'])){
	if(isset($service)) $service = get_service_by_serviceid($service['serviceid']); // update date after ACTIONS

	show_table_header(S_IT_SERVICES_BIG);

	$form = new CForm();
	$form->setName('services');

	if(isset($service)) $form->addVar('serviceid', $service['serviceid']);

	$table = new CTableInfo();
	$table->setHeader(array(
		S_SERVICE,
		S_STATUS_CALCULATION,
		S_TRIGGER
		));

//root
		$prefix	 = null;
		$trigger = '-';

		$description = S_ROOT_SMALL;

		$description = new CLink($description,'#');
		$description->setAction('javascript:
				window.opener.document.forms[0].elements[\'parent_name\'].value = '.zbx_jsvalue(S_ROOT_SMALL).';
				window.opener.document.forms[0].elements[\'parentname\'].value = '.zbx_jsvalue(S_ROOT_SMALL).';
				window.opener.document.forms[0].elements[\'parentid\'].value = '.zbx_jsvalue(0).';
				self.close(); return false;');

		$table->addRow(array(array($prefix,$description),S_NONE,$trigger));
//-----
	if(isset($service)){
		$childs = get_service_childs($service['serviceid'],1);
		$childs_str = implode(',',$childs);
		(!empty($childs_str))?($childs_str.=','):('');

		$query = 'SELECT DISTINCT s.* '.
				' FROM services s '.
				' WHERE '.DBin_node('s.serviceid').
					' AND (s.triggerid IS NULL OR '.DBcondition('s.triggerid',$available_triggers).') '.
					' AND s.serviceid NOT IN ('.$childs_str.$service['serviceid'].') '.
				' ORDER BY s.sortorder,s.name';
	}
	else {
		$query = 'SELECT DISTINCT s.* '.
			' FROM services s '.
			' WHERE '.DBin_node('s.serviceid').
				' AND (s.triggerid IS NULL OR '.DBcondition('s.triggerid',$available_triggers).') '.
			' ORDER BY s.sortorder,s.name';
	}

	$db_services = DBselect($query);

	while($db_service_data = DBfetch($db_services)){
		$prefix	 = null;
		$trigger = '-';

		$description = $db_service_data['name'];

		$description = new CSpan($description,'link');
		$description->setAttribute('onclick', 'javascript:
						window.opener.document.forms[0].elements[\'parent_name\'].value = '.zbx_jsvalue($db_service_data['name']).';
						window.opener.document.forms[0].elements[\'parentname\'].value = '.zbx_jsvalue($db_service_data['name']).';
						window.opener.document.forms[0].elements[\'parentid\'].value = '.zbx_jsvalue($db_service_data['serviceid']).';
						self.close(); return false;');

		if(isset($db_service_data['triggerid'])){
			$trigger = expand_trigger_description($db_service_data['triggerid']);
		}

		$table->addRow(array(array($prefix,$description),algorithm2str($db_service_data['algorithm']),$trigger));
	}

	$cb = new CButton('cancel',S_CANCEL);
	$cb->setType('button');
	$cb->setAction('javascript: self.close();');

	$td = new CCol($cb);
	$td->setAttribute('style','text-align:right;');

	$table->setFooter($td);
	$form->addItem($table);
	$form->Show();
}
//--------------------------------------------	</PARENT SERVICES LIST>  --------------------------------------------

//---------------------------------------------- <CHILD SERVICES LIST> --------------------------------------------

if(isset($_REQUEST['cservices'])){
	if(isset($service)) $service = get_service_by_serviceid($service['serviceid']); // update date after ACTIONS

	show_table_header(S_IT_SERVICES_BIG);

	$form = new CForm();
	$form->setName('services');

	if(isset($service)) $form->addVar('serviceid', $service['serviceid']);

	$table = new CTableInfo();
	$table->setHeader(array(S_SERVICE,S_STATUS_CALCULATION,S_TRIGGER));

	if(isset($service)){
		$childs = get_service_childs($service['serviceid'],1);
		$childs_str = implode(',',$childs);
		(!empty($childs_str))?($childs_str.=','):('');

		$query = 'SELECT DISTINCT s.* '.
				' FROM services s '.
				' WHERE '.DBin_node('s.serviceid').
					' AND (s.triggerid IS NULL OR '.DBcondition('s.triggerid',$available_triggers).') '.
					' AND s.serviceid NOT IN ('.$childs_str.$service['serviceid'].') '.
				' ORDER BY s.sortorder,s.name';

	} else {
		$query = 'SELECT DISTINCT s.* '.
				' FROM services s '.
				' WHERE '.DBin_node('s.serviceid').
					' AND (s.triggerid IS NULL OR '.DBcondition('s.triggerid',$available_triggers).') '.
				' ORDER BY s.sortorder,s.name';
	}

	$db_services = DBselect($query);

	while($db_service_data = DBfetch($db_services)){
		$prefix	 = null;
		$trigger = '-';

		$description = $db_service_data['name'];

		if(isset($db_service_data['triggerid'])){
			$trigger = expand_trigger_description($db_service_data['triggerid']);
		}

		$description = new CLink($description,'#');
		$description->setAction('window.opener.add_child_service('.zbx_jsvalue($db_service_data['name']).','.zbx_jsvalue($db_service_data['serviceid']).','.zbx_jsvalue($trigger).','.zbx_jsvalue($db_service_data['triggerid']).'); self.close(); return false;');

		$table->addRow(array(array($prefix,$description),algorithm2str($db_service_data['algorithm']),$trigger));
	}

	$cb = new CButton('cancel',S_CANCEL);
	$cb->setType('button');
	$cb->setAction('javascript: self.close();');

	$td = new CCol($cb);
	$td->setAttribute('style','text-align:right;');

	$table->setFooter($td);
	$form->addItem($table);
	$form->Show();

}
//--------------------------------------------	</CHILD SERVICES LIST>  --------------------------------------------

//--------------------------------------------	<FORM>  --------------------------------------------
if(isset($_REQUEST['sform'])){
	$frmService = new CFormTable(S_SERVICE,'services_form.php','POST',null,'sform');
	$frmService->setHelp('web.services.service.php');

	$frmService->setTableClass('formlongtable formtable');

//service times
	if(isset($_REQUEST['add_service_time']) && isset($_REQUEST['new_service_time'])){
		$_REQUEST['service_times'] = get_request('service_times',array());

		$new_service_time['type'] = $_REQUEST['new_service_time']['type'];

		//sdii($_REQUEST['new_service_time']);
		if($_REQUEST['new_service_time']['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME){
			$new_service_time['from'] = $_REQUEST['new_service_time']['from'];
			$new_service_time['to'] = $_REQUEST['new_service_time']['to'];
			$new_service_time['note'] = $_REQUEST['new_service_time']['note'];
		}
		else{
			$new_service_time['from'] = strtotime($_REQUEST['new_service_time']['from_week'].' '.$_REQUEST['new_service_time']['from']);
			$new_service_time['to'] = strtotime($_REQUEST['new_service_time']['to_week'].' '.$_REQUEST['new_service_time']['to']);
			$new_service_time['note'] = $_REQUEST['new_service_time']['note'];
		}
		//sdi($new_service_time);
		while($new_service_time['to'] && ($new_service_time['to'] <= $new_service_time['from']))
			$new_service_time['to'] += 7*24*3600;

		//validating service times that were entered
		if ($new_service_time['to'] == false){
			//time 'to' has a wrong format
			error(S_ERROR_ADDING_SERVICE_TIME.' '.$_REQUEST['new_service_time']['to'].' '.S_IS_A_WRONG_TIME_FORMAT);
		}
		elseif($new_service_time['from'] == false) {
			//time 'from' has a wrong format
			error(S_ERROR_ADDING_SERVICE_TIME.' '.$_REQUEST['new_service_time']['from'].' '.S_IS_A_WRONG_TIME_FORMAT);
		}
		elseif(!str_in_array($_REQUEST['service_times'], $new_service_time)){
			//if this time is not already there, adding it for insertation
			array_push($_REQUEST['service_times'],$new_service_time);		
		}

	}
	else if(isset($_REQUEST['del_service_times']) && isset($_REQUEST['rem_service_times'])){
		$_REQUEST['service_times'] = get_request('service_times',array());
		foreach($_REQUEST['rem_service_times'] as $val){
			unset($_REQUEST['service_times'][$val]);
		}
	}
	$service_times = get_request('service_times',array());
	$new_service_time = get_request('new_service_time',array('type' => SERVICE_TIME_TYPE_UPTIME));
//----------

	if(isset($service['serviceid'])){
		$frmService->setTitle(S_SERVICE.' "'.$service['name'].'"');
	}

	if(isset($service['serviceid']) && !isset($_REQUEST['form_refresh'])){
		$name		= $service['name'];
		$algorithm	= $service['algorithm'];
		$showsla	= $service['showsla'];
		$goodsla	= $service['goodsla'];
		$sortorder	= $service['sortorder'];
		$triggerid	= $service['triggerid'];
		$linktrigger	= isset($triggerid) ? 1 : 0;
		if(!isset($triggerid)) $triggerid = 0;

		$result = DBselect('select * from services_times where serviceid='.$service['serviceid']);

		while($db_stime = DBfetch($result)){
			$stime = array(
				'type'=>	$db_stime['type'],
				'from'=>	$db_stime['ts_from'],
				'to'=>		$db_stime['ts_to'],
				'note'=>	$db_stime['note']
				);
			if(str_in_array($stime, $service_times)){
				continue;
			}
			array_push($service_times, $stime);
		}
//links
		$query = 'SELECT DISTINCT sl.linkid, sl.soft, sl.serviceupid, sl.servicedownid, '.
					' s1.name as serviceupname, s2.name as servicedownname '.
				' FROM services s1, services s2, services_links sl '.
				' WHERE sl.serviceupid=s1.serviceid '.
					' AND sl.servicedownid=s2.serviceid '.
					' AND NOT(sl.soft=1) '.
					' AND sl.servicedownid='.$service['serviceid'];

		if($link=DBFetch(DBSelect($query))){
			$parentid = $link['serviceupid'];
			$parentname = $link['serviceupname'];
		} else {
			$parentid = 0;
			$parentname = 'root';
		}

		$query = 'SELECT DISTINCT s.*, sl.soft '.
				' FROM services s1, services s2, services_links sl, services s '.
				' WHERE (s.triggerid IS NULL OR '.DBcondition('s.triggerid',$available_triggers).') '.
					' AND '.DBin_node('s.serviceid').
					' AND sl.serviceupid=s1.serviceid '.
					' AND sl.servicedownid=s2.serviceid '.
					' AND sl.serviceupid='.$service['serviceid'].
					' AND s.serviceid=sl.servicedownid';

		$db_services = DBselect($query);

		$childs = array();
		while($db_service_data = DBfetch($db_services)){
			$child = array(
				'name' => $db_service_data['name'],
				'serviceid' => $db_service_data['serviceid'],
				'triggerid' => $db_service_data['triggerid'],
				'soft' => $db_service_data['soft']
			);
			if(str_in_array($child,	$childs)){
				continue;
			}
			array_push($childs,$child);
		}
//---
	}
	else{
		$name		= get_request('name','');
		$showsla	= get_request('showsla',0);
		$goodsla	= get_request('goodsla',99.05);
		$sortorder	= get_request('sortorder',0);
		$algorithm	= get_request('algorithm',SERVICE_ALGORITHM_MAX);
		$triggerid	= get_request('triggerid',0);
		$linktrigger	= get_request('linktrigger',0);
//links
		$parentid = get_request('parentid',0);
		$parentname = get_request('parentname','');

		$childs = get_request('childs',array());
//-----
	}

	if(isset($service)){
		$frmService->addVar('serviceid',$service['serviceid']);
	}

	$frmService->addRow(S_NAME,new CTextBox('name',$name,60));

//link
//-------------------------------------------- <LINK> --------------------------------------------
//parent link
	$ctb = new CTextBox('parent_name',$parentname,60);
	$ctb->setAttribute('disabled','disabled');

	$frmService->addVar('parentname',$parentname);
	$frmService->addVar('parentid',$parentid);

	$cb = new CButton('select_parent',S_CHANGE);
	$cb->setType('button');
	$cb->setAction("javascript: openWinCentered('services_form.php?pservices=1".url_param('serviceid')."','ZBX_Services_List',740,420,'scrollbars=1, toolbar=0, menubar=0, resizable=1, dialog=0');");

	$frmService->addRow(S_PARENT_SERVICE,array($ctb,$cb));
//----------

//child links

	$table = new CTable();

	$table->setClass('tableinfo');
	$table->setOddRowClass('even_row');
	$table->setEvenRowClass('even_row');
	$table->attributes['cellpadding'] = 3;
	$table->attributes['cellspacing'] = 1;
	$table->headerClass = 'header';
	$table->footerClass = 'footer';

	$table->setHeader(array(new CCheckBox('all_child_services',null,"check_childs('".$frmService->GetName()."','childs','all_child_services');"),S_SERVICES,S_SOFT,S_TRIGGER));

	$table->setAttribute('id','service_childs');

	foreach($childs as $id => $child){
		$prefix	 = null;
		$trigger = '-';

		$description = new CLink($child['name'],'services_form.php?sform=1&serviceid='.$child['serviceid']);

		if(isset($child['triggerid']) && !empty($child['triggerid'])){
			$trigger = expand_trigger_description($child['triggerid']);
		}

		$table->addRow(array(
				array(
					new CCheckBox('childs_to_del['.$child['serviceid'].'][serviceid]',null,null,$child['serviceid']),
					new CVar('childs['.$child['serviceid'].'][serviceid]', $child['serviceid'])
					),
				array(
					$description,
					new CVar('childs['.$child['serviceid'].'][name]', $child['name'])
					),
				new CCheckBox(
					'childs['.$child['serviceid'].'][soft]',
					(isset($child['soft']) && !empty($child['soft']))?('checked'):('no'),null,
					(isset($child['soft']) && !empty($child['soft']))?(1):(0)
					),
				array(
					$trigger,
					new CVar('childs['.$child['serviceid'].'][triggerid]', (isset($child['triggerid']))?($child['triggerid']):(''))
					)
				));
	}

	$cb = new CButton('add_child_service',S_ADD);
	$cb->setType('button');
	$cb->setAction("javascript: openWinCentered('services_form.php?cservices=1".url_param('serviceid')."','ZBX_Services_List',640,520,'scrollbars=1, toolbar=0, menubar=0, resizable=0');");

	$cb2 = new CButton('del_child_service',S_REMOVE);
	$cb2->setType('button');
	$cb2->setAction("javascript: remove_childs('".$frmService->GetName()."','childs_to_del','tr');");

	$frmService->addRow(S_DEPENDS_ON,array($table,BR(),$cb,$cb2));
//----------
//--------------------------------------------- </LINK> -------------------------------------------

//algorithm
	$cmbAlg = new CComboBox('algorithm',$algorithm);
	foreach(array(SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN, SERVICE_ALGORITHM_NONE) as $val)
		$cmbAlg->addItem($val,algorithm2str($val));
	$frmService->addRow(S_STATUS_CALCULATION_ALGORITHM, $cmbAlg);
//-------

//SLA
	$frmService->addRow(S_CALCULATE_SLA, new CCheckBox('showsla',$showsla,"javascript: display_element('sla_row');",1));

	$row = new CRow(array(
						new CCol(S_ACCEPTABLE_SLA_IN_PERCENT,'form_row_l'),
						new CCol(new CTextBox('goodsla',$goodsla,6),'form_row_r')
						)
					);

	$row->setAttribute('style',($linktrigger == 1)?(''):('display: none;'));

	$row->setAttribute('id','sla_row');
	$row->setAttribute('style',($showsla)?(''):('display: none;'));

	$frmService->addRow($row);
//------

//times
	$stime_el = array();
	$i = 0;

	foreach($service_times as $val){
		switch($val['type']){
			case SERVICE_TIME_TYPE_UPTIME:
				$type = new CSpan(S_UPTIME,'enabled');
				$from = zbx_date2str(S_SERVICES_UPTIME_DATE_FORMAT, $val['from']);
				$to = zbx_date2str(S_SERVICES_UPTIME_DATE_FORMAT, $val['to']);
				break;
			case SERVICE_TIME_TYPE_DOWNTIME:
				$type = new CSpan(S_DOWNTIME,'disabled');
				$from = zbx_date2str(S_SERVICES_DOWNTIME_DATE_FORMAT, $val['from']);
				$to = zbx_date2str(S_SERVICES_DOWNTIME_DATE_FORMAT, $val['to']);
				break;
			case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
				$type = new CSpan(S_ONE_TIME_DOWNTIME,'disabled');
				$from = zbx_date2str(S_SERVICES_ONETIME_DOWNTIME_DATE_FORMAT, $val['from']);
				$to = zbx_date2str(S_SERVICES_ONETIME_DOWNTIME_DATE_FORMAT, $val['to']);
				break;
		}
		array_push($stime_el, array(new CCheckBox('rem_service_times[]', 'no', null,$i),
			$type,':'.SPACE, $from, SPACE.'-'.SPACE, $to,
			(!empty($val['note'])?(array(BR(),'['.htmlspecialchars($val['note']).']')):('')),BR()));


		$frmService->addVar('service_times['.$i.'][type]',	$val['type']);
		$frmService->addVar('service_times['.$i.'][from]',	$val['from']);
		$frmService->addVar('service_times['.$i.'][to]',	$val['to']);
		$frmService->addVar('service_times['.$i.'][note]',	$val['note']);

		$i++;
	}

//	print_r($stime_el);

	if(count($stime_el)==0)
		array_push($stime_el, S_NO_TIMES_DEFINED);
	else
		array_push($stime_el, new CButton('del_service_times',S_DELETE_SELECTED));

	$frmService->addRow(S_SERVICE_TIMES, $stime_el);

	$cmbTimeType = new CComboBox('new_service_time[type]',$new_service_time['type'],'javascript: document.forms[0].action += \'?sform=1\'; submit();');
	$cmbTimeType->addItem(SERVICE_TIME_TYPE_UPTIME, S_UPTIME);
	$cmbTimeType->addItem(SERVICE_TIME_TYPE_DOWNTIME, S_DOWNTIME);
	$cmbTimeType->addItem(SERVICE_TIME_TYPE_ONETIME_DOWNTIME, S_ONE_TIME_DOWNTIME);

	$time_param = new CTable();

	$div = new Ctag('div','yes');

	if($new_service_time['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME){
//		$time_param->addRow(array(S_NOTE, new CTextBox('new_service_time[note]','<short description>',40)));
//		$time_param->addRow(array(S_FROM, new CTextBox('new_service_time[from]','d M Y H:i',20)));
//		$time_param->addRow(array(S_TILL, new CTextBox('new_service_time[to]','d M Y H:i',20)));



		$script = "javascript: ".
						"if(CLNDR['downtime_since'].clndr.setSDateFromOuterObj()){".
							"$('new_service_time[from]').value = parseInt(CLNDR['downtime_since'].clndr.sdt.getTime()/1000);}".
						"if(CLNDR['downtime_till'].clndr.setSDateFromOuterObj()){".
							"$('new_service_time[to]').value = parseInt(CLNDR['downtime_till'].clndr.sdt.getTime()/1000);}"
						;
		$frmService->addAction('onsubmit',$script);
		$frmService->addVar('new_service_time[from]','');
		$frmService->addVar('new_service_time[to]','');

		$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
		$clndr_icon->addAction('onclick','javascript: var pos = getPosition(this);'.
											' pos.top-=203; pos.left+=16; '.
											" CLNDR['downtime_since'].clndr.clndrshow(pos.top,pos.left);"
								);

		$filtertimetab = new CTable(null,'calendar');
		$filtertimetab->setAttribute('width','10%');

		$filtertimetab->setCellPadding(0);
		$filtertimetab->setCellSpacing(0);

		$td_tmp = new CCol(new CTextBox('new_service_time[note]','<short description>',40));
		$td_tmp->setColSpan(10);
		$filtertimetab->addRow(array(S_NOTE, $td_tmp));

		$filtertimetab->addRow(array(
								S_FROM,
								new CNumericBox('downtime_since_day','',2),
								'/',
								new CNumericBox('downtime_since_month','',2),
								'/',
								new CNumericBox('downtime_since_year','',4),
								SPACE,
								new CNumericBox('downtime_since_hour','',2),
								':',
								new CNumericBox('downtime_since_minute','',2),
								$clndr_icon
						));
		zbx_add_post_js('create_calendar('.
							'null,'.
							'["downtime_since_day","downtime_since_month","downtime_since_year","downtime_since_hour","downtime_since_minute"],'.
							'"downtime_since");'
						);

		$clndr_icon->addAction('onclick','javascript: var pos = getPosition(this);'.
											' pos.top-=203; pos.left+=16; '.
											" CLNDR['downtime_till'].clndr.clndrshow(pos.top,pos.left);"
								);
		$filtertimetab->addRow(array(
								S_TILL,
								new CNumericBox('downtime_till_day','',2),
								'/',
								new CNumericBox('downtime_till_month','',2),
								'/',
								new CNumericBox('downtime_till_year','',4),
								SPACE,
								new CNumericBox('downtime_till_hour','',2),
								':',
								new CNumericBox('downtime_till_minute','',2),
								$clndr_icon
						));
		zbx_add_post_js('create_calendar('.
							'null,'.
							'["downtime_till_day","downtime_till_month","downtime_till_year","downtime_till_hour","downtime_till_minute"],'.
							'"downtime_till");'
						);

		$time_param->addRow($filtertimetab);
	}
	else{
		$cmbWeekFrom = new CComboBox('new_service_time[from_week]','Sunday');
		$cmbWeekTo = new CComboBox('new_service_time[to_week]','Sunday');
		foreach(array(
			'Sunday'  =>S_SUNDAY,
			'Monday'  =>S_MONDAY,
			'Tuesday' =>S_TUESDAY,
			'Wednesday'=>S_WEDNESDAY,
			'Thursday'=>S_THURSDAY,
			'Friday'  =>S_FRIDAY,
			'Saturday' =>S_SATURDAY
			) as $day_num => $day_str)
		{
			$cmbWeekFrom->addItem($day_num, $day_str);
			$cmbWeekTo->addItem($day_num, $day_str);
		}

		$time_param->addRow(array(S_FROM, $cmbWeekFrom, new CTextBox('new_service_time[from]','H:i',9)));
		$time_param->addRow(array(S_TILL, $cmbWeekTo, new CTextBox('new_service_time[to]','H:i',9)));
		$frmService->addVar('new_service_time[note]','');
	}

	$frmService->addRow(S_NEW_SERVICE_TIME, array(
			$cmbTimeType, BR(),
			$time_param,
			new CButton('add_service_time',S_ADD_SMALL,'javascript: document.forms[0].action += \'?sform=1\';')
		));
//trigger
	$frmService->addRow(S_LINK_TO_TRIGGER_Q, new CCheckBox('linktrigger',$linktrigger,"javascript: display_element('trigger_name');",1));

	if($triggerid > 0){
		$trigger = expand_trigger_description_simple($triggerid);
	}
	else{
		$trigger = '';
	}

	$row = new CRow(array(
						new CCol(S_TRIGGER,'form_row_l'),
						new CCol(array(
									new CTextBox('trigger',$trigger,64,'yes'),
									new CButton('btn1',S_SELECT,"return PopUp('popup.php?"."dstfrm=".$frmService->GetName()."&dstfld1=triggerid&dstfld2=trigger"."&srctbl=triggers&srcfld1=triggerid&srcfld2=description&real_hosts=1');",'T')
								),'form_row_r')
							));
	$row->setAttribute('id','trigger_name');
	$row->setAttribute('style',($linktrigger == 1)?(''):('display: none;'));

	$frmService->addRow($row);

	$frmService->addVar('triggerid',$triggerid);
//---------

//sortorder
	$frmService->addRow(S_SORT_ORDER_0_999, new CTextBox('sortorder',$sortorder,3));
//---------

	$frmService->addItemToBottomRow(new CButton('save_service',S_SAVE,'javascript: document.forms[0].action += \'?saction=1\';'));
	if(isset($service['serviceid'])){
		$frmService->addItemToBottomRow(SPACE);
		$frmService->addItemToBottomRow(new CButtonDelete(
			'Delete selected service?',
			url_param('form').url_param('serviceid').'&saction=1'
			));
	}
	$frmService->addItemToBottomRow(SPACE);

	$cb = new CButton('cancel',S_CANCEL);
	$cb->setType('button');
	$cb->setAction('javascript: self.close();');

	$frmService->addItemToBottomRow($cb);
	$frmService->show();
}
//---------------------------------------------  </FORM>  --------------------------------------------
?>
<?php

include_once('include/page_footer.php');

?>

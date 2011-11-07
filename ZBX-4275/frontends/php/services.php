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
require_once('include/services.inc.php');
require_once('include/triggers.inc.php');
require_once('include/html.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_IT_SERVICES';
$page['file'] = 'services.php';
$page['scripts'] = array();
$page['hist_arg'] = array();

include_once('include/page_header.php');
?>
<?php

//---------------------------------- CHECKS ------------------------------------

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"msg"=>		array(T_ZBX_STR, O_OPT,	 null,	null ,NULL),

// ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'hat'"),		NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),

	);

	check_fields($fields);

/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			CProfile::update('web.services.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'],PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

//--------------------------------------------------------------------------

	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array());


	$sql = 'SELECT DISTINCT s.serviceid, sl.servicedownid, sl_p.serviceupid as serviceupid, s.triggerid, '.
		' s.name as caption, s.algorithm, t.description, t.expression, s.sortorder, sl.linkid, s.showsla, s.goodsla, s.status '.
	' FROM services s '.
		' LEFT JOIN triggers t ON s.triggerid = t.triggerid '.
		' LEFT JOIN services_links sl ON  s.serviceid = sl.serviceupid and NOT(sl.soft=0) '.
		' LEFT JOIN services_links sl_p ON  s.serviceid = sl_p.servicedownid and sl_p.soft=0 '.
	' WHERE '.DBin_node('s.serviceid').
		' AND (t.triggerid IS NULL OR '.DBcondition('t.triggerid',$available_triggers).') '.
	' ORDER BY s.sortorder, sl_p.serviceupid, s.serviceid';

	$result=DBSelect($sql);

	$services = array();
	$row = array(
				'id' =>	0,
				'serviceid' => 0,
				'serviceupid' => 0,
				'caption' => S_ROOT_SMALL,
				'status' => SPACE,
				'algorithm' => SPACE,
				'description' => SPACE,
				'soft' => 0,
				'linkid'=>''
				);

	$services[0]=$row;

	while($row = DBFetch($result)){

		$row['id'] = $row['serviceid'];

		(empty($row['serviceupid']))?($row['serviceupid']='0'):('');
		(empty($row['triggerid']))?($row['description']='None'):($row['description']=expand_trigger_description($row['triggerid']));


			if(isset($services[$row['serviceid']])){
				$services[$row['serviceid']] = zbx_array_merge($services[$row['serviceid']],$row);
			} else {

				$services[$row['serviceid']] = $row;
			}

		if(isset($row['serviceupid']))
		$services[$row['serviceupid']]['childs'][] = array('id' => $row['serviceid'], 'soft' => 0, 'linkid' => 0);

		if(isset($row['servicedownid']))
		$services[$row['serviceid']]['childs'][] = array('id' => $row['servicedownid'], 'soft' => 1, 'linkid' => $row['linkid']);
	}

	$treeServ=array();
	createServiceTree($services,$treeServ); //return into $treeServ parametr

	//permission issue
	$treeServ = del_empty_nodes($treeServ);
	//----

	if(isset($_REQUEST['msg']) && !empty($_REQUEST['msg'])){
		show_messages(true,$_REQUEST['msg']);
	}

	//show_table_header(S_IT_SERVICES_BIG);

	$tree = new CTree('service_conf_tree', $treeServ,array(
		'caption' => S_SERVICE,
		'algorithm' => S_STATUS_CALCULATION,
		'description' => S_TRIGGER));

	if($tree){
		$serv_wdgt = new CWidget();
		$serv_wdgt->addPageHeader(S_IT_SERVICES_BIG, SPACE);
		$serv_wdgt->addItem($tree->getHTML());

		$serv_wdgt->show();
	}
	else {
		error(S_CANT_FORMAT_TREE);
	}


	$tr_ov_menu[] = array('test1',	null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
	$tr_ov_menu[] = array('test2',	null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));

include_once('include/page_footer.php');

?>

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

include_once "include/config.inc.php";
include_once "include/services.inc.php";

$page["title"] = "S_IT_SERVICES";
$page["file"] = "services.php";

include_once "include/page_header.php";


//---------------------------------- CHECKS ------------------------------------

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"msg"=>		array(T_ZBX_STR, O_OPT,	 null,	null ,NULL)
	);

	check_fields($fields);

//----------------------------------------------------------------------

	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT);

	if(isset($_REQUEST['serviceid']) && $_REQUEST['serviceid'] > 0){
		$query = "SELECT s.*,sl.linkid FROM services s LEFT JOIN triggers t on s.triggerid=t.triggerid ".
			" LEFT JOIN functions f on t.triggerid=f.triggerid LEFT JOIN items i on f.itemid=i.itemid ".
			" where (i.hostid is null or i.hostid not in (".$denyed_hosts.")) ".
			" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
			" and s.serviceid=".$_REQUEST["serviceid"];
		if( !($service = DBFetch(DBSelect($query))) ){
			access_deny();
		}
	}
//--------------------------------------------------------------------------

$query = 'SELECT distinct s.serviceid, sl.servicedownid, sl_p.serviceupid as serviceupid,
        s.name caption, s.algorithm, t.triggerid, s.sortorder, sl.linkid
		FROM services s
			LEFT JOIN triggers t ON s.triggerid = t.triggerid
	LEFT JOIN services_links sl ON  s.serviceid = sl.serviceupid and NOT(sl.soft=0)
	LEFT JOIN services_links sl_p ON  s.serviceid = sl_p.servicedownid and sl_p.soft=0
	WHERE '.DBid2nodeid("s.serviceid").'='.$ZBX_CURNODEID.'
	ORDER BY s.sortorder, sl.serviceupid, s.serviceid';

$result=DBSelect($query);

$services = array();
$row = array(
				'0' => 0,'serviceid' => 0,
				'1' => 0,'serviceupid' => 0,
				'2' => '','caption' => S_ROOT_SMALL,
				'3' => '','status' => SPACE,
				'4' => '','algorithm' => SPACE,
				'5' => '','description' => SPACE,
				'6' => 0,'soft' => 0,
				'7' => '','linkid'=>''
				);

$services[0]=$row;

while($row = DBFetch($result)){

		(empty($row['serviceupid']))?($row['serviceupid']='0'):('');
		(empty($row['triggerid']))?($row['description']='None'):($row['description']=expand_trigger_description($row['triggerid']));
		

			if(isset($services[$row['serviceid']])){
				$services[$row['serviceid']] = array_merge($services[$row['serviceid']],$row);
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

echo '<script src="js/services.js" type="text/javascript"></script>';

$p = new Ctag('p','yes');
$p->AddOption('align','center');
$p->AddOption('id','message');
(isset($_REQUEST['msg']))?($p->AddItem('<b>'.$_REQUEST['msg'].'</b>')):('');
$p->Show();

show_table_header(S_IT_SERVICES_BIG);

$tree = new CTree($treeServ,array('caption' => '<b>'.S_SERVICE.'</b>','algorithm' => '<b>'.S_STATUS_CALCULATION.'</b>', 'description' => '<b>'.S_TRIGGER.'</b>'));

if($tree){
	echo $tree->CreateJS();
	echo $tree->SimpleHTML();
} else {
	error(S_CANT_FORMAT_TREE);
}


$tr_ov_menu[] = array('test1',	null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
$tr_ov_menu[] = array('test2',	null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
$jsmenu = new CPUMenu($tr_ov_menu,170);
$jsmenu->InsertJavaScript();


include_once "include/page_footer.php";
?>

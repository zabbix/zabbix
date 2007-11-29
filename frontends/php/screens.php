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
	require_once "include/config.inc.php";
	require_once "include/graphs.inc.php";
	require_once "include/screens.inc.php";
	require_once 'include/nodes.inc.php';


	$page["title"] = "S_CUSTOM_SCREENS";
	$page["file"] = "screens.php";
	$page['hist_arg'] = array('config','elementid');
	$page['scripts'] = array('prototype.js','url.js','gmenu.js','scrollbar.js','sbox.js','sbinit.js'); //do not change order!!!

	$_REQUEST["fullscreen"] = get_request("fullscreen", 0);

	if($_REQUEST["fullscreen"])
	{
		define('ZBX_PAGE_NO_MENU', 1);
	}

	$_REQUEST['config'] = get_request('config',get_profile('web.screens.config',0));

	if( 1 != $_REQUEST['config'])
		define('ZBX_PAGE_DO_REFRESH', 1);
	
include_once "include/page_header.php";

?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"config"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	null), // 0 - screens, 1 - slides

		"groupid"=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		"hostid"=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),

		"elementid"=>	array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		"step"=>		array(T_ZBX_INT, O_OPT,  P_SYS,		BETWEEN(0,65535),NULL),
		"dec"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"inc"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"from"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"left"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"right"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"period"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),NULL),
		"stime"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		"action"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go'"),NULL),
		"reset"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		"fullscreen"=>	array(T_ZBX_INT, O_OPT,	P_SYS,		IN("0,1,2"),		NULL)
	);

	check_fields($fields);

	$config = $_REQUEST['config'] = get_request('config', 0);

	if( 2 != $_REQUEST["fullscreen"] )
		update_profile('web.screens.config', $_REQUEST['config']);

?>

<?php
	$_REQUEST["elementid"] = get_request("elementid",get_profile("web.screens.elementid", null));
	$_REQUEST["fullscreen"] = get_request("fullscreen", 0);

	if( 2 != $_REQUEST["fullscreen"] )
		update_profile("web.screens.elementid",$_REQUEST["elementid"]);

	$_REQUEST["period"] = get_request('period',get_profile('web.screens'.$_REQUEST['elementid'].'.period', ZBX_PERIOD_DEFAULT));
	if($_REQUEST["period"] >= ZBX_MIN_PERIOD)
	{
		update_profile('web.screens'.$_REQUEST['elementid'].'.period',$_REQUEST['period']);
	}
?>

<?php

	$text = array(S_SCREENS_BIG);

	$elementid = get_request('elementid', null);
	if($elementid <= 0) $elementid = null;

	$form = new CForm();
	$form->SetMethod('get');
	
	$form->AddVar("fullscreen",$_REQUEST["fullscreen"]);

	$cmbConfig = new CComboBox('config', $config, 'submit()');
	$cmbConfig->AddItem(0, S_SCREENS);
	$cmbConfig->AddItem(1, S_SLIDESHOWS);

	$form->AddItem($cmbConfig);

	$cmbElements = new CComboBox("elementid",$elementid,"submit()");
	unset($screen_correct);
	unset($first_screen);

	if( 0 == $config ){
		$result = DBselect('select screenid as elementid,name '.
				' from screens '.
				' where '.DBin_node('screenid').
				' order by name'
				);
		while($row=DBfetch($result)){
			if(!screen_accessiable($row["elementid"], PERM_READ_ONLY))
				continue;

			$cmbElements->AddItem(
					$row['elementid'],
					get_node_name_by_elid($row['elementid']).$row["name"]
					);
			if($elementid == $row["elementid"]) $element_correct = 1;
			if(!isset($first_element)) $first_element = $row["elementid"];
		}
	}
	else{
		$result = DBselect('select slideshowid as elementid,name '.
				' from slideshows '.
				' where '.DBin_node('slideshowid').
				' order by name'
				);
		while($row=DBfetch($result)){
			if(!slideshow_accessiable($row["elementid"], PERM_READ_ONLY))
				continue;

			$cmbElements->AddItem(
					$row['elementid'],
					get_node_name_by_elid($row['elementid']).$row['name']
					);
			if($elementid == $row["elementid"]) $element_correct = 1;
			if(!isset($first_element)) $first_element = $row["elementid"];
		}
	}

	if(!isset($element_correct) && isset($first_element)){
		$elementid = $first_element;
	}

	if(isset($elementid)){
		if(0 == $config){
			if(!screen_accessiable($elementid, PERM_READ_ONLY))
				access_deny();
			$element = get_screen_by_screenid($elementid);
		}
		else{
			if(!slideshow_accessiable($elementid, PERM_READ_ONLY))
				access_deny();
			$element = get_slideshow_by_slideshowid($elementid);
		}

		if( $element ){
			$url = "?elementid=".$elementid;
			if($_REQUEST["fullscreen"]==0) $url .= "&fullscreen=1";
			$text[] = array(nbsp(" / "),new CLink($element["name"], $url));
		}
		else{
			$elementid = null;
			update_profile("web.screens.elementid",0);
		}
	}

	if($cmbElements->ItemsCount() > 0)
		$form->AddItem($cmbElements);

	if((2 != $_REQUEST["fullscreen"]) && (0 == $config) && (check_dynamic_items($elementid))){
		if(!isset($_REQUEST["hostid"])){
			$_REQUEST["groupid"] = $_REQUEST["hostid"] = 0;
		}
		
		$options = array("allow_all_hosts","monitored_hosts","with_items");//, "always_select_first_host");
		if(!$ZBX_WITH_SUBNODES)	array_push($options,"only_current_node");
		
		validate_group_with_host(PERM_READ_ONLY,$options);
		
		$availiable_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());
		$availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());
		
		$r_form = new CForm();
		$r_form->SetMethod('get');
		if(isset($_REQUEST['fullscreen']))	$r_form->AddVar('fullscreen', $_REQUEST['fullscreen']);
		if(isset($_REQUEST['period']))	$r_form->AddVar('period', $_REQUEST['period']);
		if(isset($_REQUEST['stime']))	$r_form->AddVar('stime', $_REQUEST['stime']);
			
		$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit()');
		$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');
	
		$cmbGroup->AddItem(0,S_ALL_SMALL);
		$cmbHosts->AddItem(0,S_DEFAULT);
		

		$result=DBselect('SELECT DISTINCT g.groupid, g.name '.
					' FROM groups g, hosts_groups hg, hosts h, items i, graphs_items gi '.
					' WHERE g.groupid in ('.$availiable_groups.') '.
						' AND hg.groupid=g.groupid '.
						' AND h.status='.HOST_STATUS_MONITORED.
						' AND h.hostid=i.hostid '.
						' AND hg.hostid=h.hostid '.
					' ORDER BY g.name');
		while($row=DBfetch($result)){
			$cmbGroup->AddItem(
					$row['groupid'],
					get_node_name_by_elid($row['groupid']).$row["name"]
					);
		}
		
		$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
		
		if($_REQUEST['groupid'] > 0){
			$sql = ' SELECT distinct h.hostid,h.host '.
				' FROM hosts h,items i,hosts_groups hg, graphs_items gi '.
				' WHERE h.status='.HOST_STATUS_MONITORED.
					' AND h.hostid=i.hostid '.
					' AND hg.groupid='.$_REQUEST['groupid'].
					' AND hg.hostid=h.hostid '.
					' AND h.hostid IN ('.$availiable_hosts.') '.
				' ORDER BY h.host';
		}
		else{
			$sql = 'SELECT distinct h.hostid,h.host '.
				' FROM hosts h,items i, graphs_items gi '.
				' WHERE h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND h.hostid=i.hostid'.
					' AND h.hostid IN ('.$availiable_hosts.') '.
				' ORDER BY h.host';
		}
//SDI($sql);
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$cmbHosts->AddItem(
					$row['hostid'],
					get_node_name_by_elid($row['hostid']).$row['host']
					);
		}
	
		$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));	
		show_table_header($text,$form);
		show_table_header(null,$r_form);
	}
	else if(2 != $_REQUEST["fullscreen"]){
		show_table_header($text,$form);
	}
?>
<?php
	if(isset($elementid)){
		$effectiveperiod = navigation_bar_calc();
		if( 0 == $config ){
			$element = get_screen($elementid, 0, $effectiveperiod);
		}
		else{
			$element = get_slideshow($elementid, get_request('step', null), $effectiveperiod);
			zbx_add_post_js('if(isset(parent)) parent.resizeiframe("iframe");
							else resizeiframe("iframe");'."\n");
		}
		if($element){
			$element->Show();
		}
		
		$_REQUEST['elementid'] = $elementid;

		if( 2 != $_REQUEST["fullscreen"] ){
		
			$stime = time() - (31536000); // ~1year
			$bstime = time()-$effectiveperiod;
			
			if(isset($_REQUEST['stime'])){
				$bstime = $_REQUEST['stime'];
				$bstime = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
			}
			
 			$script = 	'scrollinit(0,0,0,'.$effectiveperiod.','.$stime.',0,'.$bstime.');
						 showgraphmenu("iframe");';
							
			zbx_add_post_js($script); 
			$img = new CImg('images/general/tree/O.gif','space','20','20');
			$img->Show();
			echo BR;
//			navigation_bar("screens.php",array('config','elementid'));
		}
	}
	else
	{
		echo unpack_object(new CTableInfo(
					0 == $config ?
						S_NO_SCREENS_DEFINED :
						S_NO_SLIDESHOWS_DEFINED
					));
	}
?>
<?php

include_once "include/page_footer.php";

?>

<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	
	$page["title"] = "S_NETWORK_MAPS";
	$page["file"] = "maps.php";
	$page['hist_arg'] = array('sysmapid');
	$page['scripts'] = array('prototype.js','url.js');
	
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
	
include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"sysmapid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,		NULL),
		"fullscreen"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("0,1"),	NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove'"),NULL)
	);

	check_fields($fields);

?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('sysmapid' == $_REQUEST['favobj']){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.sysmapids',$_REQUEST['favid'],$_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("sysmapid","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.sysmapids',$_REQUEST['favid'],ZBX_FAVORITES_ALL,$_REQUEST['favobj']);
				
				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){ add2favorites("sysmapid","'.$_REQUEST['favid'].'");}'."\n");
				}
			}
			
			if((PAGE_TYPE_JS == $page['type']) && $result){
				print('switchElementsClass("addrm_fav","iconminus","iconplus");');
			}
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
	
	$_REQUEST["sysmapid"] = get_request("sysmapid",get_profile("web.maps.sysmapid",0));

	$all_maps = array();
	
	$result = DBselect('SELECT sysmapid,name '.
						' FROM sysmaps '.
						' WHERE '.DBin_node('sysmapid').
						' ORDER BY name');
	while($row=DBfetch($result)){
		if(!sysmap_accessible($row["sysmapid"],PERM_READ_ONLY))
			continue;

		if(!isset($all_maps[0]))
			$all_maps[0] = $row['sysmapid'];

		$all_maps[$row['sysmapid']] = 
			get_node_name_by_elid($row['sysmapid']).
			$row['name'];
	}

	if(isset($_REQUEST["sysmapid"]) && (!isset($all_maps[$_REQUEST["sysmapid"]]) || $_REQUEST["sysmapid"] == 0)){
		if(count($all_maps)){
			$_REQUEST["sysmapid"] = $all_maps[0];
		}
		else{
			unset($_REQUEST["sysmapid"]);
		}
	}
	unset($all_maps[0]);
	
	if(isset($_REQUEST["sysmapid"])){
		update_profile("web.maps.sysmapid",$_REQUEST["sysmapid"]);
	}
?>
<?php
	$text = array(S_NETWORK_MAPS_BIG);
	if(isset($_REQUEST["sysmapid"])){
		$sysmap = get_sysmap_by_sysmapid($_REQUEST["sysmapid"]);

		array_push($text, nbsp(' / '), $all_maps[$_REQUEST["sysmapid"]]);
		
		if(infavorites('web.favorite.sysmapids',$_REQUEST['sysmapid'],'sysmapid')){
			$icon = new CDiv(SPACE,'iconminus');
			$icon->AddOption('title',S_REMOVE_FROM.' '.S_FAVORITES);
			$icon->AddAction('onclick',new CScript("javascript: rm4favorites('sysmapid','".$_REQUEST["sysmapid"]."',0);"));
		}
		else{
			$icon = new CDiv(SPACE,'iconplus');
			$icon->AddOption('title',S_ADD_TO.' '.S_FAVORITES);
			$icon->AddAction('onclick',new CScript("javascript: add2favorites('sysmapid','".$_REQUEST["sysmapid"]."');"));
		}
		$icon->AddOption('id','addrm_fav');
		
		$url = '?sysmapid='.$_REQUEST['sysmapid'].($_REQUEST['fullscreen']?'':'&fullscreen=1');

		$fs_icon = new CDiv(SPACE,'fullscreen');
		$fs_icon->AddOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
		$fs_icon->AddAction('onclick',new CScript("javascript: document.location = '".$url."';"));
	
		$icon_tab = new CTable();
		$icon_tab->AddRow(array($fs_icon,$icon,SPACE,$text));
		
		$text = $icon_tab;

	}

	$form = new CForm();
	$form->SetMethod('get');
	
	$form->AddVar("fullscreen",$_REQUEST["fullscreen"]);

	$cmbMaps = new CComboBox("sysmapid",get_request("sysmapid",0),"submit()");
	
	foreach($all_maps as $id => $name){
		$cmbMaps->AddItem($id, $name);
	}
	
	if($cmbMaps->ItemsCount()>0){
		$form->AddItem($cmbMaps);
	}

	show_table_header($text,$form);
?>
<?php
	$table = new CTable(S_NO_MAPS_DEFINED,"map");
	if(isset($_REQUEST["sysmapid"])){
		$action_map = get_action_map_by_sysmapid($_REQUEST["sysmapid"]);
		$table->AddRow($action_map);

		$imgMap = new CImg("map.php?noedit=1&sysmapid=".$_REQUEST["sysmapid"]);
		$imgMap->SetMap($action_map->GetName());
		$table->AddRow($imgMap);
	}
	$table->Show();
?>
<?php

include_once "include/page_footer.php";

?>

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
	require_once('include/maps.inc.php');

	$page['title'] = "S_NETWORK_MAPS";
	$page['file'] = 'maps.php';
	$page['hist_arg'] = array('sysmapid');
	$page['scripts'] = array('prototype.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'sysmapid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,		NULL),
		'fullscreen'=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN('0,1'),	NULL),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),

		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove'"),NULL)
	);

	check_fields($fields);

?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.maps.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		else if('sysmapid' == $_REQUEST['favobj']){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.sysmapids',$_REQUEST['favid'],$_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVOURITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("sysmapid","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.sysmapids',$_REQUEST['favid'],ZBX_FAVORITES_ALL,$_REQUEST['favobj']);

				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVOURITES.'";'."\n");
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

	$_REQUEST['sysmapid'] = get_request('sysmapid',get_profile('web.maps.sysmapid',0));

	$all_maps = array();

	$sql = 'SELECT sysmapid,name '.
			' FROM sysmaps '.
			' WHERE '.DBin_node('sysmapid').
			' ORDER BY name';
	$result = DBselect($sql);
	while($row=DBfetch($result)){
		if(!sysmap_accessible($row['sysmapid'],PERM_READ_ONLY))
			continue;

		if(!isset($all_maps[0]))
			$all_maps[0] = $row['sysmapid'];

		$all_maps[$row['sysmapid']] =
			get_node_name_by_elid($row['sysmapid'], null, ': ').
			$row['name'];
	}

	if(isset($_REQUEST['sysmapid']) && (!isset($all_maps[$_REQUEST['sysmapid']]) || $_REQUEST['sysmapid'] == 0)){
		if(count($all_maps)){
			$_REQUEST['sysmapid'] = $all_maps[0];
		}
		else{
			unset($_REQUEST['sysmapid']);
		}
	}
	unset($all_maps[0]);

	if(isset($_REQUEST['sysmapid'])){
		update_profile('web.maps.sysmapid',$_REQUEST['sysmapid']);
	}
?>
<?php
	$map_wdgt = new CWidget('hat_maps');

// HEADER
	$text = SPACE;
	if(isset($_REQUEST['sysmapid'])){
		$sysmap = get_sysmap_by_sysmapid($_REQUEST['sysmapid']);
		$text = $all_maps[$_REQUEST['sysmapid']];
	}

	$form = new CForm();
	$form->setMethod('get');

	$form->addVar('fullscreen',$_REQUEST['fullscreen']);

	$cmbMaps = new CComboBox('sysmapid',get_request('sysmapid',0),'submit()');

	foreach($all_maps as $id => $name){
		$cmbMaps->addItem($id, $name);
	}
//-------------------------
?>
<?php
	$table = new CTable(S_NO_MAPS_DEFINED,'map');
	if(isset($_REQUEST['sysmapid'])){
		$action_map = get_action_map_by_sysmapid($_REQUEST['sysmapid']);
		$table->addRow($action_map);

		$imgMap = new CImg('map.php?noedit=1&sysmapid='.$_REQUEST['sysmapid']);
		$imgMap->setMap($action_map->GetName());
		$table->addRow($imgMap);
	}

	$icon = null;
	$fs_icon = null;
	if(isset($_REQUEST['sysmapid'])){
		$sysmap = get_sysmap_by_sysmapid($_REQUEST['sysmapid']);

		$text = $all_maps[$_REQUEST['sysmapid']];

		if(infavorites('web.favorite.sysmapids',$_REQUEST['sysmapid'],'sysmapid')){
			$icon = new CDiv(SPACE,'iconminus');
			$icon->setAttribute('title',S_REMOVE_FROM.' '.S_FAVOURITES);
			$icon->addAction('onclick',new CJSscript("javascript: rm4favorites('sysmapid','".$_REQUEST["sysmapid"]."',0);"));
		}
		else{
			$icon = new CDiv(SPACE,'iconplus');
			$icon->setAttribute('title',S_ADD_TO.' '.S_FAVOURITES);
			$icon->addAction('onclick',new CJSscript("javascript: add2favorites('sysmapid','".$_REQUEST["sysmapid"]."');"));
		}
		$icon->setAttribute('id','addrm_fav');

		$url = '?sysmapid='.$_REQUEST['sysmapid'].($_REQUEST['fullscreen']?'':'&fullscreen=1');

		$fs_icon = new CDiv(SPACE,'fullscreen');
		$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
		$fs_icon->addAction('onclick',new CJSscript("javascript: document.location = '".$url."';"));
	}

	$map_wdgt->addPageHeader(S_NETWORK_MAPS_BIG,array($icon,$fs_icon));

	if($cmbMaps->itemsCount()>0){
		$form->addItem($cmbMaps);
		$map_wdgt->addHeader($text,$form);
	}

	$map_wdgt->addItem($table);

	$map_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>

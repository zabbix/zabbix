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

	$page['title'] = 'S_NETWORK_MAPS';
	$page['file'] = 'maps.php';
	$page['hist_arg'] = array('sysmapid');
	$page['scripts'] = array();

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}

	define('GET_PARAM_NAME', 'mapname');

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array(
		'sysmapid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,					NULL),
		GET_PARAM_NAME=>	array(T_ZBX_STR, O_OPT,	P_SYS,			null,					null),
		'fullscreen'=>		array(T_ZBX_INT, O_OPT,	P_SYS,			IN('0,1'),				NULL),
//ajax
		'favobj'=>			array(T_ZBX_STR, O_OPT, P_ACT,			NULL,					NULL),
		'favref'=>			array(T_ZBX_STR, O_OPT, P_ACT, 			NOT_EMPTY,				NULL),
		'favid'=>			array(T_ZBX_INT, O_OPT, P_ACT, 			NULL,					NULL),

		'state'=>			array(T_ZBX_INT, O_OPT, P_ACT,  		NOT_EMPTY,				NULL),
		'action'=>			array(T_ZBX_STR, O_OPT, P_ACT, 			IN("'add','remove'"),	NULL)
	);
	check_fields($fields);
?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			CProfile::update('web.maps.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
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
				$result = rm4favorites('web.favorite.sysmapids',$_REQUEST['favid'],$_REQUEST['favobj']);

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
		include_once('include/page_footer.php');
		exit();
	}

	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(),
		'select_selements' => API_OUTPUT_EXTEND,
		'preservekeys' => true,
	);
	$maps = CMap::get($options);
	$maps = zbx_toHash($maps, 'sysmapid');

	if($name = get_request(GET_PARAM_NAME)){
		unset($_REQUEST['sysmapid']);

		foreach($maps as $map){
			if(strcmp($map['name'], $name) == 0){
				$_REQUEST['sysmapid'] = $map['sysmapid'];
			}
		}
	}
	else if(!isset($_REQUEST['sysmapid'])){
		$_REQUEST['sysmapid'] = CProfile::get('web.maps.sysmapid');
		if(is_null($_REQUEST['sysmapid']) || !isset($maps[$_REQUEST['sysmapid']])){
			$first_map = reset($maps);
			$_REQUEST['sysmapid'] = $first_map['sysmapid'];
			if (!$first_map) {
				unset($_REQUEST['sysmapid']);
			}
			else {
				$_REQUEST['sysmapid'] = $first_map['sysmapid'];
			}
		}
	}


	if (isset($_REQUEST['sysmapid']) && !isset($maps[$_REQUEST['sysmapid']])) {
		show_error_message(S_NO_PERMISSIONS_OR_MAP_DOES_NOT_EXIST);
	}
	else{
		$map_wdgt = new CWidget('hat_maps');
		$table = new CTable(S_NO_MAPS_DEFINED, 'map');

		$icon = $fs_icon = null;

		if(!empty($maps)){
	// no profile record when get by name
			if(!isset($_REQUEST[GET_PARAM_NAME]))
				CProfile::update('web.maps.sysmapid', $_REQUEST['sysmapid'], PROFILE_TYPE_ID);

			$form = new CForm(null, 'get');
			$form->addVar('fullscreen', $_REQUEST['fullscreen']);
			$cmbMaps = new CComboBox('sysmapid', get_request('sysmapid', 0), 'submit()');
			order_result($maps, 'name');
			foreach($maps as $sysmapid => $map){
				$cmbMaps->addItem($sysmapid, get_node_name_by_elid($sysmapid, null, ': ').$map['name']);
			}
			$form->addItem($cmbMaps);

			$map_wdgt->addHeader($maps[$_REQUEST['sysmapid']]['name'], $form);

	// GET MAP PARENT MAPS {{{
			$parent_maps = array();
			foreach($maps as $sysmapid => $map){
				foreach($map['selements'] as $enum => $selement){
					if(($selement['elementid'] == $_REQUEST['sysmapid']) && ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP)){
						$parent_maps[] = SPACE.SPACE;
						$parent_maps[] = new Clink($map['name'], 'maps.php?sysmapid='.$map['sysmapid'].'&fullscreen='.$_REQUEST['fullscreen']);
						break;
					}
				}
			}

			if(!empty($parent_maps)){
				array_unshift($parent_maps, S_UPPER_LEVEL_MAPS.':');
				$map_wdgt->addHeader($parent_maps);
			}
	// }}} GET MAP PARENT MAPS

			$action_map = getActionMapBySysmap($maps[$_REQUEST['sysmapid']]);

			$table->addRow($action_map);

			$imgMap = new CImg('map.php?sysmapid='.$_REQUEST['sysmapid']);
			$imgMap->setMap($action_map->getName());
			$table->addRow($imgMap);

			$icon = get_icon('favourite', array(
				'fav' => 'web.favorite.sysmapids',
				'elname' => 'sysmapid',
				'elid' => $_REQUEST['sysmapid'],
			));
			$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
		}

		$map_wdgt->addItem($table);
		$map_wdgt->addPageHeader(S_NETWORK_MAPS_BIG, array($icon, $fs_icon));
		$map_wdgt->show();
	}
?>
<?php
include_once('include/page_footer.php');
?>

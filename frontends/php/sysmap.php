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
	require_once "include/maps.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_CONFIGURATION_OF_NETWORK_MAPS";
	$page["file"] = "sysmap.php";
	$page['scripts'] = array('scriptaculous/scriptaculous.js','updater.js','cmap.js');
	$page["type"] = detect_page_type();

include_once "include/page_header.php";

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"sysmapid"=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,NULL),

		"selementid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		NULL),
		"elementid"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID.'{}>0',	'isset({save})'),
		"elementtype"=>	array(T_ZBX_INT, O_OPT,  NULL, IN("0,1,2,3"),	'isset({save})'),
		"label"=>		array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,	'isset({save})'),
		"x"=>			array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		"y"=>           array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		"iconid_off"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		"iconid_on"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		"iconid_unknown"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		"iconid_maintenance"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		"url"=>				array(T_ZBX_STR, O_OPT,  NULL, NULL,		'isset({save})'),
		"label_location"=>	array(T_ZBX_INT, O_OPT, NULL,	IN("-1,0,1,2,3"),'isset({save})'),

		"linkid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"selementid1"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID.'{}!={selementid2}','isset({save_link})'),
		"selementid2"=> array(T_ZBX_INT, O_OPT,  NULL, DB_ID.'{}!={selementid1}','isset({save_link})'),
		"triggerid"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,'isset({save_link})'),
		"drawtype_off"=>array(T_ZBX_INT, O_OPT,  NULL, IN("0,1,2,3,4"),'isset({save_link})'),
		"drawtype_on"=>	array(T_ZBX_INT, O_OPT,  NULL, IN("0,1,2,3,4"),'isset({save_link})'),
		"color_off"=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,'isset({save_link})'),
		"color_on"=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,'isset({save_link})'),

/* actions */
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save_link"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,	NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  null,	NULL),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,	null),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'form','list','get','get_img','save'"),NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),
		
		'elements'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID, NULL),
		'links'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID, NULL),
	);

	check_fields($fields);
// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['favobj'])){
		ob_flush();
		if('sysmap' == $_REQUEST['favobj']){
			$sysmapid = get_request('sysmapid',0);
			$cmapid = get_request('favid',0);

			switch($_REQUEST['action']){
				case 'get':
					$action = '';

					$sql = 'SELECT * FROM sysmaps  WHERE sysmapid='.$sysmapid;
					$map = DBfetch(DBselect($sql));
					$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.melement["label_location"]='.$map.'; '."\n";
										
					$sql = 'SELECT * FROM sysmaps_elements se WHERE se.sysmapid='.$sysmapid;
					$res = DBselect($sql);
					while($element = DBfetch($res)){
						foreach($element as $key => $value){
							if(is_int($key)) unset($element[$key]);
						}
//						$element['image'] = get_base64_icon($element);
						$element['image'] = get_element_iconid($element);
						$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.add_element('.zbx_jsvalue($element).'); '."\n";
					}
					
					$sql = 'SELECT * FROM sysmaps_links sl WHERE sl.sysmapid='.$sysmapid;
					$res = DBselect($sql);
					while($link = DBfetch($res)){
						foreach($link as $key => $value){
							if(is_int($key)) unset($link[$key]);
						}

						$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.add_link('.zbx_jsvalue($link).'); '."\n";
					}
					$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg(); '."\n";
					$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_elements_icon(); '."\n";

					print($action);
					break;
				case 'save':
					$elements = get_request('elements', array());
					$links = get_request('links', array());

					$db_elementids = array();
					$res = DBselect('SELECT selementid FROM sysmaps_elements WHERE sysmapid='.$sysmapid);
					while($db_element = DBfetch($res)){
						$db_elementids[$db_element['selementid']] = $db_element['selementid'];
					}
										
					foreach($elements as $id => $element){
						if($element['elementid'] == 0){
							$element['elementtype'] = SYSMAP_ELEMENT_TYPE_UNDEFINED;
						}
						
						if(uint_in_array($element['selementid'], $db_elementids)){
							$result=update_sysmap_element($element['selementid'],$sysmapid,$element['elementid'],
								$element['elementtype'],$element['label'],$element['x'],$element['y'],
								$element['iconid_off'],$element['iconid_unknown'],$element['iconid_on'],$element['iconid_maintenance'],
								$element['url'],$element['label_location']);
							unset($db_elementids[$element['selementid']]);
						}
						else{
							$selementid=add_element_to_sysmap($sysmapid,$element['elementid'],
								$element['elementtype'],$element['label'],$element['x'],$element['y'],
								$element['iconid_off'],$element['iconid_unknown'],$element['iconid_on'],$element['iconid_maintenance'],
								$element['url'],$element['label_location']);
							
							foreach($links as $id => $link){
								if($link['selementid1'] == $element['selementid']) $links[$id]['selementid1']=$selementid;
								else if($link['selementid2'] == $element['selementid']) $links[$id]['selementid2']=$selementid;
							}
						}
					}

					foreach($db_elementids as $id => $elementid){
						delete_sysmaps_element($elementid);
					}

					$db_linkids = array();
					$res = DBselect('SELECT linkid FROM sysmaps_links WHERE sysmapid='.$sysmapid);
					while($db_link = DBfetch($res)){
						$db_linkids[$db_link['linkid']] = $db_link['linkid'];
					}
					
					foreach($links as $id => $link){
						if(uint_in_array($link['linkid'], $db_linkids)){
							$result=update_link($link['linkid'],$sysmapid,$link['selementid1'],$link['selementid2'],
								$link['triggerid'],	$link['drawtype_off'],$link['color_off'],
								$link['drawtype_on'],$link['color_on']);
							unset($db_linkids[$link['linkid']]);
						}
						else{
							$result=add_link($sysmapid,$link['selementid1'],$link['selementid2'],
								$link['triggerid'],	$link['drawtype_off'],$link['color_off'],
								$link['drawtype_on'],$link['color_on']);
						}
					}
					
					foreach($db_linkids as $id => $linkid){
						delete_link($linkid);
					}
					
					print('location.href = "sysmaps.php"');
					break;
			}
		}
		
		if('elements' == $_REQUEST['favobj']){
			$sysmapid = get_request('sysmapid',0);
			$cmapid = get_request('favid',0);
			
			switch($_REQUEST['action']){
				case 'get_img':
					$elements = get_request('elements',array());
					
					if(!empty($elements)){
						$element = $elements[0];
//						$element['image'] = get_base64_icon($element);
						$element['image'] = get_element_iconid($element);

						$action = '';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.add_element('.zbx_jsvalue($element).',1);';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg();';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.show_element_list();';
						
						print($action);
					}
					else{
						print('ZBX_SYSMAPS['.$cmapid.'].map.info("Get Img: Element data not found!"); ');
					}
					break;
			}
		}
		
		if('links' == $_REQUEST['favobj']){
			switch($_REQUEST['action']){
			}
		}
	}	
	
	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
?>
<?php
	show_table_header("CONFIGURATION OF NETWORK MAP");
	if(!sysmap_accessiable($_REQUEST["sysmapid"],PERM_READ_WRITE)) access_deny();
	
	$sysmap = DBfetch(DBselect('SELECT * FROM sysmaps WHERE sysmapid='.$_REQUEST['sysmapid']));
?>
<?php
	echo BR;
	$map=get_sysmap_by_sysmapid($_REQUEST["sysmapid"]);
	
// ELEMENTS

	$el_add = new CDiv(SPACE,'iconplus');
	$el_add->addOption('title',S_ADD_ELEMENT);
	$el_add->addOption('id','element_add');
	
	$el_rmv = new CDiv(SPACE,'iconminus');
	$el_rmv->addOption('title',S_REMOVE_ELEMENT);
	$el_rmv->addOption('id','element_rmv');
						
//-----------------

// CONNECTORS 
//		echo BR;
//		show_table_header("CONNECTORS", new CButton("form","Create connection","return Redirect('".$page["file"]."?form=add_link".url_param("sysmapid")."');"));

//		$table->Show();

	$cn_add = new CDiv(SPACE,'iconplus');
	$cn_add->addOption('title',S_ADD_LINK);
	$cn_add->addOption('id','link_add');
	
	$cn_rmv = new CDiv(SPACE,'iconminus');
	$cn_rmv->addOption('title',S_REMOVE_LINK);
	$cn_rmv->addOption('id','link_rmv');

	$elcn_tab = new CTable();
	$elcn_tab->addRow(array(bold('E'),bold('L')));
	$elcn_tab->addRow(array($el_add,$cn_add));
	$elcn_tab->addRow(array($el_rmv,$cn_rmv));

	$td = new CCol($elcn_tab);
	$td->addOption('valign','top');
//------------------------\

	$save_btn = new CButton('save',S_SAVE);
	$save_btn->addOption('id','sysmap_save');
	
	$elcn_tab = new CTable();
	$elcn_tab->addRow(array(S_ELEMENT.'[',$el_add,$el_rmv,']',SPACE,SPACE,S_LINK.'[',$cn_add,$cn_rmv,']'));
//	show_table_header($map['name'], $save_btn);
	show_table_header($elcn_tab, $save_btn);


	$sysmap_img = new CImg('images/general/tree/o.gif','sysmap');
	$sysmap_img->addOption('id', 'sysmap_img');	
	
	$table = new CTable(NULL,'map');
//	$table->addRow(array($td, $sysmap_img));
	$table->addRow($sysmap_img);
	$table->Show();
	
	$container = new CDiv(null);
	$container->addOption('id','sysmap_cnt');
	$container->addOption('style','position: absolute;');
	$container->Show();
	
	zbx_add_post_js('create_map("sysmap_cnt", "'.$sysmap['sysmapid'].'");');
	
	insert_js(get_element_form_menu());
	insert_js(get_link_form_menu());
	
	$jsmenu = new CPUMenu(null,200);
	$jsmenu->InsertJavaScript();

?>
<?php
	
include_once "include/page_footer.php";

?>

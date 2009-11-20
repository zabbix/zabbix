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
require_once('include/forms.inc.php');

$page['title'] = "S_CONFIGURATION_OF_NETWORK_MAPS";
$page['file'] = 'sysmap.php';
$page['hist_arg'] = array('sysmapid');
$page['scripts'] = array('scriptaculous.js?load=effects,dragdrop','pmaster.js','class.cmap.js');
$page['type'] = detect_page_type();

include_once('include/page_header.php');
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'sysmapid'=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,NULL),

		'selementid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		NULL),
		'elementid'=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,	'isset({save})'),
		'elementtype'=>	array(T_ZBX_INT, O_OPT,  NULL, IN('0,1,2,3,4'),	'isset({save})'),
		'label'=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,	'isset({save})'),
		'x'=>		array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		'y'=>           array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		'iconid_off'=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		'iconid_on'=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		'iconid_unknown'=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		'iconid_disabled'=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		'url'=>		array(T_ZBX_STR, O_OPT,  NULL, NULL,		'isset({save})'),
		'label_location'=>array(T_ZBX_INT, O_OPT, NULL,	IN('-1,0,1,2,3'),'isset({save})'),

		'linkid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		'selementid1'=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID.'{}!={selementid2}','isset({save_link})'),
		'selementid2'=> array(T_ZBX_INT, O_OPT,  NULL, DB_ID.'{}!={selementid1}','isset({save_link})'),
		'triggers'=>	array(T_ZBX_STR, O_OPT,  NULL, null,null),
		'drawtype'=>array(T_ZBX_INT, O_OPT,  NULL, IN('0,1,2,3,4'),'isset({save_link})'),
		'color'=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,'isset({save_link})'),

// actions 
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'save_link'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

// other
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,	NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  null,	NULL),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,	null),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'form','list','get','get_img','new_selement','save'"),NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),
		
		'selements'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID, NULL),
		'links'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID, NULL),
	);

	check_fields($fields);

?>
<?php
// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['favobj'])){
		@ob_flush();
		$json = new CJSON();
		if('sysmap' == $_REQUEST['favobj']){
			$sysmapid = get_request('sysmapid',0);
			$cmapid = get_request('favid',0);

			switch($_REQUEST['action']){
				case 'get':
					$action = '';

					$options = array('sysmapids'=> $sysmapid, 'extendoutput'=>1, 'select_selements'=>1, 'select_links'=>1);
					$sysmaps = CMap::get($options);
					$db_map = reset($sysmaps);

					$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.mselement["label_location"]='.$db_map['label_location'].'; '."\n";

					foreach($db_map['selements'] as $snum => $selement){
						foreach($selement as $key => $value){
							if(is_int($key)) unset($selement[$key]);
						}
//						$element['image'] = get_base64_icon($element);
						$selement['image'] = get_selement_iconid($selement);
						$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.add_selement('.zbx_jsvalue($selement).'); '."\n";
					}

					foreach($db_map['links'] as $enum => $link){
						foreach($link as $key => $value){
							if(is_int($key)) unset($link[$key]);
						}

						$description = S_SELECT;
						foreach($link['linktriggers'] as $lnum => $linktrigger){
							$hosts = get_hosts_by_triggerid($linktrigger['triggerid']);
							if($host = DBfetch($hosts)){
								$description = $host['host'].':'.expand_trigger_description($linktrigger['triggerid']);
							}

							$link['linktriggers'][$lnum]['desc_exp'] = $description;
						}

						$link['tr_desc'] = $description;
						
						$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.add_link('.zbx_jsvalue($link).'); '."\n";
					}

					$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg(); '."\n";
					$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_selements_icon(); '."\n";

					print($action);
					break;
				case 'save':
					$selements = get_request('selements', '[]');
					$selements = $json->decode($selements, true);

					$links = get_request('links', '[]');
					$links = $json->decode($links, true);

					$db_selementids = array();
					$res = DBselect('SELECT selementid FROM sysmaps_elements WHERE sysmapid='.$sysmapid);
					while($db_selement = DBfetch($res)){
						$db_selementids[$db_selement['selementid']] = $db_selement['selementid'];
					}
										
					foreach($selements as $id => $selement){
						if($selement['elementid'] == 0){
							$selement['elementtype'] = SYSMAP_ELEMENT_TYPE_UNDEFINED;
						}
						
						if(uint_in_array($selement['selementid'], $db_selementids)){
							$result=update_sysmap_element($selement['selementid'],$sysmapid,$selement['elementid'],
								$selement['elementtype'],$selement['label'],$selement['x'],$selement['y'],
								$selement['iconid_off'],$selement['iconid_unknown'],$selement['iconid_on'],$selement['iconid_maintenance'],
								$selement['url'],$selement['label_location']);
							unset($db_selementids[$selement['selementid']]);
						}
						else{
							$selementid=add_element_to_sysmap($sysmapid,$selement['elementid'],
								$selement['elementtype'],$selement['label'],$selement['x'],$selement['y'],
								$selement['iconid_off'],$selement['iconid_unknown'],$selement['iconid_on'],$selement['iconid_maintenance'],
								$selement['url'],$selement['label_location']);
							
							foreach($links as $id => $link){
								if($link['selementid1'] == $selement['selementid']) $links[$id]['selementid1']=$selementid;
								else if($link['selementid2'] == $selement['selementid']) $links[$id]['selementid2']=$selementid;
							}
						}
					}

					foreach($db_selementids as $id => $selementid){
						delete_sysmaps_element($selementid);
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

		if('selements' == $_REQUEST['favobj']){
			$sysmapid = get_request('sysmapid',0);
			$cmapid = get_request('favid',0);
			
			switch($_REQUEST['action']){
				case 'get_img':
					$selements = get_request('selements', '[]');
					$selements = $json->decode($selements, true);

					if(!empty($selements)){
						$selement = reset($selements);

//						$selement['image'] = get_base64_icon($element);
						$selement['image'] = get_selement_iconid($selement);

						$action = '';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.add_selement('.zbx_jsvalue($selement).',1);';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg();';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.show_selement_list();';
						
						print($action);
					}
					else{
						print('ZBX_SYSMAPS['.$cmapid.'].map.info("Get Img: Element data not found!"); ');
					}
				break;
				case 'new_selement':
					$sql = 'SELECT i.* FROM images i WHERE '.dbin_node('i.imageid', false).' AND i.imagetype='.IMAGE_TYPE_ICON;
					$default_icon = DBfetch(DBselect($sql,1));

					$selements = get_request('selements', '[]');
					$selements = $json->decode($selements, true);
					if(!empty($selements)){
						$selement = reset($selements);

						$selement['iconid_off']			= $default_icon['imageid'];
						$selement['iconid_on']			= $default_icon['imageid'];
						$selement['iconid_unknown']		= $default_icon['imageid'];
						$selement['iconid_maintenance']	= $default_icon['imageid'];
						$selement['iconid_disabled']	= $default_icon['imageid'];

//						$selement['image'] = get_base64_icon($element);
						$selement['image'] = get_selement_iconid($selement);

						$action = '';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.add_selement('.zbx_jsvalue($selement).',1);';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg();';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.show_selement_list();';

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
	show_table_header(S_CONFIGURATION_OF_NETWORK_MAPS_BIG);

	if(!sysmap_accessible($_REQUEST['sysmapid'],PERM_READ_WRITE)) access_deny();

	$sysmap = DBfetch(DBselect('select * from sysmaps where sysmapid='.$_REQUEST['sysmapid']));
?>
<?php
	echo SBR;
	$map = get_sysmap_by_sysmapid($_REQUEST['sysmapid']);
	
// ELEMENTS

	$el_add = new CDiv(SPACE,'iconplus');
	$el_add->setAttribute('title',S_ADD_ELEMENT);
	$el_add->setAttribute('id','selement_add');
	
	$el_rmv = new CDiv(SPACE,'iconminus');
	$el_rmv->setAttribute('title',S_REMOVE_ELEMENT);
	$el_rmv->setAttribute('id','selement_rmv');
						
//-----------------

// CONNECTORS 
//		echo BR;
//		show_table_header("CONNECTORS", new CButton("form","Create connection","return Redirect('".$page["file"]."?form=add_link".url_param("sysmapid")."');"));

//		$table->Show();

	$cn_add = new CDiv(SPACE,'iconplus');
	$cn_add->setAttribute('title',S_ADD_LINK);
	$cn_add->setAttribute('id','link_add');
	
	$cn_rmv = new CDiv(SPACE,'iconminus');
	$cn_rmv->setAttribute('title',S_REMOVE_LINK);
	$cn_rmv->setAttribute('id','link_rmv');

	$elcn_tab = new CTable();
	$elcn_tab->addRow(array(bold('E'),bold('L')));
	$elcn_tab->addRow(array($el_add,$cn_add));
	$elcn_tab->addRow(array($el_rmv,$cn_rmv));

	$td = new CCol($elcn_tab);
	$td->setAttribute('valign','top');
//------------------------\

	$save_btn = new CButton('save',S_SAVE);
	$save_btn->setAttribute('id','sysmap_save');
	
	$elcn_tab = new CTable(null,'textblackwhite');
	$elcn_tab->addRow(array(S_ELEMENT.'[',$el_add,$el_rmv,']',SPACE,SPACE,S_LINK.'[',$cn_add,$cn_rmv,']'));
//	show_table_header($map['name'], $save_btn);
	show_table_header($elcn_tab, $save_btn);


	$sysmap_img = new CImg('images/general/tree/zero.gif','sysmap');
	$sysmap_img->setAttribute('id', 'sysmap_img');	
	
	$table = new CTable(NULL,'map');
//	$table->addRow(array($td, $sysmap_img));
	$table->addRow($sysmap_img);
	$table->Show();
	
	$container = new CDiv(null);
	$container->setAttribute('id','sysmap_cnt');
	$container->setAttribute('style','position: absolute;');
	$container->Show();
	
	zbx_add_post_js('create_map("sysmap_cnt", "'.$sysmap['sysmapid'].'");');
	
	insert_js(get_selement_form_menu());
	insert_js(get_link_form_menu());
	
	$jsmenu = new CPUMenu(null,200,1);
	$jsmenu->InsertJavaScript();
?>
<?php

include_once('include/page_footer.php');

?>
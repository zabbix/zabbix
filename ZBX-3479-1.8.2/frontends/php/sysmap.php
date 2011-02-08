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

$page['title'] = 'S_CONFIGURATION_OF_NETWORK_MAPS';
$page['file'] = 'sysmap.php';
$page['hist_arg'] = array('sysmapid');
$page['scripts'] = array('scriptaculous.js?load=effects,dragdrop','class.cmap.js');
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

		$json = new CJSON();
		if('sysmap' == $_REQUEST['favobj']){
			$sysmapid = get_request('sysmapid',0);
			$cmapid = get_request('favid',0);

			switch($_REQUEST['action']){
				case 'get':
					$action = '';

					$options = array(
						'sysmapids'=> $sysmapid,
						'editable'=>1,
						'extendoutput'=>1,
						'select_selements'=>1,
						'select_links'=>1
					);

					$sysmaps = CMap::get($options);
					$db_map = reset($sysmaps);

					expandMapLabels($db_map);

					$expandProblem = ($db_map['highlight'] > 1)? 0 : 1;
					$map_info = getSelementsInfo($db_map, $expandProblem);
//SDII($db_map);
					add_elementNames($db_map['selements']);

					$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.mselement["label_location"]='.$db_map['label_location'].'; '."\n";

					foreach($db_map['selements'] as $snum => $selement){
						$info = $map_info[$selement['selementid']];
//						$element['image'] = get_base64_icon($element);
						$selement['image'] = get_selement_iconid($selement, $info);
						$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.add_selement('.zbx_jsvalue($selement).'); '."\n";
					}

					foreach($db_map['links'] as $enum => $link){
						foreach($link as $key => $value){
							if(is_int($key)) unset($link[$key]);
						}

						$link['linktriggers'] = zbx_toHash($link['linktriggers'], 'linktriggerid');
						foreach($link['linktriggers'] as $lnum => $linktrigger){
							$hosts = get_hosts_by_triggerid($linktrigger['triggerid']);
							if($host = DBfetch($hosts)){
								$description = $host['host'].':'.expand_trigger_description($linktrigger['triggerid']);
							}

							$link['linktriggers'][$lnum]['desc_exp'] = $description;
						}

						$action .= 'ZBX_SYSMAPS['.$cmapid.'].map.add_link('.zbx_jsvalue($link).'); '."\n";
					}

					$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg(); '."\n";
					$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_selements_icon(); '."\n";

					print($action);
					break;
				case 'save':
					$options = array(
							'sysmapids'=> $sysmapid,
							'editable'=>1,
							'extendoutput'=>1,
							'select_selements'=>1,
							'select_links'=>1
						);
					$sysmaps = CMap::get($options);
					if(empty($sysmaps)) print('alert("Access denied!");');

					$selements = get_request('selements', '[]');
					$selements = $json->decode($selements, true);

					$links = get_request('links', '[]');
					$links = $json->decode($links, true);

					@ob_start();

					try{
						$db_selementids = array();
						$res = DBselect('SELECT selementid FROM sysmaps_elements WHERE sysmapid='.$sysmapid);
						while($db_selement = DBfetch($res)){
							$db_selementids[$db_selement['selementid']] = $db_selement['selementid'];
						}

						$transaction = DBstart();

						foreach($selements as $id => $selement){
							if($selement['elementid'] == 0){
								$selement['elementtype'] = SYSMAP_ELEMENT_TYPE_IMAGE;
							}

							if($selement['iconid_off'] == 0){
								throw new Exception('Cannot save map. Map element "'.$selement['label'].'" contains no icon.');
							}
							if(isset($selement['new'])){
								$selement['sysmapid'] = $sysmapid;
								$selementid = add_element_to_sysmap($selement);

								foreach($links as $id => $link){
									if($link['selementid1'] == $selement['selementid']) $links[$id]['selementid1']=$selementid;
									else if($link['selementid2'] == $selement['selementid']) $links[$id]['selementid2']=$selementid;
								}
							}
							else{
//SDII($selement);
								$selement['sysmapid'] = $sysmapid;
								$result = update_sysmap_element($selement);
								unset($db_selementids[$selement['selementid']]);
							}
						}

						delete_sysmaps_element($db_selementids);

						$db_linkids = array();
						$res = DBselect('SELECT linkid FROM sysmaps_links WHERE sysmapid='.$sysmapid);
						while($db_link = DBfetch($res)){
							$db_linkids[$db_link['linkid']] = $db_link['linkid'];
						}

						foreach($links as $id => $link){
							if(isset($link['new'])){
								$link['sysmapid'] = $sysmapid;
								$result = add_link($link);
							}
							else{
								$link['sysmapid'] = $sysmapid;
								$result = update_link($link);
								unset($db_linkids[$link['linkid']]);
							}
						}

						delete_link($db_linkids);

						$result = DBend(true);

						if($result)
							print('if(Confirm("'.S_MAP_SAVED_RETURN_Q.'")){ location.href = "sysmaps.php"; }');
						else
							throw new Exception(S_MAP_SAVE_OPERATION_FAILED."\n\r");
					}
					catch(Exception $e){
						if(isset($transaction)) DBend(false);
						$msg =  $e->getMessage()."\n\r";

						ob_clean();
						print('alert('.zbx_jsvalue($msg).');');
					}
					@ob_flush();
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
						$selement['label_expanded'] = expand_map_element_label_by_data($selement);

						$action = '';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.add_selement('.zbx_jsvalue($selement).',1);';
//						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg();';

						print($action);
					}
					else{
						print('ZBX_SYSMAPS['.$cmapid.'].map.info("'.S_GET_IMG_ELEMENT_DATA_NOT_FOUND.'"); ');
					}
				break;
				case 'new_selement':
					$default_icon = get_default_image(false);

					$selements = get_request('selements', '[]');
					$selements = $json->decode($selements, true);
					if(!empty($selements)){
						$selement = reset($selements);

						$selement['iconid_off']	= $default_icon['imageid'];

//						$selement['image'] = get_base64_icon($element);
						$selement['image'] = get_selement_iconid($selement);

						$action = '';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.add_selement('.zbx_jsvalue($selement).',1);';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.update_mapimg();';
						$action.= 'ZBX_SYSMAPS['.$cmapid.'].map.show_selement_list();';

						print($action);
					}
					else{
						print('ZBX_SYSMAPS['.$cmapid.'].map.info("'.S_GET_IMG_ELEMENT_DATA_NOT_FOUND.'"); ');
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
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php

	show_table_header(S_CONFIGURATION_OF_NETWORK_MAPS_BIG);

	if(isset($_REQUEST['sysmapid'])){
		$options = array(
			'sysmapids' => $_REQUEST['sysmapid'],
			'editable' => 1,
			'extendoutput' => 1,
		);

		$maps = CMap::get($options);

		if(empty($maps)) access_deny();
		else $sysmap = reset($maps);
	}

?>
<?php
	echo SBR;

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

	$elcn_tab = new CTable(null,'textwhite');
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

	$jsLocale = array(
			'S_EDIT_MAP_ELEMENT',
			'S_TYPE','S_LABEL',
			'S_LABEL_LOCATION','S_HOST',
			'S_MAP','S_TRIGGER','S_SELECT',
			'S_HOST_GROUP','S_IMAGE','S_ICON_OK',
			'S_ICON_PROBLEM','S_ICON_UNKNOWN',
			'S_ICON_MAINTENANCE','S_ICON_DISABLED','S_ICON_DEFAULT',
			'S_COORDINATE_X','S_COORDINATE_Y',
			'S_URL','S_BOTTOM','S_TOP','S_LEFT','S_RIGHT','S_DEFAULT',
			'S_APPLY','S_REMOVE','S_CLOSE',
			'S_MAP_ELEMENTS','S_MAP_LINKS','S_CONNECTORS',
			'S_ELEMENT','S_LINK_STATUS_INDICATOR',
			'S_LINK','S_EDIT_CONNECTOR','S_TRIGGERS','S_COLOR',
			'S_ADD','S_TYPE_OK','S_COLOR_OK','S_LINK_INDICATORS',
			'S_TYPE_PROBLEM','S_COLOR_PROBLEM','S_DESCRIPTION',
			'S_LINE','S_BOLD_LINE','S_DOT','S_DASHED_LINE','S_USE_ADVANCED_ICONS'
		);

	zbx_addJSLocale($jsLocale);
	insert_js(get_selement_icons());
	insert_show_color_picker_javascript();

	zbx_add_post_js('create_map("sysmap_cnt", "'.$sysmap['sysmapid'].'");');
?>
<?php

include_once('include/page_footer.php');

?>

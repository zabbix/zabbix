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
	require_once 'include/config.inc.php';
	require_once 'include/graphs.inc.php';
	require_once 'include/screens.inc.php';
	require_once 'include/blocks.inc.php';
	require_once 'include/nodes.inc.php';


	$page['title'] = "S_CUSTOM_SCREENS";
	$page['file'] = 'slides.php';
	$page['hist_arg'] = array('config','elementid');
	$page['scripts'] = array('gmenu.js','scrollbar.js','sbox.js','sbinit.js','pmaster.js','menu_scripts.js'); //do not change order!!!

	$_REQUEST['config'] = get_request('config',get_profile('web.screens.config',0));

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	if((1 != $_REQUEST['config']) && (PAGE_TYPE_HTML == $page['type'])){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
	
include_once 'include/page_header.php';

?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),	null), // 0 - screens, 1 - slides

		'groupid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		'hostid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		
// STATUS OF TRIGGER
		'tr_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
		'tr_hostid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),


		'elementid'=>	array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		'step'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		BETWEEN(0,65535),NULL),
		'period'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	null,NULL),
		'stime'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),

		'reset'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,		IN('0,1,2'),		NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,			null),
		'pmasterid'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	null,			NULL),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove'"),NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),
		'upd_counter'=> array(T_ZBX_INT, O_OPT, P_ACT,  null,		null),
	);

	check_fields($fields);

	if(isset($_REQUEST['favobj'])){
		$_REQUEST['pmasterid'] = get_request('pmasterid','mainpage');
		
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.screens.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		
		if(in_array($_REQUEST['favobj'],array('screenid','slideshowid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.screenids',$_REQUEST['favid'],$_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("'.$_REQUEST['favobj'].'","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.screenids',$_REQUEST['favid'],ZBX_FAVORITES_ALL,$_REQUEST['favobj']);
				
				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){ add2favorites("'.$_REQUEST['favobj'].'","'.$_REQUEST['favid'].'");}'."\n");
				}
			}			

			if((PAGE_TYPE_JS == $page['type']) && $result){
				print('switchElementsClass("addrm_fav","iconminus","iconplus");');
			}
		}
		
		if('refresh' == $_REQUEST['favobj']){
			switch($_REQUEST['favid']){
				case 'hat_slides':
					$elementid = $_REQUEST['elementid'];

					$effectiveperiod = navigation_bar_calc();
					$step = get_request('upd_counter');
//					$element = new CTableInfo(S_NO_SLIDESHOWS_DEFINED);

					$element = get_slideshow($elementid, $step, $effectiveperiod);
					$element->Show();
					
//					$element = get_slideshow($elementid, get_request('step', null), $effectiveperiod);
					break;
			}
		}
		
		if('set_rf_rate' == $_REQUEST['favobj']){
			if(str_in_array($_REQUEST['favid'],array('hat_slides'))){
				$elementid = $_REQUEST['elementid'];
				
				update_profile('web.slides.rf_rate.'.$_REQUEST['favid'],$_REQUEST['favcnt'], PROFILE_TYPE_INT);
				$_REQUEST['favcnt'] = get_profile('web.slides.rf_rate.'.$_REQUEST['favid'],30);

				$script = get_update_doll_script('mainpage', $_REQUEST['favid'], 'frequency', $_REQUEST['favcnt']);
				$script.= get_update_doll_script('mainpage', $_REQUEST['favid'], 'url', 'slides.php?elementid='.$elementid.url_param('stime').url_param('period'));
				$script.= get_update_doll_script('mainpage', $_REQUEST['favid'], 'stopDoll');
				$script.= get_update_doll_script('mainpage', $_REQUEST['favid'], 'startDoll');
				print $script;
				
				$menu = array();
				$submenu = array();
				
				make_refresh_menu('mainpage', $_REQUEST['favid'],$_REQUEST['favcnt'],array('elementid'=> $elementid),$menu,$submenu);
				
				print 'page_menu["menu_'.$_REQUEST['favid'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favid']]).';';
			}
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
?>
<?php
	$config = $_REQUEST['config'] = get_request('config', 1);

	if( 2 != $_REQUEST['fullscreen'] )
		update_profile('web.screens.config', $_REQUEST['config'],PROFILE_TYPE_INT);

	$_REQUEST['elementid'] = get_request('elementid',get_profile('web.screens.elementid', null));

	if( 2 != $_REQUEST['fullscreen'] )
		update_profile('web.screens.elementid',$_REQUEST['elementid']);

	$_REQUEST['period'] = get_request('period',get_profile('web.screens.period', ZBX_PERIOD_DEFAULT, null, $_REQUEST['elementid']));
	if($_REQUEST['period'] >= ZBX_MIN_PERIOD){
		update_profile('web.screens.period',$_REQUEST['period'], PROFILE_TYPE_INT, $_REQUEST['elementid']);
	}
?>
<?php

	$elementid = get_request('elementid', null);
	if($elementid <= 0) $elementid = null;

	$text = S_SLIDESHOWS;
		
	$form = new CForm();
	$form->setMethod('get');
	$form->addVar('config',S_SLIDESHOWS);	
	$form->addVar('fullscreen',$_REQUEST['fullscreen']);
	
	if(isset($_REQUEST['period']))	$form->addVar('period', $_REQUEST['period']);
	if(isset($_REQUEST['stime']))	$form->addVar('stime', $_REQUEST['stime']);

	$cmbConfig = new CComboBox('config', $config, "javascript: redirect('screens.php?config=0');");
	$cmbConfig->addItem(0, S_SCREENS);
	$cmbConfig->addItem(1, S_SLIDESHOWS);

	$form->addItem(array(S_SHOW.SPACE,$cmbConfig));

	$cmbElements = new CComboBox('elementid',$elementid,'submit()');
	unset($screen_correct);
	unset($first_screen);


	$result = DBselect('SELECT slideshowid as elementid,name '.
			' FROM slideshows '.
			' WHERE '.DBin_node('slideshowid').
			' ORDER BY name'
			);
	while($row=DBfetch($result)){
		if(!slideshow_accessible($row['elementid'], PERM_READ_ONLY))
			continue;

		$cmbElements->addItem(
				$row['elementid'],
				get_node_name_by_elid($row['elementid']).$row['name']
				);
		if((bccomp($elementid , $row['elementid']) == 0)) $element_correct = 1;
		if(!isset($first_element)) $first_element = $row['elementid'];
	}


	if(!isset($element_correct) && isset($first_element)){
		$elementid = $first_element;
	}

	if(isset($elementid) && !slideshow_accessible($elementid, PERM_READ_ONLY)) access_deny();

	if($cmbElements->ItemsCount() > 0) $form->addItem(array(SPACE.S_SLIDESHOW.SPACE,$cmbElements));
	
	
	if((2 != $_REQUEST['fullscreen']) && !empty($elementid) && check_dynamic_items($elementid, 1)){
		if(!isset($_REQUEST['hostid'])){
			$_REQUEST['groupid'] = $_REQUEST['hostid'] = 0;
		}
		
		$options = array('allow_all_hosts','monitored_hosts','with_items');
		if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');
		
		$params = array();
		foreach($options as  $option) $params[$option] = 1;
		$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
		$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
		validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);

		$available_groups = $PAGE_GROUPS['groupids'];
//		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
		$available_hosts = $PAGE_HOSTS['hostids'];
		
		$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
		}
		$form->addItem(array(SPACE.S_GROUP.SPACE,$cmbGroups));


		$PAGE_HOSTS['hosts']['0'] = S_DEFAULT;
		$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');		
		foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
			$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
		}
		$form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
		
		show_table_header($text,$form);
	}
	else if(2 != $_REQUEST['fullscreen']){
		show_table_header($text,$form);
	}
?>
<?php	
	$slide_name = S_SLIDESHOW;
	if(isset($elementid)){
		if($element = get_slideshow_by_slideshowid($elementid)){
			$slide_name = $element['name'];
		}
	}

	$icon = null;
	$fs_icon = null;
	if(isset($elementid) && $element){
		if(infavorites('web.favorite.screenids',$elementid,'slideshowid')){
			$icon = new CDiv(SPACE,'iconminus');
			$icon->addOption('title',S_REMOVE_FROM.' '.S_FAVORITES);
			$icon->addAction('onclick',new CScript("javascript: rm4favorites('slideshowid','".$elementid."',0);"));
		}
		else{
			$icon = new CDiv(SPACE,'iconplus');
			$icon->addOption('title',S_ADD_TO.' '.S_FAVORITES);
			$icon->addAction('onclick',new CScript("javascript: add2favorites('slideshowid','".$elementid."');"));
		}
		$icon->addOption('id','addrm_fav');
		
		$url = '?elementid='.$elementid.($_REQUEST['fullscreen']?'':'&fullscreen=1');
		$url.=url_param('groupid').url_param('hostid');
		
		$fs_icon = new CDiv(SPACE,'fullscreen');
		$fs_icon->addOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
		$fs_icon->addAction('onclick',new CScript("javascript: document.location = '".$url."';"));	
	}
	
	$menu = array();
	$submenu = array();

// js menu arrays	
	make_refresh_menu('mainpage','hat_slides',get_profile('web.slides.rf_rate.hat_slides',30),array('elementid'=> $elementid),$menu,$submenu);

	insert_js('var page_menu='.zbx_jsvalue($menu).";\n".
			 'var page_submenu='.zbx_jsvalue($submenu).";\n"
		);	
// --------------
	
	$tab = new CTable();
	$tab->setCellPadding(0);
	$tab->setCellSpacing(0);

	$tab->addOption('border',0);
	$tab->addOption('width','100%');

// Refresh tab

	$refresh_tab = array(
		array('id' => 'hat_slides',
				'frequency' => get_profile('web.slides.rf_rate.hat_slides',30),
				'url' => 'slides.php?elementid='.$elementid.url_param('stime').url_param('period').url_param('groupid').url_param('hostid'),
				'params'=> array('lastupdate' => time()),
			));
	add_doll_objects($refresh_tab);
	
	$refresh_icon = new CDiv(SPACE,'iconmenu');
	$refresh_icon->addAction('onclick','javascript: create_page_menu(event,"hat_slides");');
	$refresh_icon->addOption('title',S_MENU);
	
	if(isset($elementid)){
		$effectiveperiod = navigation_bar_calc();
		if( 2 != $_REQUEST['fullscreen'] ){		
			$stime = time() - 31536000; // ~1year
			$bstime = time()-$effectiveperiod;
			
			if(isset($_REQUEST['stime'])){
				$bstime = $_REQUEST['stime'];
				$bstime = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
			}

			zbx_add_post_js('onload_update_scroll("iframe",0,'.$effectiveperiod.','.$stime.',0,'.$bstime.');');
		}
		$element = get_slideshow($elementid, 0, $effectiveperiod);
	}
	else{
		$element = new CSpan(S_LOADING_P,'textcolorstyles');
	}

	$tab->addRow(create_hat(
			$slide_name,
			$element,
			array($icon,$fs_icon,$refresh_icon),
			'hat_slides',
			get_profile('web.slides.hats.hat_slides.state',1)
		),'center');
	

	$tab->show();
	
	$scroll_div = new CDiv();
	$scroll_div->addOption('id','scroll_cntnr');
	$scroll_div->addOption('style','border: 0px #CC0000 solid; height: 25px; width: 800px;');
	$scroll_div->show();
	
/*	
	$img = new CImg('images/general/tree/zero.gif','space','20','20');
	$elements = array($img,BR());
	print(unpack_object($elements));
//*/

	$jsmenu = new CPUMenu(null,170);
	$jsmenu->InsertJavaScript();	
?>
<?php

include_once('include/page_footer.php');

?>
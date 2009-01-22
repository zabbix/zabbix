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

require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/triggers.inc.php');
require_once('include/items.inc.php');
require_once('include/actions.inc.php');
require_once('include/discovery.inc.php');
require_once('include/html.inc.php');
require_once('include/blocks.inc.php');

$page["title"] = "S_DASHBOARD";
$page['file'] = 'dashboard.php';
$page['hist_arg'] = array();
$page['scripts'] = array('pmaster.js','menu_scripts.js','showhint.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once "include/page_header.php";

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			NULL),
		'view_style'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		'type'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),

		'output'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,			NULL),
		'jsscriptid'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,			NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,			NULL),
		'pmasterid'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	null,			NULL),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove'"),NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),
	);

	check_fields($fields);
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_RES_IDS_ARRAY);
// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['favobj'])){
		$_REQUEST['pmasterid'] = get_request('pmasterid','mainpage');
		
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.dashboard.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}

		if('refresh' == $_REQUEST['favobj']){
			switch($_REQUEST['favid']){
				case 'hat_syssum':
					$syssum = make_system_summary();
					$syssum->show();
					break;
				case 'hat_stszbx':
					$stszbx = make_status_of_zbx();
					$stszbx->show();
					break;
				case 'hat_lastiss':
					$lastiss = make_latest_issues();
					$lastiss->show();
					break;
				case 'hat_webovr':
					$webovr = make_webmon_overview();
					$webovr->show();
					break;
				case 'hat_dscvry':
					$dscvry = make_discovery_status();
					$dscvry->show();
					break;
			}
		}

		if('set_rf_rate' == $_REQUEST['favobj']){
			if(str_in_array($_REQUEST['favid'],array('hat_syssum','hat_stszbx','hat_lastiss','hat_webovr','hat_dscvry'))){
			
				update_profile('web.dahsboard.rf_rate.'.$_REQUEST['favid'],$_REQUEST['favcnt'], PROFILE_TYPE_INT);
				$_REQUEST['favcnt'] = get_profile('web.dahsboard.rf_rate.'.$_REQUEST['favid'], 60);

				$script = get_update_doll_script('mainpage', $_REQUEST['favid'], 'frequency', $_REQUEST['favcnt']);
				$script.= get_update_doll_script('mainpage', $_REQUEST['favid'], 'stopDoll');
				$script.= get_update_doll_script('mainpage', $_REQUEST['favid'], 'startDoll');
				echo $script;
				
				$menu = array();
				$submenu = array();
				
				make_refresh_menu('mainpage',$_REQUEST['favid'],$_REQUEST['favcnt'],null,$menu,$submenu);
				
				echo 'page_menu["menu_'.$_REQUEST['favid'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favid']]).';';
			}
		}

		if(str_in_array($_REQUEST['favobj'],array('itemid','graphid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.graphids',$_REQUEST['favid'],$_REQUEST['favobj']);
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.graphids',$_REQUEST['favid'],get_request('favcnt',0),$_REQUEST['favobj']);
			}

			if((PAGE_TYPE_JS == $page['type']) && $result){
				$innerHTML = make_favorite_graphs();
				$innerHTML = $innerHTML->toString();
				print('$("hat_favgrph").update('.zbx_jsvalue($innerHTML).');');
				
				$menu = array();
				$submenu = array();
				print('page_submenu["menu_graphs"] = '.zbx_jsvalue(make_graph_submenu()).';');
			}
		}
		
		if('sysmapid' == $_REQUEST['favobj']){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.sysmapids',$_REQUEST['favid'],$_REQUEST['favobj']);
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.sysmapids',$_REQUEST['favid'],get_request('favcnt',0),$_REQUEST['favobj']);
			}			
			
			if((PAGE_TYPE_JS == $page['type']) && $result){
				$innerHTML = make_favorite_maps();
				$innerHTML = $innerHTML->toString();
				echo '$("hat_favmap").update('.zbx_jsvalue($innerHTML).');';
				
				$menu = array();
				$submenu = array();
				echo 'page_submenu["menu_sysmaps"] = '.zbx_jsvalue(make_sysmap_submenu()).';';
			}
		}
		if(str_in_array($_REQUEST['favobj'],array('screenid','slideshowid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.screenids',$_REQUEST['favid'],$_REQUEST['favobj']);
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.screenids',$_REQUEST['favid'],get_request('favcnt',0),$_REQUEST['favobj']);
			}			
			
			if(PAGE_TYPE_JS == $page['type'] && $result){
				$innerHTML = make_favorite_screens();
				$innerHTML = $innerHTML->toString();
				echo '$("hat_favscr").update('.zbx_jsvalue($innerHTML).');';
				
				$menu = array();
				$submenu = array();
				echo 'page_submenu["menu_screens"] = '.zbx_jsvalue(make_screen_submenu()).';';
			}
		}
	}	
	
	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}

//	validate_group(PERM_READ_ONLY,array("allow_all_hosts","monitored_hosts","with_monitored_items"));
//	$time = new CSpan(date("[H:i:s]",time()));
//	$time->addOption('id','refreshed');

	$p_elements = array();
// Header	

	$url = '?fullscreen='.($_REQUEST['fullscreen']?'0':'1');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->addOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->addAction('onclick',new CScript("javascript: document.location = '".$url."';"));
//-------------

	$left_tab = new CTable();
	$left_tab->setCellPadding(3);
	$left_tab->setCellSpacing(3);

	$left_tab->addOption('border',0);
	
	$menu = array();
	$submenu = array();

// js menu arrays	
	make_graph_menu($menu,$submenu);
	make_sysmap_menu($menu,$submenu);
	make_screen_menu($menu,$submenu);
	
	make_refresh_menu('mainpage','hat_syssum',get_profile('web.dahsboard.rf_rate.hat_syssum',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_stszbx',get_profile('web.dahsboard.rf_rate.hat_stszbx',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_lastiss',get_profile('web.dahsboard.rf_rate.hat_lastiss',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_webovr',get_profile('web.dahsboard.rf_rate.hat_webovr',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_dscvry',get_profile('web.dahsboard.rf_rate.hat_dscvry',60),null,$menu,$submenu);
	
	insert_js('var page_menu='.zbx_jsvalue($menu).";\n".
			 'var page_submenu='.zbx_jsvalue($submenu).";\n"
		);
	
// --------------

	$graph_menu = new CDiv(SPACE,'iconmenu');
	$graph_menu->addAction('onclick','javascript: create_page_menu(event,"graphs");');
	$graph_menu->addOption('title',S_MENU);
	
	$left_tab->addRow(create_hat(
			S_FAVORITE.SPACE.S_GRAPHS,
			make_favorite_graphs(),
			array($graph_menu),
			'hat_favgrph',
			get_profile('web.dashboard.hats.hat_favgrph.state',1)
		));
		
	$screen_menu = new CDiv(SPACE,'iconmenu');
	$screen_menu->addAction('onclick','javascript: create_page_menu(event,"screens");');
	$screen_menu->addOption('title',S_MENU);

	$left_tab->addRow(create_hat(
			S_FAVORITE.SPACE.S_SCREENS,
			make_favorite_screens(),
			array($screen_menu),
			'hat_favscr',
			get_profile('web.dashboard.hats.hat_favscr.state',1)
		));
		
		
	$sysmap_menu = new CDiv(SPACE,'iconmenu');
	$sysmap_menu->addAction('onclick','javascript: create_page_menu(event,"sysmaps");');
	$sysmap_menu->addOption('title',S_MENU);
		
	$left_tab->addRow(create_hat(
			S_FAVORITE.SPACE.S_MAPS,
			make_favorite_maps(),
			array($sysmap_menu),
			'hat_favmap',
			get_profile('web.dashboard.hats.hat_favmap.state',1)
		));
		
	$left_tab->addRow(SPACE);
	
	$right_tab = new CTable();
	$right_tab->setCellPadding(3);
	$right_tab->setCellSpacing(3);

	$right_tab->addOption('border',0);

// Refresh tab

	$refresh_tab = array(
		array('id' => 'hat_syssum',
				'frequency' => get_profile('web.dahsboard.rf_rate.hat_syssum',120)
			),
		array('id' => 'hat_stszbx',
				'frequency' => get_profile('web.dahsboard.rf_rate.hat_stszbx',120)
			),
		array('id' => 'hat_lastiss',
				'frequency'  => get_profile('web.dahsboard.rf_rate.hat_lastiss',60)
			),
		array('id' => 'hat_webovr',
				'frequency'  => get_profile('web.dahsboard.rf_rate.hat_webovr',60)
			)
/*		array('id' => 'hat_custom',
				'frequency'  =>	get_profile('web.dahsboard.rf_rate.hat_custom',60),
				'url'=>	'charts.php?groupid=4&hostid=10017&graphid=5&output=html&fullscreen=1'
			)*/
	);

	$refresh_menu = new CDiv(SPACE,'iconmenu');
	$refresh_menu->addAction('onclick','javascript: create_page_menu(event,"hat_syssum");');
	$refresh_menu->addOption('title',S_MENU);
	
	$right_tab->addRow(create_hat(
			S_SYSTEM_STATUS,
			new CSpan(S_LOADING_P,'textcolorstyles'),//make_system_summary(),
			array($refresh_menu),
			'hat_syssum',
			get_profile('web.dashboard.hats.hat_syssum.state',1)
		));

	$refresh_menu = new CDiv(SPACE,'iconmenu');
	$refresh_menu->addAction('onclick','javascript: create_page_menu(event,"hat_stszbx");');
	$refresh_menu->addOption('title',S_MENU);

	$right_tab->addRow(create_hat(
			S_STATUS_OF_ZABBIX,
			new CSpan(S_LOADING_P,'textcolorstyles'),//make_status_of_zbx(),
			array($refresh_menu),
			'hat_stszbx',
			get_profile('web.dashboard.hats.hat_stszbx.state',1)
		));
		
	$refresh_menu = new CDiv(SPACE,'iconmenu');
	$refresh_menu->addAction('onclick','javascript: create_page_menu(event,"hat_lastiss");');
	$refresh_menu->addOption('title',S_MENU);
		
	$right_tab->addRow(create_hat(S_LAST_20_ISSUES,
			new CSpan(S_LOADING_P,'textcolorstyles'),//make_latest_issues(),
			array($refresh_menu),
			'hat_lastiss',
			get_profile('web.dashboard.hats.hat_lastiss.state',1)
		));
		
	$refresh_menu = new CDiv(SPACE,'iconmenu');
	$refresh_menu->addAction('onclick','javascript: create_page_menu(event,"hat_webovr");');
	$refresh_menu->addOption('title',S_MENU);

	$right_tab->addRow(create_hat(
			S_WEB_MONITORING,
			new CSpan(S_LOADING_P,'textcolorstyles'),//make_webmon_overview(),
			array($refresh_menu),
			'hat_webovr',
			get_profile('web.dashboard.hats.hat_webovr.state',1)
		));

	$drules = DBfetch(DBselect('SELECT COUNT(d.druleid) as cnt FROM drules d WHERE '.DBin_node('d.druleid').' AND d.status='.DRULE_STATUS_ACTIVE));

	if(($drules['cnt'] > 0) && check_right_on_discovery(PERM_READ_ONLY)){
	
		$refresh_tab[] = array(	'id' => 'hat_dscvry','frequency'  => get_profile('web.dahsboard.rf_rate.hat_dscvry',60));

		$refresh_menu = new CDiv(SPACE,'iconmenu');
		$refresh_menu->addAction('onclick','javascript: create_page_menu(event,"hat_dscvry");');
		$refresh_menu->addOption('title',S_MENU);
	
		$right_tab->addRow(create_hat(
				S_DISCOVERY_STATUS,
				new CSpan(S_LOADING_P,'textcolorstyles'),//make_discovery_status(),//
				array($refresh_menu),
				'hat_dscvry',
				get_profile('web.dashboard.hats.hat_dscvry.state',1)
			));
	}
	
	add_doll_objects($refresh_tab);

/*		
	$right_tab->addRow(create_hat(
			S_GRAPH,
			null,//make_webmon_overview(),
			null,
			'hat_custom',
			get_profile('web.dashboard.hats.hat_custom.state',1)
		));
*/
	$td_l = new CCol($left_tab);
	$td_l->addOption('valign','top');
	
	$td_r = new CCol($right_tab);
	$td_r->addOption('valign','top');

	$outer_table = new CTable();
	$outer_table->addOption('border',0);
	$outer_table->setCellPadding(1);
	$outer_table->setCellSpacing(1);
	$outer_table->addRow(array($td_l,$td_r));
	
	$p_elements[] = $outer_table;

	$fav_form = new CForm();
	$fav_form->addOption('name','fav_form');
	$fav_form->addOption('id','fav_form');
	$fav_form->addOption('style','display: inline; margin: 0px;');
	$fav_form->addVar('favobj','');
	$fav_form->addVar('favid','');
	$fav_form->addVar('source','');
	
	$p_elements[] = $fav_form;
	
	$dashboard_hat = create_hat(
			S_DASHBOARD_BIG,
			$p_elements,
			array($fs_icon),
			'hat_dashboard',
			get_profile('web.dashboard.hats.hat_dashboard.state',1)
	);

	$dashboard_hat->Show();

	$jsmenu = new CPUMenu(null,170);
	$jsmenu->InsertJavaScript();
		
//	$link = new CLink('Click Me','javascript: callJSON();','highlight');
//	$link->Show();
?>
<?php

include_once "include/page_footer.php";

?>
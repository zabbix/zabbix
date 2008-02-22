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

require_once "include/config.inc.php";
require_once "include/hosts.inc.php";
require_once "include/triggers.inc.php";
require_once "include/items.inc.php";
require_once "include/html.inc.php";
require_once "include/blocks.inc.php";

$page["title"] = "S_DASHBOARD";
$page["file"] = "dashboard.php";
$page['hist_arg'] = array();
$page['scripts'] = array('prototype.js','json.js','dashboard.js');


$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if(PAGE_TYPE_HTML == $page['type'])
	define('ZBX_PAGE_DO_REFRESH', 1);


include_once "include/page_header.php";

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			NULL),
		'view_style'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		'type'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		
		'output'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'jsscriptid'=>	array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,			null),
		
		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),
	);

	check_fields($fields);
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, null, null, get_current_nodeid());
	
// ACTION /////////////////////////////////////////////////////////////////////////////

	if(isset($_REQUEST['favobj'])){
		if($_REQUEST['favobj'] == 'hat'){
//			echo 'alert("'.$_REQUEST['favid'].' : '.$_REQUEST['state'].'");';
			update_profile('web.dashboard.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state']);
		}
//SDI()
		if(in_array($_REQUEST['favobj'],array('simple_graph','graphs'))){
			$result = false;
			if($_REQUEST['action'] == 'add'){
				$graphids = get_profile('web.favorite.graphids',array());
				$graph_rsrc = get_profile('web.favorite.graph_rsrc',array());
				
				$graphids[] = $_REQUEST['favid'];
				$graph_rsrc[] = ($_REQUEST['favobj'] == 'graphs')?'graphs':'simple_graph';
				
				$result = update_profile('web.favorite.graphids',$graphids);
				$result &= update_profile('web.favorite.graph_rsrc',$graph_rsrc);
			}
			else if($_REQUEST['action'] == 'remove'){
				$graphids = get_profile('web.favorite.graphids',array());
				$graph_rsrc = get_profile('web.favorite.graph_rsrc',array());
				
				$favcnt = (isset($_REQUEST['favcnt']))?$_REQUEST['favcnt']:0;
				foreach($graphids as $key => $value){
					if(($_REQUEST['favid'] == $value) && ($graph_rsrc[$key] == $_REQUEST['favobj'])){
						if($favcnt < 1){
							unset($graphids[$key]);
							unset($graph_rsrc[$key]);
							break;
						}
					}
					$favcnt--;
				}
				
				$result = update_profile('web.favorite.graphids',$graphids);
				$result &= update_profile('web.favorite.graph_rsrc',$graph_rsrc);
			}
			
			
			if((PAGE_TYPE_JS == $page['type']) && $result){
				$innerHTML = make_favorite_graphs($available_hosts);
				$innerHTML = $innerHTML->toString();
				echo '$("hat_favgrph").update('.zbx_jsvalue($innerHTML).');';
				
				$menu = array();
				$submenu = array();
				echo 'dashboard_submenu["menu_graphs"] = '.zbx_jsvalue(make_graph_submenu()).';';
			}
		}
		if('sysmaps' == $_REQUEST['favobj']){
			$result = false;
			if($_REQUEST['action'] == 'add'){
				$sysmapids = get_profile('web.favorite.sysmapids',array());
				$sysmapids[] = $_REQUEST['favid'];

				$result = update_profile('web.favorite.sysmapids',$sysmapids);
			}
			else if($_REQUEST['action'] == 'remove'){
				$sysmapids = get_profile('web.favorite.sysmapids',array());
				
				$favcnt = (isset($_REQUEST['favcnt']))?$_REQUEST['favcnt']:0;
				foreach($sysmapids as $key => $value){
					if($_REQUEST['favid'] == $value){
						if($favcnt < 1){
							unset($sysmapids[$key]);
							break;
						}
					}
					$favcnt--;
				}
				$result = update_profile('web.favorite.sysmapids',$sysmapids);
			}
			
			
			if((PAGE_TYPE_JS == $page['type']) && $result){
				$innerHTML = make_favorite_maps();
				$innerHTML = $innerHTML->toString();
				echo '$("hat_favmap").update('.zbx_jsvalue($innerHTML).');';
				
				$menu = array();
				$submenu = array();
				echo 'dashboard_submenu["menu_sysmaps"] = '.zbx_jsvalue(make_sysmap_submenu()).';';
			}
		}
		if(in_array($_REQUEST['favobj'],array('screens','slides'))){
			$result = false;
			if($_REQUEST['action'] == 'add'){
				$perm = ('screens' == $_REQUEST['favobj'])?
					screen_accessiable($_REQUEST['favid'], PERM_READ_ONLY):
					slideshow_accessiable($_REQUEST['favid'], PERM_READ_ONLY);
					
				if($perm){
					$screenids = get_profile('web.favorite.screenids',array());
					$screen_rsrc = get_profile('web.favorite.screen_rsrc',array());

					$screenids[] = $_REQUEST['favid'];
					$screen_rsrc[] = $_REQUEST['favobj'];
					
					$result = update_profile('web.favorite.screenids',$screenids);
					$result &= update_profile('web.favorite.screen_rsrc',$screen_rsrc);
				}
			}
			else if($_REQUEST['action'] == 'remove'){
				$screenids = get_profile('web.favorite.screenids',array());
				$screen_rsrc = get_profile('web.favorite.screen_rsrc',array());
				
				$favcnt = (isset($_REQUEST['favcnt']))?$_REQUEST['favcnt']:0;
				foreach($screenids as $key => $value){
					if(($_REQUEST['favid'] == $value) && ($screen_rsrc[$key] == $_REQUEST['favobj'])){
						if($favcnt < 1){
							unset($screenids[$key]);
							unset($screen_rsrc[$key]);
							break;
						}
					}
					$favcnt--;
				}
								
				$result = update_profile('web.favorite.screenids',$screenids);
				$result &= update_profile('web.favorite.screen_rsrc',$screen_rsrc);
			}
			
			if(PAGE_TYPE_JS == $page['type']){
				$innerHTML = make_favorite_screens();
				$innerHTML = $innerHTML->toString();
				echo '$("hat_favscr").update('.zbx_jsvalue($innerHTML).');';
				
				$menu = array();
				$submenu = array();
				echo 'dashboard_submenu["menu_screens"] = '.zbx_jsvalue(make_screen_submenu()).';';
			}
		}
	}	
	
	if(isset($_REQUEST['output'])){
		if('json2' == $_REQUEST['output']){
			echo 'try{'.
					'json.callBack("sdt"); '.
					'json.removeScript("'.$_REQUEST['jsscriptid'].'");'.
				' }catch(e){ '.
					'alert("Warning: incorrect JSON return.");'.
					'json.removeScript("'.$_REQUEST['jsscriptid'].'");'.
				' }';
		}
	}
	


	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}

//	validate_group(PERM_READ_ONLY,array("allow_all_hosts","monitored_hosts","with_monitored_items"));
	show_table_header(SPACE.S_DASHBOARD_BIG.SPACE.date("[H:i:s]",time()),SPACE);

	$left_tab = new CTable();
	$left_tab->SetCellPadding(5);
	$left_tab->SetCellSpacing(5);

	$left_tab->AddOption('border',0);
	
	$menu = array();
	$submenu = array();

// js menu arrays	
	make_graph_menu($menu,$submenu);
	make_sysmap_menu($menu,$submenu);
	make_screen_menu($menu,$submenu);
	
	insert_js('var dashboard_menu='.zbx_jsvalue($menu)."\n".
			 'var dashboard_submenu='.zbx_jsvalue($submenu)."\n"
		);
	
// --------------

	$graph_menu = new CDiv(SPACE,'menuplus');
	$graph_menu->AddAction('onclick','javascript: create_menu(event,"graphs");');
	
	$left_tab->AddRow(create_hat(
			S_FAVORITE.SPACE.S_GRAPHS,
			make_favorite_graphs($available_hosts),
			array($graph_menu),
			'hat_favgrph',
			get_profile('web.dashboard.hats.hat_favgrph.state')
		));
		
	$sysmap_menu = new CDiv(SPACE,'menuplus');
	$sysmap_menu->AddAction('onclick','javascript: create_menu(event,"sysmaps");');
		
	$left_tab->AddRow(create_hat(
			S_FAVORITE.SPACE.S_MAPS,
			make_favorite_maps(),
			array($sysmap_menu),
			'hat_favmap',
			get_profile('web.dashboard.hats.hat_favmap.state')
		));
		
	$screen_menu = new CDiv(SPACE,'menuplus');
	$screen_menu->AddAction('onclick','javascript: create_menu(event,"screens");');

	$left_tab->AddRow(create_hat(
			S_FAVORITE.SPACE.S_SCREENS,
			make_favorite_screens(),
			array($screen_menu),
			'hat_favscr',
			get_profile('web.dashboard.hats.hat_favscr.state')
		));
	$left_tab->AddRow(SPACE);
	
	$right_tab = new CTable();
	$right_tab->SetCellPadding(5);
	$right_tab->SetCellSpacing(5);

	$right_tab->AddOption('border',0);

//	$right_td_l = new CCol();
//	$right_td_l->Addoption('valign','top');

	$right_tab->AddRow(create_hat(
			S_SYSTEM_STATUS,
			make_system_summary($available_hosts),
			null,
			'hat_syssum',
			get_profile('web.dashboard.hats.hat_syssum.state')
		));
	$right_tab->AddRow(create_hat(
			S_STATUS_OF_ZABBIX,
			make_status_of_zbx(),
			null,
			'hat_stszbx',
			get_profile('web.dashboard.hats.hat_stszbx.state')
		));
	$right_tab->AddRow(create_hat(S_LATEST_ISSUES,
			make_latest_issues($available_hosts),
			null,
			'hat_lastiss',
			get_profile('web.dashboard.hats.hat_lastiss.state')
		));
	$right_tab->AddRow(create_hat(
			S_WEB_MONITORING,
			make_webmon_overview(),
			null,
			'hat_webovr',
			get_profile('web.dashboard.hats.hat_webovr.state')
		));

	$td_l = new CCol($left_tab);
	$td_l->AddOption('valign','top');
	
	$td_r = new CCol($right_tab);
	$td_r->AddOption('valign','top');

	$outer_table = new CTable();
	$outer_table->AddOption('border',0);
	$outer_table->SetCellPadding(1);
	$outer_table->SetCellSpacing(1);
	$outer_table->AddRow(array($td_l,$td_r));
	
	$outer_table->Show();

	$fav_form = new CForm();
	$fav_form->AddOption('name','fav_form');
	$fav_form->AddOption('id','fav_form');
	$fav_form->AddOption('style','display: inline; margin: 0px;');
	$fav_form->AddVar('favobj','');
	$fav_form->AddVar('favid','');
	$fav_form->AddVar('resource','');
	$fav_form->Show();

	$jsmenu = new CPUMenu(null,170);
	$jsmenu->InsertJavaScript();
		
//	$link = new CLink('Click Me','javascript: callJSON();','highlight');
//	$link->Show();
?>
<?php

include_once "include/page_footer.php";

?>
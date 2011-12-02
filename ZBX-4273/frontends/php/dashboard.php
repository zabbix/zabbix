<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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

$page['title'] = 'S_DASHBOARD';
$page['file'] = 'dashboard.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.pmaster.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');

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
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NULL,			NULL),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,			NULL),
		'pmasterid'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	null,			NULL),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove','refresh','flop'"),	NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({action}) && ("flop"=={action})'),
	);

	check_fields($fields);

// FILTER
	$dashconf = array();
	$dashconf['groupids'] = null;
	$dashconf['maintenance'] = null;
	$dashconf['severity'] = null;
	$dashconf['extAck'] = 0;

	$dashconf['filterEnable'] = CProfile::get('web.dashconf.filter.enable', 0);
	if($dashconf['filterEnable'] == 1){
// groups
		$dashconf['grpswitch'] = CProfile::get('web.dashconf.groups.grpswitch', 0);

		if($dashconf['grpswitch'] == 0){
			$dashconf['groupids'] = null;
		}
		else{
			$groupids = get_favorites('web.dashconf.groups.groupids');
			$dashconf['groupids'] = zbx_objectValues($groupids, 'value');
		}

// hosts
		$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);
		$dashconf['maintenance'] = ($maintenance == 0)?0:null;

// triggers
		$severity = CProfile::get('web.dashconf.triggers.severity', null);
		$dashconf['severity'] = zbx_empty($severity)?null:explode(';', $severity);
		$dashconf['severity'] = zbx_toHash($dashconf['severity']);

		$config = select_config();
		$dashconf['extAck'] = $config['event_ack_enable'] ? CProfile::get('web.dashconf.events.extAck', 0) : 0;
	}

// ------


// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['favobj'])){
		$_REQUEST['pmasterid'] = get_request('pmasterid','mainpage');

		if('hat' == $_REQUEST['favobj']){
			if('flop' == $_REQUEST['action']){
				CProfile::update('web.dashboard.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
			}
			else if('refresh' == $_REQUEST['action']){
				switch($_REQUEST['favref']){
					case 'hat_syssum':
						$syssum = make_system_status($dashconf);
						$syssum->show();
						break;
					case 'hat_hoststat':
						$hoststat = make_hoststat_summary($dashconf);
						$hoststat->show();
						break;
					case 'hat_stszbx':
						$stszbx = make_status_of_zbx();
						$stszbx->show();
						break;
					case 'hat_lastiss':
						$lastiss = make_latest_issues($dashconf);
						$lastiss->show();
						break;
					case 'hat_webovr':
						$webovr = make_webmon_overview($dashconf);
						$webovr->show();
						break;
					case 'hat_dscvry':
						$dscvry = make_discovery_status();
						$dscvry->show();
						break;
				}
			}
		}

		if('set_rf_rate' == $_REQUEST['favobj']){
			if(str_in_array($_REQUEST['favref'],array('hat_syssum','hat_stszbx','hat_lastiss','hat_webovr','hat_dscvry','hat_hoststat'))){

				CProfile::update('web.dahsboard.rf_rate.'.$_REQUEST['favref'],$_REQUEST['favcnt'], PROFILE_TYPE_INT);
				$_REQUEST['favcnt'] = CProfile::get('web.dahsboard.rf_rate.'.$_REQUEST['favref'], 60);

				$script = get_update_doll_script('mainpage', $_REQUEST['favref'], 'frequency', $_REQUEST['favcnt']);
				$script.= get_update_doll_script('mainpage', $_REQUEST['favref'], 'stopDoll');
				$script.= get_update_doll_script('mainpage', $_REQUEST['favref'], 'startDoll');
				echo $script;

				$menu = array();
				$submenu = array();

				make_refresh_menu('mainpage',$_REQUEST['favref'],$_REQUEST['favcnt'],null,$menu,$submenu);

				echo 'page_menu["menu_'.$_REQUEST['favref'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favref']]).';';
			}
		}

		if(str_in_array($_REQUEST['favobj'],array('itemid','graphid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				zbx_value2array($_REQUEST['favid']);

				foreach($_REQUEST['favid'] as  $num => $sourceid){
					$result = add2favorites('web.favorite.graphids',$sourceid,$_REQUEST['favobj']);
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.graphids',$_REQUEST['favid'],$_REQUEST['favobj']);
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
				zbx_value2array($_REQUEST['favid']);

				foreach($_REQUEST['favid'] as  $num => $sourceid){
					$result = add2favorites('web.favorite.sysmapids',$sourceid,$_REQUEST['favobj']);
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.sysmapids',$_REQUEST['favid'],$_REQUEST['favobj']);
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
				zbx_value2array($_REQUEST['favid']);

				foreach($_REQUEST['favid'] as  $num => $sourceid){
					$result = add2favorites('web.favorite.screenids',$sourceid,$_REQUEST['favobj']);
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.screenids',$_REQUEST['favid'],$_REQUEST['favobj']);
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
		include_once('include/page_footer.php');
		exit();
	}

//	$time = new CSpan(date("[H:i:s]",time()));
//	$time->setAttribute('id','refreshed');

	$dashboard_wdgt = new CWidget('dashboard_wdgt');
// Header

	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));

	$style = $dashconf['filterEnable']? 'iconconfig_hl':'iconconfig';
	$state = S_CONFIGURE.' ('.S_FILTER.' '.($dashconf['filterEnable']?S_ENABLED:S_DISABLED).')';
	$dc_icon = new CIcon($state, $style, "document.location = 'dashconf.php';");

	$dashboard_wdgt->setClass('header');
	$dashboard_wdgt->addHeader(S_DASHBOARD_BIG, array($dc_icon, $fs_icon));
//-------------

	$left_tab = new CTable();
	$left_tab->setCellPadding(3);
	$left_tab->setCellSpacing(3);

	$left_tab->setAttribute('border',0);

	$menu = array();
	$submenu = array();

// js menu arrays
	make_graph_menu($menu,$submenu);
	make_sysmap_menu($menu,$submenu);
	make_screen_menu($menu,$submenu);

	make_refresh_menu('mainpage','hat_syssum',CProfile::get('web.dahsboard.rf_rate.hat_syssum',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_hoststat',CProfile::get('web.dahsboard.rf_rate.hat_hoststat',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_stszbx',CProfile::get('web.dahsboard.rf_rate.hat_stszbx',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_lastiss',CProfile::get('web.dahsboard.rf_rate.hat_lastiss',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_webovr',CProfile::get('web.dahsboard.rf_rate.hat_webovr',60),null,$menu,$submenu);
	make_refresh_menu('mainpage','hat_dscvry',CProfile::get('web.dahsboard.rf_rate.hat_dscvry',60),null,$menu,$submenu);

	insert_js('var page_menu='.zbx_jsvalue($menu).";\n".
			'var page_submenu='.zbx_jsvalue($submenu).";\n"
		);

// --------------

// Favorite graphs
	$graph_menu = get_icon('menu', array('menu' => 'graphs'));
	$fav_grph = new CWidget('hat_favgrph',
						make_favorite_graphs(),
						CProfile::get('web.dashboard.hats.hat_favgrph.state',1)
						);
	$fav_grph->addHeader(S_FAVOURITE_GRAPHS,array($graph_menu));
	$left_tab->addRow($fav_grph);
//----------------

// favorite screens
	$screen_menu = get_icon('menu', array('menu' => 'screens'));
	$fav_scr = new CWidget('hat_favscr',
						make_favorite_screens(),
						CProfile::get('web.dashboard.hats.hat_favscr.state',1)
						);
	$fav_scr->addHeader(S_FAVOURITE_SCREENS,array($screen_menu));
	$left_tab->addRow($fav_scr);
//----------------

// Favorite Sysmaps
	$sysmap_menu = get_icon('menu', array('menu' => 'sysmaps'));
	$fav_maps = new CWidget('hat_favmap',
						make_favorite_maps(),
						CProfile::get('web.dashboard.hats.hat_favmap.state',1)
						);
	$fav_maps->addHeader(S_FAVOURITE_MAPS,array($sysmap_menu));
	$left_tab->addRow($fav_maps);
//----------------

	$left_tab->addRow(SPACE);

	$right_tab = new CTable();
	$right_tab->setCellPadding(3);
	$right_tab->setCellSpacing(3);

	$right_tab->setAttribute('border',0);

// Refresh tab

	$refresh_tab = array(
		array('id' => 'hat_syssum',
				'frequency' => CProfile::get('web.dahsboard.rf_rate.hat_syssum',120)
			),
		array('id' => 'hat_stszbx',
				'frequency' => CProfile::get('web.dahsboard.rf_rate.hat_stszbx',120)
			),
		array('id' => 'hat_lastiss',
				'frequency'  => CProfile::get('web.dahsboard.rf_rate.hat_lastiss',60)
			),
		array('id' => 'hat_webovr',
				'frequency'  => CProfile::get('web.dahsboard.rf_rate.hat_webovr',60)
			),
		array('id' => 'hat_hoststat',
				'frequency'  => CProfile::get('web.dahsboard.rf_rate.hat_hoststat',60)
			)
/*		array('id' => 'hat_custom',
				'frequency'  =>	CProfile::get('web.dahsboard.rf_rate.hat_custom',60),
				'url'=>	'charts.php?groupid=4&hostid=10017&graphid=5&output=html&fullscreen=1'
			)*/
	);

// Status of ZBX
	if(USER_TYPE_SUPER_ADMIN == $USER_DETAILS['type']){
		$refresh_menu = get_icon('menu', array('menu' => 'hat_stszbx'));
		$zbx_stat = new CWidget('hat_stszbx',
							new CSpan(S_LOADING_P,'textcolorstyles'),//make_status_of_zbx()
							CProfile::get('web.dashboard.hats.hat_stszbx.state',1)
							);
		$zbx_stat->addHeader(S_STATUS_OF_ZABBIX,array($refresh_menu));
		$right_tab->addRow($zbx_stat);
	}
//----------------

// System status
	$refresh_menu = new CIcon(S_MENU, 'iconmenu', 'create_page_menu(event,"hat_syssum");');
	$sys_stat = new CWidget('hat_syssum',
						new CSpan(S_LOADING_P,'textcolorstyles'),//make_system_summary()
						CProfile::get('web.dashboard.hats.hat_syssum.state',1)
						);
	$sys_stat->addHeader(S_SYSTEM_STATUS,array($refresh_menu));
	$right_tab->addRow($sys_stat);
//----------------

// Host status
	$refresh_menu = get_icon('menu', array('menu' => 'hat_hoststat'));
	$hoststat = new CWidget('hat_hoststat',
						new CSpan(S_LOADING_P,'textcolorstyles'),//make_system_summary()
						CProfile::get('web.dashboard.hats.hat_hoststat.state',1)
						);
	$hoststat->addHeader(S_HOST_STATUS_STATUS,array($refresh_menu));
	$right_tab->addRow($hoststat);
//----------------

// Last Issues
	$refresh_menu = get_icon('menu', array('menu' => 'hat_lastiss'));
	$lastiss = new CWidget('hat_lastiss',
						new CSpan(S_LOADING_P,'textcolorstyles'),//make_latest_issues(),
						CProfile::get('web.dashboard.hats.hat_lastiss.state',1)
						);
	$lastiss->addHeader(S_LAST_20_ISSUES,array($refresh_menu));
	$right_tab->addRow($lastiss);
//----------------

// Web monioring
	$refresh_menu = get_icon('menu', array('menu' => 'hat_webovr'));
	$web_mon = new CWidget('hat_webovr',
						new CSpan(S_LOADING_P,'textcolorstyles'),//make_webmon_overview()
						CProfile::get('web.dashboard.hats.hat_webovr.state',1)
						);
	$web_mon->addHeader(S_WEB_MONITORING,array($refresh_menu));
	$right_tab->addRow($web_mon);
//----------------

// Discovery Info
	$drules = DBfetch(DBselect('SELECT COUNT(d.druleid) as cnt FROM drules d WHERE '.DBin_node('d.druleid').' AND d.status='.DRULE_STATUS_ACTIVE));

	if(($drules['cnt'] > 0) && check_right_on_discovery(PERM_READ_ONLY)){

		$refresh_tab[] = array(	'id' => 'hat_dscvry','frequency'  => CProfile::get('web.dahsboard.rf_rate.hat_dscvry',60));

		$refresh_menu = get_icon('menu', array('menu' => 'hat_dscvry'));
		$web_mon = new CWidget('hat_dscvry',
							new CSpan(S_LOADING_P,'textcolorstyles'),//make_discovery_status()
							CProfile::get('web.dashboard.hats.hat_dscvry.state',1)
							);
		$web_mon->addHeader(S_DISCOVERY_STATUS,array($refresh_menu));
		$right_tab->addRow($web_mon);
//----------------
	}

	add_doll_objects($refresh_tab);

/*
	$right_tab->addRow(create_hat(
			S_GRAPH,
			null,//make_webmon_overview(),
			null,
			'hat_custom',
			CProfile::get('web.dashboard.hats.hat_custom.state',1)
		));
*/
	$td_l = new CCol($left_tab);
	$td_l->setAttribute('valign','top');

	$td_r = new CCol($right_tab);
	$td_r->setAttribute('valign','top');

	$outer_table = new CTable();
	$outer_table->setAttribute('border',0);
	$outer_table->setCellPadding(1);
	$outer_table->setCellSpacing(1);
	$outer_table->addRow(array($td_l,$td_r));

	$dashboard_wdgt->addItem($outer_table);
	$dashboard_wdgt->show();
?>
<script type="text/javascript">
//<!--<![CDATA[
function addPopupValues(list){
	if(!isset('object', list)){
		throw("Error hash attribute 'list' doesn't contain 'object' index");
		return false;
	}

	if('undefined' == typeof(Ajax)){
		throw("Prototype.js lib is required!");
		return false;
	}

	var favorites = {'graphid': 1,'itemid': 1,'screenid': 1,'slideshowid': 1,'sysmapid': 1};
	if(isset(list.object, favorites)){
		var params = {
			'favobj': 	list.object,
			'favid[]': 	list.values,
			'action':	'add'
		}

		send_params(params);
	}
}
//]]> -->
</script>
<?php

include_once('include/page_footer.php');

?>

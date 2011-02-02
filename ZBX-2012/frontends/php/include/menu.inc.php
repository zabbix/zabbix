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
global $ZBX_MENU;

$ZBX_MENU = array(
	'view'=>array(
			'label'			=> S_MONITORING,
			'user_type'		=> USER_TYPE_ZABBIX_USER,
			'node_perm'		=> PERM_READ_LIST,
			'default_page_id'	=> 0,
			'pages'=>array(
				array(
						'url'=>'dashboard.php',
						'label'=>S_DASHBOARD,
						'sub_pages'=>array('dashconf.php')
					),
				array(
						'url'=>'overview.php',
						'label'=>S_OVERVIEW
					),
				array(
						'url'=>'httpmon.php',
						'label'=>S_WEB,
						'sub_pages'=>array('httpdetails.php')
					),
				array(
						'url'=>'latest.php',
						'label'=>S_LATEST_DATA,
						'sub_pages'=>array('history.php','chart.php')
					),
				array(
						'url'=>'tr_status.php',
						'label'=>S_TRIGGERS,
						'sub_pages'=>array('acknow.php','tr_comments.php','chart4.php','scripts_exec.php')
					),
				array(
						'url'=>'events.php',
						'label'=>S_EVENTS,
						'sub_pages'=>array('tr_events.php')
					),
				array(
						'url'=>'charts.php',
						'label'=>S_GRAPHS,
						'sub_pages'=>array('chart2.php','chart3.php','chart6.php','chart7.php')
					),
				array(
						'url'=>'screens.php',
						'label'=>S_SCREENS,
						'sub_pages'=>array('slides.php')
					),
				array(
						'url'=>'maps.php',
						'label'=>S_MAPS,
						'sub_pages'=>array('map.php')
					),
				array(
						'url'=>'discovery.php',
						'label'=>S_DISCOVERY,
						'user_type'=>USER_TYPE_ZABBIX_ADMIN),
				array(
						'url'=>'srv_status.php',
						'label'=>S_IT_SERVICES,
						'forse_disable_all_nodes' => true,
						'sub_pages'=>array('report3.php','report7.php','chart_sla.php','chart5.php')
					),
				array('url'=>'vtext.php'),
				array('url'=>'chart3.php'),
				array('url'=>'imgstore.php'),
				array('url'=>'search.php'),
				array('url'=>'jsrpc.php')
			)
	),
	'cm'=>array(
			'label'			=> S_INVENTORY,
			'user_type'		=> USER_TYPE_ZABBIX_USER,
			'node_perm'		=> PERM_READ_LIST,
			'default_page_id'	=> 0,
			'pages'=>array(
				array('url'=>'hostprofiles.php'	,'label'=>S_HOSTS	)
			)
	),
	'reports'=>array(
			'label'			=> S_REPORTS,
			'user_type'		=> USER_TYPE_ZABBIX_USER,
			'node_perm'		=> PERM_READ_LIST,
			'default_page_id'	=> 0,
			'pages'=>array(
				array(
					'url'=>'report1.php',
					'label'=>S_STATUS_OF_ZABBIX,
					'user_type'=>USER_TYPE_SUPER_ADMIN
				),
				array(
					'url'=>'report2.php',
					'label'=>S_AVAILABILITY_REPORT
				),
				array(
					'url'=>'report5.php',
					'label'=>S_TRIGGERS_TOP_100
				),
				array(
						'url'=>'report6.php',
						'label'=>S_BAR_REPORTS,
						'sub_pages'=>array('popup_period.php','popup_bitem.php','chart_bar.php')
					),
				array('url'=>'popup.php'),
				array('url'=>'popup_right.php')
			),
	),
	'config'=>array(
			'label'			=> S_CONFIGURATION,
			'user_type'		=> USER_TYPE_ZABBIX_ADMIN,
			'node_perm'		=> PERM_READ_LIST,
			'default_page_id'	=> 0,
			'forse_disable_all_nodes'=> true,
			'pages'=>array(
				array(
						'url'=>'hostgroups.php',
						'label'=>S_HOST_GROUPS
					),
				array(
						'url'=>'templates.php',
						'label'=>S_TEMPLATES
					),
				array(
						'url'=>'hosts.php',
						'label'=>S_HOSTS,
						'sub_pages'=>array('items.php','triggers.php','graphs.php','applications.php','tr_logform.php','tr_testexpr.php','popup_trexpr.php','popup_gitem.php')
					),
				array(
						'url'=>'maintenance.php',
						'label'=>S_MAINTENANCE
					),
				array(
						'url'=>'httpconf.php',
						'label'=>S_WEB,
						'sub_pages'=>array('popup_httpstep.php')
					),
				array('url'=>'actionconf.php',
						'label'=>S_ACTIONS
					),
				array('url'=>'screenconf.php',
						'label' => S_SCREENS,
						'sub_pages' => array('screenedit.php')
					),
				array('url'=>'slideconf.php',
						'label' => S_SLIDES,
					),
				array('url'=>'sysmaps.php',
						'label' => S_MAPS,
						'sub_pages' => array('image.php','sysmap.php','popup_link_tr.php')
					),
				array('url' => 'discoveryconf.php', 'label' => S_DISCOVERY),
				array('url'=>'services.php',
						'label'=>S_IT_SERVICES,
						'sub_pages'=>array('services_form.php')
					),
			)
	),
	'admin'=>array(
			'label'			=> S_ADMINISTRATION,
			'user_type'		=> USER_TYPE_SUPER_ADMIN,
			'node_perm'		=> PERM_READ_WRITE,
			'default_page_id'	=> 1,
			'forse_disable_all_nodes'=> true,
			'pages'=>array(
				array(
						'url'=>'config.php',
						'label'=>S_GENERAL,
						'sub_pages'=>array()
					),
				array('url'=>'nodes.php',
						'label'=>S_DM,
						'sub_pages'=>array('proxies.php')
					),
				array('url'=>'authentication.php',
						'label'=>S_AUTHENTICATION
					),
				array('url'=>'usergrps.php',
						'label'=>S_USERS,
						'sub_pages'=>array('users.php','popup_usrgrp.php','popup_users.php')
					),
				array('url'=>'media_types.php',
						'label'=>S_MEDIA_TYPES
					),
				array('url'=>'scripts.php',
						'label'=>S_SCRIPTS
					),
				array('url'=>'auditlogs.php',
						'label'=>S_AUDIT,
						'sub_pages'=>array('auditacts.php')
					),
				array('url'=>'queue.php',
						'label'=>S_QUEUE
					),
				array('url'=>'report4.php',
						'label'=>S_NOTIFICATIONS
					),
				array('url'=>'locales.php',
						'label'=>S_LOCALES
					),
				array('url'=>'instal.php',
						'label'=>S_INSTALLATION,
						'sub_pages'=>array('setup.php','warning.php')
					)
			)
	),
	'login'=>array(
			'label'			=> S_LOGIN,
			'user_type'		=> 	0,
			'default_page_id'	=> 0,
			'hide_node_selection' => 1,
			'forse_disable_all_nodes'=> true,
			'pages'=>array(
				array('url'=>'index.php','sub_pages'=>array('profile.php', 'popup_media.php'))
				)
			)
	);


function zbx_construct_menu(&$main_menu, &$sub_menus, &$page) {
	global $ZBX_MENU, $USER_DETAILS;

	$denyed_page_requested = false;

/* NOTE - menu array format:
	first level:
		'label' 		= main menu title.
		'default_page_id	= default page url from 'pages' then opened menu.
		'pages'			= collection of pages which are displayed from this menu.
					these pages are saved a last visited submenu of main menu.

	second level (pages):
		'url'	= 	real url for this page
		'label'	= 	submenu title, if missing, menu skipped, but remembered as last visited page.
		'sub_pages'	= collection of pages for displaying but not remembered as last visited.
*/

//SDI($USER_DETAILS);
	$page_exists = false;
	$deny = !defined('ZBX_PAGE_NO_AUTHORIZATION');

	foreach($ZBX_MENU as $label => $menu){
// Check to show menu or not
		$show_menu = true;

		if(isset($menu['user_type'])){
			$show_menu &= ($menu['user_type'] <= $USER_DETAILS['type']);
		}

		if($label == 'login'){
			$show_menu = false;
		}

//---
		$menu_class = 'horizontal_menu_n';
		$sub_menus[$label] = array();
		foreach($menu['pages'] as $id => $sub_page){
			$show_sub_menu = true;
// show check
			if(!isset($sub_page['label'])) $show_sub_menu = false;
			if(!isset($sub_page['user_type'])) $sub_page['user_type'] = $menu['user_type'];

			if($USER_DETAILS['type'] < $sub_page['user_type']){
				$show_sub_menu = false;
			}
//----------
			$row = array('menu_text' => isset($sub_page['label'])?$sub_page['label']:'',
							'menu_url'=> $sub_page['url'],
							'class'=> 'highlight',
							'selected'=> false);

			$sub_menu_active = ($page['file'] == $sub_page['url']);
			$sub_menu_active |= (isset($sub_page['sub_pages']) && str_in_array($page['file'], $sub_page['sub_pages']));

			if($sub_menu_active){
// PERMISSION CHECK
				$deny &= (($USER_DETAILS['type'] < $menu['user_type']) || ($USER_DETAILS['type'] < $sub_page['user_type']));

// END OF PERMISSION CHECK
				$menu_class = 'active';
				$page_exists = true;
				$page['menu'] = $label;
				$row['selected'] = true;

				if(!defined('ZBX_PAGE_NO_MENU'))
					CProfile::update('web.menu.'.$label.'.last', $sub_page['url'], PROFILE_TYPE_STR);
			}

			if($show_sub_menu) $sub_menus[$label][] = $row;
		}

		if($page_exists && !defined('ZBX_NOT_ALLOW_ALL_NODES') &&
			(isset($menu['forse_disable_all_nodes']) || isset($sub_page['forse_disable_all_nodes'])))
		{
			define('ZBX_NOT_ALLOW_ALL_NODES', 1);
		}
//SDI($label.' : '.$show_menu.' : '.$deny);

		if($page_exists && $deny){
			$denyed_page_requested = true;
		}

		if(!$show_menu){
			unset($sub_menus[$label]);
			continue;
		}

//		$menu_url = CProfile::get('web.menu.'.$label.'.last',false);
		$menu_url = $sub_menus[$label][$menu['default_page_id']]['menu_url'];

		$mmenu_entry = new CCol($menu['label'], $menu_class);
		$mmenu_entry->setAttribute('id', $label);
		$mmenu_entry->addAction('onclick', "javascript: redirect('$menu_url');");
		$mmenu_entry->addAction('onmouseover', 'javascript: MMenu.mouseOver("'.$label.'");');
		$mmenu_entry->addAction('onmouseout', 'javascript: MMenu.mouseOut();');

		array_push($main_menu, $mmenu_entry);
	}

	if(!$page_exists && ($page['type']!=PAGE_TYPE_XML)&&($page['type']!=PAGE_TYPE_TEXT_FILE)){
		$denyed_page_requested = true;
	}

return $denyed_page_requested;
}

function zbx_define_menu_restrictions($page, $ZBX_MENU){
	foreach($ZBX_MENU as $sid => $section){
		foreach($section['pages'] as $pid => $menu_page) {
			if (($menu_page['url'] == $page['file']) || (isset($menu_page['sub_pages']) && str_in_array($page['file'], $menu_page['sub_pages']))) {
				if(isset($section['forse_disable_all_nodes']) && !defined('ZBX_NOT_ALLOW_ALL_NODES')) {
					define('ZBX_NOT_ALLOW_ALL_NODES', 1);
				}
				if(isset($section['hide_node_selection']) && !defined('ZBX_HIDE_NODE_SELECTION')) {
					define('ZBX_HIDE_NODE_SELECTION', 1);
				}
				return;
			}
		}
	}
}

?>

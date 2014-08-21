<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


global $ZBX_MENU;

$ZBX_MENU = array(
	'view' => array(
		'label'				=> _('Monitoring'),
		'user_type'			=> USER_TYPE_ZABBIX_USER,
		'node_perm'			=> PERM_READ_LIST,
		'default_page_id'	=> 0,
		'pages' => array(
			array(
				'url' => 'dashboard.php',
				'label' => _('Dashboard'),
				'sub_pages' => array('dashconf.php')
			),
			array(
				'url' => 'overview.php',
				'label' => _('Overview')
			),
			array(
				'url' => 'httpmon.php',
				'label' => _('Web'),
				'sub_pages' => array('httpdetails.php')
			),
			array(
				'url' => 'latest.php',
				'label' => _('Latest data'),
				'sub_pages' => array('history.php', 'chart.php')
			),
			array(
				'url' => 'tr_status.php',
				'label' => _('Triggers'),
				'sub_pages' => array('acknow.php', 'tr_comments.php', 'chart4.php', 'scripts_exec.php')
			),
			array(
				'url' => 'events.php',
				'label' => _('Events'),
				'sub_pages' => array('tr_events.php')
			),
			array(
				'url' => 'charts.php',
				'label' => _('Graphs'),
				'sub_pages' => array('chart2.php', 'chart3.php', 'chart6.php', 'chart7.php')
			),
			array(
				'url' => 'screens.php',
				'label' => _('Screens'),
				'sub_pages' => array('slides.php')
			),
			array(
				'url' => 'maps.php',
				'label' => _('Maps'),
				'sub_pages' => array('map.php')
			),
			array(
				'url' => 'discovery.php',
				'label' => _('Discovery'),
				'user_type' => USER_TYPE_ZABBIX_ADMIN
			),
			array(
				'url' => 'srv_status.php',
				'label' => _('IT services'),
				'force_disable_all_nodes' => true,
				'sub_pages' => array('report3.php', 'chart5.php')
			),
			array(
				'url' => 'chart3.php'
			),
			array(
				'url' => 'imgstore.php'
			),
			array(
				'url' => 'search.php'
			),
			array(
				'url' => 'jsrpc.php'
			)
		)
	),
	'cm' => array(
		'label'				=> _('Inventory'),
		'user_type'			=> USER_TYPE_ZABBIX_USER,
		'node_perm'			=> PERM_READ_LIST,
		'default_page_id'	=> 0,
		'pages' => array(
			array(
				'url' => 'hostinventoriesoverview.php',
				'label' => _('Overview')
			),
			array(
				'url' => 'hostinventories.php',
				'label' => _('Hosts')
			)
		)
	),
	'reports' => array(
		'label'				=> _('Reports'),
		'user_type'			=> USER_TYPE_ZABBIX_USER,
		'node_perm'			=> PERM_READ_LIST,
		'default_page_id'	=> 0,
		'pages' => array(
			array(
				'url' => 'report1.php',
				'label' => _('Status of Zabbix'),
				'user_type' => USER_TYPE_SUPER_ADMIN
			),
			array(
				'url' => 'report2.php',
				'label' => _('Availability report')
			),
			array(
				'url' => 'report5.php',
				'label' => _('Triggers top 100')
			),
			array(
				'url' => 'report6.php',
				'label' => _('Bar reports'),
				'sub_pages' => array('popup_period.php', 'popup_bitem.php', 'chart_bar.php')
			),
			array(
				'url' => 'popup.php'
			),
			array(
				'url' => 'popup_right.php'
			)
		),
	),
	'config' => array(
		'label'				=> _('Configuration'),
		'user_type'			=> USER_TYPE_ZABBIX_ADMIN,
		'node_perm'			=> PERM_READ_LIST,
		'default_page_id'	=> 0,
		'force_disable_all_nodes' => true,
		'pages' => array(
			array(
				'url' => 'conf.import.php'
			),
			array(
				'url' => 'hostgroups.php',
				'label' => _('Host groups')
			),
			array(
				'url' => 'templates.php',
				'label' => _('Templates')
			),
			array(
				'url' => 'hosts.php',
				'label' => _('Hosts'),
				'sub_pages' => array(
					'items.php',
					'triggers.php',
					'graphs.php',
					'applications.php',
					'tr_logform.php',
					'tr_testexpr.php',
					'popup_trexpr.php',
					'host_discovery.php',
					'disc_prototypes.php',
					'trigger_prototypes.php'
				)
			),
			array(
				'url' => 'maintenance.php',
				'label' => _('Maintenance')
			),
			array(
				'url' => 'httpconf.php',
				'label' => _('Web'),
				'sub_pages' => array('popup_httpstep.php')
			),
			array(
				'url' => 'actionconf.php',
				'label' => _('Actions')
			),
			array(
				'url' => 'screenconf.php',
				'label' => _('Screens'),
				'sub_pages' => array('screenedit.php')
			),
			array(
				'url' => 'slideconf.php',
				'label' => _('Slide shows'),
			),
			array(
				'url' => 'sysmaps.php',
				'label' => _('Maps'),
				'sub_pages' => array('image.php', 'sysmap.php')
			),
			array(
				'url' => 'discoveryconf.php',
				'label' => _('Discovery')
			),
			array(
				'url' => 'services.php',
				'label' => _('IT services')
			),
		)
	),
	'admin' => array(
		'label'				=> _('Administration'),
		'user_type'			=> USER_TYPE_SUPER_ADMIN,
		'node_perm'			=> PERM_READ_WRITE,
		'default_page_id'	=> 1,
		'force_disable_all_nodes'=> true,
		'pages' => array(
			array(
				'url' => 'adm.gui.php',
				'label' => _('General'),
				'sub_pages' => array(
					'adm.housekeeper.php',
					'adm.images.php',
					'adm.iconmapping.php',
					'adm.regexps.php',
					'adm.macros.php',
					'adm.valuemapping.php',
					'adm.workingtime.php',
					'adm.triggerseverities.php',
					'adm.triggerdisplayoptions.php',
					'adm.other.php'
				)
			),
			array(
				'url' => 'proxies.php',
				'label' => _('DM'),
				'sub_pages' => array('nodes.php')
			),
			array(
				'url' => 'authentication.php',
				'label' => _('Authentication')
			),
			array(
				'url' => 'usergrps.php',
				'label' => _('Users'),
				'sub_pages' => array('users.php', 'popup_usrgrp.php')
			),
			array(
				'url' => 'media_types.php',
				'label' => _('Media types')
			),
			array(
				'url' => 'scripts.php',
				'label' => _('Scripts')
			),
			array(
				'url' => 'auditlogs.php',
				'label' => _('Audit'),
				'sub_pages' => array('auditacts.php')
			),
			array(
				'url' => 'queue.php',
				'label' => _('Queue')
			),
			array(
				'url' => 'report4.php',
				'label' => _('Notifications')
			),
			array(
				'url' => 'setup.php',
				'label' => _('Installation'),
				'sub_pages' => array('warning.php')
			)
		)
	),
	'login' => array(
		'label'					=> _('Login'),
		'user_type'				=> 0,
		'default_page_id'		=> 0,
		'hide_node_selection'	=> 1,
		'force_disable_all_nodes'=> true,
		'pages' => array(
			array(
				'url' => 'index.php',
				'sub_pages' => array('profile.php', 'popup_media.php')
			)
		)
	)
);

/**
 * NOTE - menu array format:
 * first level:
 *	'label' = main menu title.
 *	'default_page_id	= default page url from 'pages' then opened menu.
 *	'pages' = collection of pages which are displayed from this menu.
 *	these pages are saved a last visited submenu of main menu.
 *
 * second level (pages):
 *	'url' = real url for this page
 *	'label' =  submenu title, if missing, menu skipped, but remembered as last visited page.
 *	'sub_pages' = collection of pages for displaying but not remembered as last visited.
 */
function zbx_construct_menu(&$main_menu, &$sub_menus, &$page) {
	global $ZBX_MENU, $USER_DETAILS;

	$denied_page_requested = false;
	$page_exists = false;
	$deny = !defined('ZBX_PAGE_NO_AUTHORIZATION');

	foreach ($ZBX_MENU as $label => $menu) {
		$show_menu = true;

		if (isset($menu['user_type'])) {
			$show_menu &= ($menu['user_type'] <= $USER_DETAILS['type']);
		}
		if ($label == 'login') {
			$show_menu = false;
		}

		$menu_class = 'horizontal_menu_n';
		$sub_menus[$label] = array();
		foreach ($menu['pages'] as $sub_page) {
			$show_sub_menu = true;

			// show check
			if (!isset($sub_page['label'])) {
				$show_sub_menu = false;
			}
			if (!isset($sub_page['user_type'])) {
				$sub_page['user_type'] = $menu['user_type'];
			}
			if ($USER_DETAILS['type'] < $sub_page['user_type']) {
				$show_sub_menu = false;
			}

			$row = array(
				'menu_text' => isset($sub_page['label']) ? $sub_page['label'] : '',
				'menu_url' => $sub_page['url'],
				'class' => 'highlight',
				'selected' => false
			);
			$sub_menu_active = ($page['file'] == $sub_page['url']);
			$sub_menu_active |= (isset($sub_page['sub_pages']) && str_in_array($page['file'], $sub_page['sub_pages']));

			if ($sub_menu_active) {
				// permition check
				$deny &= ($USER_DETAILS['type'] < $menu['user_type'] || $USER_DETAILS['type'] < $sub_page['user_type']);

				$menu_class = 'active';
				$page_exists = true;
				$page['menu'] = $label;
				$row['selected'] = true;

				if (!defined('ZBX_PAGE_NO_MENU')) {
					CProfile::update('web.menu.'.$label.'.last', $sub_page['url'], PROFILE_TYPE_STR);
				}
			}

			if ($show_sub_menu) {
				$sub_menus[$label][] = $row;
			}
		}

		if ($page_exists && !defined('ZBX_NOT_ALLOW_ALL_NODES') && (isset($menu['force_disable_all_nodes']) || isset($sub_page['force_disable_all_nodes']))) {
			define('ZBX_NOT_ALLOW_ALL_NODES', 1);
		}

		if ($page_exists && $deny) {
			$denied_page_requested = true;
		}

		if (!$show_menu) {
			unset($sub_menus[$label]);
			continue;
		}

		$menu_url = $sub_menus[$label][$menu['default_page_id']]['menu_url'];
		$mmenu_entry = new CCol($menu['label'], $menu_class);
		$mmenu_entry->setAttribute('id', $label);
		$mmenu_entry->addAction('onclick', 'javascript: redirect(\''.$menu_url.'\');');
		$mmenu_entry->addAction('onmouseover', 'javascript: MMenu.mouseOver(\''.$label.'\');');
		$mmenu_entry->addAction('onmouseout', 'javascript: MMenu.mouseOut();');
		array_push($main_menu, $mmenu_entry);
	}

	if (!$page_exists && $page['type'] != PAGE_TYPE_XML && $page['type'] != PAGE_TYPE_CSV && $page['type'] != PAGE_TYPE_TEXT_FILE) {
		$denied_page_requested = true;
	}

	return $denied_page_requested;
}

function zbx_define_menu_restrictions($page, $ZBX_MENU) {
	foreach ($ZBX_MENU as $section) {
		foreach ($section['pages'] as $pid => $menu_page) {
			if ($menu_page['url'] == $page['file'] || (isset($menu_page['sub_pages']) && str_in_array($page['file'], $menu_page['sub_pages']))) {
				if (isset($section['force_disable_all_nodes']) && !defined('ZBX_NOT_ALLOW_ALL_NODES')) {
					define('ZBX_NOT_ALLOW_ALL_NODES', 1);
				}
				if (isset($section['hide_node_selection']) && !defined('ZBX_HIDE_NODE_SELECTION')) {
					define('ZBX_HIDE_NODE_SELECTION', 1);
				}
				return null;
			}
		}
	}
}

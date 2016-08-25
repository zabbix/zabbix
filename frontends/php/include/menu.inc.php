<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
function zbx_construct_menu(&$main_menu, &$sub_menus, &$page, $action = null) {
	$zbx_menu = [
		'view' => [
			'label' => _('Monitoring'),
			'user_type' => USER_TYPE_ZABBIX_USER,
			'default_page_id' => 0,
			'pages' => [
				[
					'url' => 'zabbix.php',
					'action' => 'dashboard.view',
					'active_if' => ['dashboard.view'],
					'label' => _('Dashboard'),
					'sub_pages' => ['dashconf.php']
				],
				[
					'url' => 'zabbix.php',
					'action' => 'problem.view',
					'active_if' => ['problem.view'],
					'label' => _('Problems'),
					'sub_pages' => ['tr_events.php']
				],
				[
					'url' => 'overview.php',
					'label' => _('Overview')
				],
				[
					'url' => 'zabbix.php',
					'action' => 'web.view',
					'active_if' => ['web.view'],
					'label' => _('Web'),
					'sub_pages' => ['httpdetails.php']
				],
				[
					'url' => 'latest.php',
					'label' => _('Latest data'),
					'sub_pages' => ['history.php', 'chart.php']
				],
				[
					'url' => 'tr_status.php',
					'active_if' => ['acknowledge.edit'],
					'label' => _('Triggers'),
					'sub_pages' => ['tr_comments.php', 'chart4.php', 'scripts_exec.php']
				],
				[
					'url' => 'charts.php',
					'label' => _('Graphs'),
					'sub_pages' => ['chart2.php', 'chart3.php', 'chart6.php', 'chart7.php']
				],
				[
					'url' => 'screens.php',
					'label' => _('Screens'),
					'sub_pages' => [
						'screenconf.php',
						'screenedit.php',
						'screen.import.php',
						'slides.php',
						'slideconf.php'
					]
				],
				[
					'url' => 'zabbix.php',
					'action' => 'map.view',
					'active_if' => ['map.view'],
					'label' => _('Maps'),
					'sub_pages' => ['image.php', 'sysmaps.php', 'sysmap.php', 'map.php', 'map.import.php']
				],
				[
					'url' => 'zabbix.php',
					'action' => 'discovery.view',
					'active_if' => ['discovery.view'],
					'label' => _('Discovery'),
					'user_type' => USER_TYPE_ZABBIX_ADMIN
				],
				[
					'url' => 'srv_status.php',
					'active_if' => ['report.services'],
					'label' => _('IT services'),
					'sub_pages' => ['chart5.php']
				],
				[
					'url' => 'chart3.php'
				],
				[
					'url' => 'imgstore.php'
				],
				[
					'url' => 'search.php'
				],
				[
					'url' => 'jsrpc.php'
				]
			]
		],
		'cm' => [
			'label' => _('Inventory'),
			'user_type' => USER_TYPE_ZABBIX_USER,
			'default_page_id' => 0,
			'pages' => [
				[
					'url' => 'hostinventoriesoverview.php',
					'label' => _('Overview')
				],
				[
					'url' => 'hostinventories.php',
					'label' => _('Hosts')
				]
			]
		],
		'reports' => [
			'label' => _('Reports'),
			'user_type' => USER_TYPE_ZABBIX_USER,
			'default_page_id' => 0,
			'pages' => [
				[
					'url' => 'zabbix.php',
					'action' => 'report.status',
					'active_if' => ['report.status'],
					'label' => _('Status of Zabbix'),
					'user_type' => USER_TYPE_SUPER_ADMIN
				],
				[
					'url' => 'report2.php',
					'label' => _('Availability report')
				],
				[
					'url' => 'toptriggers.php',
					'label' => _('Triggers top 100')
				],
				[
					'url' => 'auditlogs.php',
					'label' => _('Audit'),
					'user_type' => USER_TYPE_SUPER_ADMIN
				],
				[
					'url' => 'auditacts.php',
					'label' => _('Action log'),
					'user_type' => USER_TYPE_SUPER_ADMIN
				],
				[
					'url' => 'report4.php',
					'label' => _('Notifications'),
					'user_type' => USER_TYPE_ZABBIX_ADMIN
				],
				[
					'url' => 'popup.php'
				]
			]
		],
		'config' => [
			'label' => _('Configuration'),
			'user_type' => USER_TYPE_ZABBIX_ADMIN,
			'default_page_id' => 0,
			'pages' => [
				[
					'url' => 'conf.import.php'
				],
				[
					'url' => 'hostgroups.php',
					'label' => _('Host groups')
				],
				[
					'url' => 'templates.php',
					'label' => _('Templates'),
					'sub_pages' => [
						'screenconf.php',
						'screenedit.php'
					]
				],
				[
					'url' => 'hosts.php',
					'label' => _('Hosts'),
					'sub_pages' => [
						'items.php',
						'triggers.php',
						'graphs.php',
						'applications.php',
						'tr_logform.php',
						'tr_testexpr.php',
						'popup_trexpr.php',
						'host_discovery.php',
						'disc_prototypes.php',
						'trigger_prototypes.php',
						'host_prototypes.php',
						'httpconf.php',
						'popup_httpstep.php'
					]
				],
				[
					'url' => 'maintenance.php',
					'label' => _('Maintenance')
				],
				[
					'url' => 'actionconf.php',
					'label' => _('Actions')
				],
				[
					'user_type' => USER_TYPE_SUPER_ADMIN,
					'url' => 'correlation.php',
					'label' => _('Event correlation')
				],
				[
					'url' => 'discoveryconf.php',
					'label' => _('Discovery')
				],
				[
					'url' => 'services.php',
					'label' => _('IT services')
				]
			]
		],
		'admin' => [
			'label' => _('Administration'),
			'user_type' => USER_TYPE_SUPER_ADMIN,
			'default_page_id' => 0,
			'pages' => [
				[
					'url' => 'adm.gui.php',
					'label' => _('General'),
					'sub_pages' => [
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
					]
				],
				[
					'url' => 'zabbix.php',
					'action' => 'proxy.list',
					'active_if' => ['proxy.edit', 'proxy.list'],
					'label' => _('Proxies')
				],
				[
					'url' => 'authentication.php',
					'label' => _('Authentication')
				],
				[
					'url' => 'usergrps.php',
					'label' => _('User groups')
				],
				[
					'url' => 'users.php',
					'label' => _('Users'),
					'sub_pages' => [
						'popup_usrgrp.php'
					]
				],
				[
					'url' => 'zabbix.php',
					'action' => 'mediatype.list',
					'active_if' => ['mediatype.edit', 'mediatype.list'],
					'label' => _('Media types')
				],
				[
					'url' => 'zabbix.php',
					'action' => 'script.list',
					'active_if' => ['script.edit', 'script.list'],
					'label' => _('Scripts')
				],
				[
					'url' => 'queue.php',
					'label' => _('Queue')
				]
			]
		],
		'login' => [
			'label' => _('Login'),
			'user_type' => 0,
			'default_page_id' => 0,
			'pages' => [
				[
					'url' => 'index.php',
					'sub_pages' => ['profile.php', 'popup_media.php']
				]
			]
		]
	];

	$denied_page_requested = false;
	$page_exists = false;
	$deny = true;

	foreach ($zbx_menu as $label => $menu) {
		$show_menu = true;

		if (isset($menu['user_type'])) {
			$show_menu &= ($menu['user_type'] <= CWebUser::$data['type']);
		}
		if ($label == 'login') {
			$show_menu = false;
		}

		$menu_class = null;
		$sub_menus[$label] = [];

		foreach ($menu['pages'] as $sub_page) {
			$show_sub_menu = true;

			// show check
			if (!isset($sub_page['label'])) {
				$show_sub_menu = false;
			}
			if (!isset($sub_page['user_type'])) {
				$sub_page['user_type'] = $menu['user_type'];
			}
			if (CWebUser::$data['type'] < $sub_page['user_type']) {
				$show_sub_menu = false;
			}

			$row = [
				'menu_text' => array_key_exists('label', $sub_page) ? $sub_page['label'] : '',
				'menu_url' => $sub_page['url'],
				'menu_action' => array_key_exists('action', $sub_page) ? $sub_page['action'] : null,
				'selected' => false
			];

			if ($action == null) {
				$sub_menu_active = ($page['file'] == $sub_page['url']);

				// Quick and dirty hack to display correct menu for templated screens.
				if (array_key_exists('sub_pages', $sub_page)) {
					if ((str_in_array('screenconf.php', $sub_page['sub_pages'])
							|| str_in_array('screenedit.php', $sub_page['sub_pages']))
								&& ($page['file'] === 'screenconf.php' || $page['file'] === 'screenedit.php')) {
						if ($label === 'view') {
							$sub_menu_active |= getRequest('templateid') ? false : true;
						}
						elseif ($label === 'config') {
							$sub_menu_active |= getRequest('templateid') ? true : false;
						}
					}
					elseif (str_in_array($page['file'], $sub_page['sub_pages'])) {
						$sub_menu_active |= true;
					}
				}
				else {
					$sub_menu_active |= false;
				}
			}
			else {
				$sub_menu_active = array_key_exists('active_if', $sub_page) && str_in_array($action, $sub_page['active_if']);
			}

			if ($sub_menu_active) {
				// permission check
				$deny &= (CWebUser::$data['type'] < $menu['user_type'] || CWebUser::$data['type'] < $sub_page['user_type']);

				$menu_class = 'selected';
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

		if ($page_exists && $deny) {
			$denied_page_requested = true;
		}

		if (!$show_menu) {
			unset($sub_menus[$label]);
			continue;
		}

		if ($sub_menus[$label][$menu['default_page_id']]['menu_action'] === null) {
			$menu_url = $sub_menus[$label][$menu['default_page_id']]['menu_url'];
		}
		else {
			$menu_url = $sub_menus[$label][$menu['default_page_id']]['menu_url'].'?action='.$sub_menus[$label][$menu['default_page_id']]['menu_action'];
		}
		$mmenu_entry = (new CListItem(
			(new CLink($menu['label']))
				->setAttribute('tabindex', 0)
		))
			->addClass($menu_class)
			->setId($label);
		$mmenu_entry->onClick('javascript: MMenu.mouseOver(\''.$label.'\');');
		array_push($main_menu, $mmenu_entry);
	}

	if (!$page_exists && $page['type'] != PAGE_TYPE_XML && $page['type'] != PAGE_TYPE_CSV && $page['type'] != PAGE_TYPE_TEXT_FILE) {
		$denied_page_requested = true;
	}

	return $denied_page_requested;
}

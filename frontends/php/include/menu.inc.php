<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


<<<<<<< HEAD
$user_type = CWebUser::getType();
$menu = APP::Component()->get('menu.main');
$menu
	->add(_('Monitoring'), [
		'alias' => [],
		'items' => [
			_('Dashboard') => [
				'action' => 'dashboard.view',
				'alias' => ['dashboard.list', 'dashboard.view']
			],
			_('Problems') => [
				'action' => 'problem.view',
				'alias' => ['problem.view', 'acknowledge.edit', 'tr_events.php']
			],
			_('Overview') => [
				'action' => 'overview.php'
			],
			_('Web') => [
				'action' => 'web.view',
				'alias' => ['httpdetails.php']
			],
			_('Latest data') => [
				'action' => 'latest.php',
				'alias' => ['history.php', 'chart.php']
			],
			_('Graphs') => [
				'action' => 'charts.php',
				'alias' => ['chart2.php', 'chart3.php', 'chart6.php', 'chart7.php']
			],
			_('Screens') => [
				'action' => 'screens.php',
				'alias' => ['screenconf.php', 'screenedit.php', 'screen.import.php', 'slides.php', 'slideconf.php']
			],
			_('Maps') => [
				'action' => 'map.view',
				'alias' => ['image.php', 'sysmaps.php', 'sysmap.php', 'map.php', 'map.import.php']
			],
			_('Services') => [
				'action' => 'srv_status.php',
				'alias' => ['report.services', 'chart5.php']
=======
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
					'active_if' => ['dashboard.list', 'dashboard.view'],
					'label' => _('Dashboard'),
				],
				[
					'url' => 'zabbix.php',
					'action' => 'problem.view',
					'active_if' => ['problem.view', 'acknowledge.edit'],
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
					'url' => 'zabbix.php',
					'action' => 'latest.view',
					'active_if' => ['latest.view'],
					'label' => _('Latest data'),
					'sub_pages' => ['history.php', 'chart.php']
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
					'label' => _('Services'),
					'sub_pages' => ['chart5.php']
				],
				[
					'url' => 'chart3.php'
				],
				[
					'url' => 'imgstore.php'
				],
				[
					'url' => 'zabbix.php',
					'action' => 'search',
					'active_if' => ['search']
				],
				[
					'url' => 'jsrpc.php'
				]
>>>>>>> 9ee19e435ca31e1d2e13589fed4b1a4668c45e10
			]
		],
		'uniqueid' => 'view'
	])
	->add(_('Inventory'), [
		'alias' => [],
		'items' => [
			_('Overview') => [
				'action' => 'hostinventoriesoverview.php'
			],
			_('Hosts') => [
				'action' => 'hostinventories.php'
			]
		],
		'uniqueid' => 'cm'
	])
	->add(_('Reports'), [
		'alias' => [],
		'items' => [
			_('Availability report') => [
				'action' => 'report2.php',
				'alias' => ['chart4.php']
			],
			_('Triggers top 100') => [
				'action' => 'toptriggers.php'
			]
		],
		'uniqueid' => 'reports'
	]);

if ($user_type >= USER_TYPE_ZABBIX_ADMIN) {
	$menu
		->find(_('Monitoring'))
			->insertAfter(_('Maps'), _('Discovery'), [
				'action' => 'discovery.view'
			]);
	$menu->insertAfter(_('Reports'), _('Configuration'), [
		'alias' => ['conf.import.php'],
		'items' => [
			_('Host groups') => [
				'action' => 'hostgroups.php'
			],
			_('Templates') => [
				'action' => 'templates.php'
			],
			_('Hosts') => [
				'action' => 'hosts.php',
				'alias' => ['items.php', 'triggers.php', 'graphs.php', 'applications.php', 'host_discovery.php',
					'disc_prototypes.php', 'trigger_prototypes.php', 'host_prototypes.php', 'httpconf.php'
				]
			],
			_('Maintenance') => [
				'action' => 'maintenance.php'
			],
			_('Actions') => [
				'action' => 'actionconf.php'
			],
			_('Discovery') => [
				'action' => 'discoveryconf.php'
			],
			_('Services') => [
				'action' => 'services.php'
			]
		],
<<<<<<< HEAD
		'uniqueid' => 'config'
	]);

}

if ($user_type == USER_TYPE_SUPER_ADMIN) {
	$menu
		->find(_('Reports'))
			->insertBefore(_('Availability report'), _('System information'), [
				'action' => 'report.status'
			])
			->add(_('Audit'), [
				'action' => 'auditlogs.php'
			])
			->add(_('Action log'), [
				'action' => 'auditacts.php'
			]);

	$menu
		->find(_('Configuration'))
			->insertAfter(_('Actions'), _('Event correlation'), [
				'action' => 'correlation.php'
			]);

	$menu->add(_('Administration'), [
		'alias' => [],
		'items' => [
			_('General') => [
				'action' => 'adm.gui.php',
				'alias' => ['adm.housekeeper.php', 'adm.images.php', 'adm.iconmapping.php', 'adm.regexps.php',
					'adm.macros.php', 'adm.valuemapping.php', 'adm.workingtime.php', 'adm.triggerseverities.php',
					'adm.triggerdisplayoptions.php', 'adm.other.php', 'autoreg.edit', 'module.list', 'module.edit',
					'module.scan'
=======
		'admin' => [
			'label' => _('Administration'),
			'user_type' => USER_TYPE_SUPER_ADMIN,
			'default_page_id' => 0,
			'pages' => [
				[
					'url' => 'zabbix.php',
					'action' => 'gui.edit',
					'label' => _('General'),
					'active_if' => [
						'gui.edit',
						'autoreg.edit',
						'housekeeping.edit',
						'image.list',
						'image.edit',
						'iconmap.list',
						'iconmap.edit',
						'regex.list',
						'regex.edit',
						'macros.edit',
						'valuemap.list',
						'valuemap.edit',
						'workingtime.edit',
						'trigseverity.edit',
						'trigdisplay.edit',
						'miscconfig.edit'
					]
				],
				[
					'url' => 'zabbix.php',
					'action' => 'proxy.list',
					'active_if' => ['proxy.edit', 'proxy.list'],
					'label' => _('Proxies')
				],
				[
					'url' => 'zabbix.php',
					'action' => 'authentication.edit',
					'active_if' => ['authentication.edit', 'authentication.update'],
					'label' => _('Authentication')
				],
				[
					'url' => 'zabbix.php',
					'action' => 'usergroup.list',
					'active_if' => ['usergroup.list', 'usergroup.edit'],
					'label' => _('User groups')
				],
				[
					'url' => 'zabbix.php',
					'action' => 'user.list',
					'active_if' => ['user.edit', 'user.list'],
					'label' => _('Users')
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
>>>>>>> 9ee19e435ca31e1d2e13589fed4b1a4668c45e10
				]
			],
			_('Proxies') => [
				'action' => 'proxy.list',
				'alias' => ['proxy.edit', 'proxy.list']
			],
			_('Authentication') => [
				'action' => 'authentication.edit',
				'alias' => ['authentication.edit', 'authentication.update']
			],
			_('User groups') => [
				'action' => 'usergroup.list',
				'alias' => ['usergroup.edit']
			],
			_('Users') => [
				'action' => 'user.list',
				'alias' => ['user.edit']
			],
			_('Media types') => [
				'action' => 'mediatype.list',
				'alias' => ['mediatype.edit']
			],
			_('Scripts') => [
				'action' => 'script.list',
				'alias' => ['script.edit']
			],
			_('Queue') => [
				'action' => 'queue.php'
			]
		],
		'uniqueid' => 'admin'
	]);
}

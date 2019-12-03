<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


$user_type = CWebUser::getType();
$menu = APP::component()->get('menu.main');
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
			]
		]
	])
	->add('Inventory', [
		'alias' => [],
		'items' => [
			_('Overview') => [
				'action' => 'hostinventoriesoverview.php'
			],
			_('Hosts') => [
				'action' => 'hostinventories.php'
			]
		]
	])
	->add('Reports', [
		'alias' => [],
		'items' => [
			_('Availability report') => [
				'action' => 'report2.php',
				'alias' => ['chart4.php']
			],
			_('Triggers top 100') => [
				'action' => 'toptriggers.php'
			]
		]
	]);

if ($user_type >= USER_TYPE_ZABBIX_ADMIN) {
	$menu
		->find('Monitoring')
			->insertAfter('Maps', 'Discovery', [
				'action' => 'discovery.view'
			]);
	$menu->insertAfter('Reports', 'Configuration', [
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
		]
	]);

}

if ($user_type == USER_TYPE_SUPER_ADMIN) {
	$menu
		->find('Reports')
			->insertBefore('Availability report', 'System information', [
				'action' => 'report.status'
			])
			->add('Audit', [
				'action' => 'auditlogs.php'
			])
			->add('Action log', [
				'action' => 'auditacts.php'
			])
			->add('Audit', [
				'Notifications' => 'report4.php'
			]);

	$menu
		->find('Configuration')
			->insertAfter('Actions', 'Event correlation', [
				'action' => 'correlation.php'
			]);

	$menu->add('Administration', [
		'alias' => [],
		'items' => [
			_('General') => [
				'action' => 'adm.gui.php',
				'alias' => ['adm.housekeeper.php', 'adm.images.php', 'adm.iconmapping.php', 'adm.regexps.php',
					'adm.macros.php', 'adm.valuemapping.php', 'adm.workingtime.php', 'adm.triggerseverities.php',
					'adm.triggerdisplayoptions.php', 'adm.other.php', 'autoreg.edit', 'module.list', 'module.edit',
					'module.scan'
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
		]
	]);
}

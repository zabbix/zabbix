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


/**
 * @return CMenu
 */
function getMainMenu(): CMenu {
	$menu = new CMenu([
		(new CMenuItem(_('Monitoring')))
			->setId('view')
			->setIcon('icon-monitoring')
			->setSubMenu(new Cmenu([
				(new CMenuItem(_('Dashboard')))
					->setAction('dashboard.view')
					->setAliases(['dashboard.list']),
				(new CMenuItem(_('Problems')))
					->setAction('problem.view')
					->setAliases(['tr_events.php']),
				(new CMenuItem(_('Hosts')))
					->setAction('host.view')
					->setAliases(['web.view', 'charts.view', 'chart2.php', 'chart3.php', 'chart6.php', 'chart7.php',
						'httpdetails.php', 'host_screen.php'
					]),
				(new CMenuItem(_('Overview')))
					->setUrl(new CUrl('overview.php'), 'overview.php'),
				(new CMenuItem(_('Latest data')))
					->setAction('latest.view')
					->setAliases(['history.php', 'chart.php']),
				(new CMenuItem(_('Screens')))
					->setUrl(new CUrl('screens.php'), 'screens.php')
					->setAliases(['screenconf.php', 'screenedit.php', 'screen.import.php', 'slides.php',
						'slideconf.php'
					]),
				(new CMenuItem(_('Maps')))
					->setAction('map.view')
					->setAliases(['image.php', 'sysmaps.php', 'sysmap.php', 'map.php', 'map.import.php']),
				(new CMenuItem(_('Services')))
					->setUrl(new CUrl('srv_status.php'), 'srv_status.php')
					->setAliases(['report.services', 'chart5.php'])
			])),
		(new CMenuItem(_('Inventory')))
			->setId('cm')
			->setIcon('icon-inventory')
			->setSubMenu(new CMenu([
				(new CMenuItem(_('Overview')))
					->setUrl(new CUrl('hostinventoriesoverview.php'), 'hostinventoriesoverview.php'),
				(new CMenuItem(_('Hosts')))
					->setUrl(new CUrl('hostinventories.php'), 'hostinventories.php')
			])),
		(new CMenuItem(_('Reports')))
			->setId('reports')
			->setIcon('icon-reports')
			->setSubMenu(new CMenu([
				(new CMenuItem(_('Availability report')))
					->setUrl(new CUrl('report2.php'), 'report2.php')
					->setAliases(['chart4.php']),
				(new CMenuItem(_('Triggers top 100')))
					->setUrl(new CUrl('toptriggers.php'), 'toptriggers.php')
			]))
	]);

	if (CWebUser::getType() >= USER_TYPE_ZABBIX_ADMIN) {
		$menu
			->find(_('Monitoring'))
			->getSubMenu()
				->insertAfter(_('Maps'),
					(new CMenuItem(_('Discovery')))
						->setAction('discovery.view')
				);

		$menu
			->find(_('Reports'))
			->getSubMenu()
				->add((new CMenuItem(_('Notifications')))
					->setUrl(new CUrl('report4.php'), 'report4.php')
				);

		$menu->add(
			(new CMenuItem(_('Configuration')))
				->setId('config')
				->setIcon('icon-configuration')
				->setSubMenu(new CMenu([
					(new CMenuItem(_('Host groups')))
						->setUrl(new CUrl('hostgroups.php'), 'hostgroups.php'),
					(new CMenuItem(_('Templates')))
						->setUrl(new CUrl('templates.php'), 'templates.php')
						->setAliases(['conf.import.php?rules_preset=template']),
					(new CMenuItem(_('Hosts')))
						->setUrl(new CUrl('hosts.php'), 'hosts.php')
						->setAliases(['items.php', 'triggers.php', 'graphs.php', 'applications.php',
							'host_discovery.php', 'disc_prototypes.php', 'trigger_prototypes.php',
							'host_prototypes.php', 'httpconf.php', 'conf.import.php?rules_preset=host'
						]),
					(new CMenuItem(_('Maintenance')))
						->setUrl(new CUrl('maintenance.php'), 'maintenance.php'),
					(new CMenuItem(_('Actions')))
						->setUrl(new CUrl('actionconf.php'), 'actionconf.php'),
					(new CMenuItem(_('Discovery')))
						->setUrl(new CUrl('discoveryconf.php'), 'discoveryconf.php'),
					(new CMenuItem(_('Services')))
						->setUrl(new CUrl('services.php'), 'services.php')
				]))
		);
	}

	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$menu
			->find(_('Reports'))
			->getSubMenu()
				->insertBefore(_('Availability report'),
					(new CMenuItem(_('System information')))
						->setAction('report.status')
				)
				->insertAfter(_('Triggers top 100'),
					(new CMenuItem(_('Audit')))
						->setAction('auditlog.list')
				)
				->insertAfter(_('Audit'),
					(new CMenuItem(_('Action log')))
						->setUrl(new CUrl('auditacts.php'), 'auditacts.php')
				);

		$menu
			->find(_('Configuration'))
			->getSubMenu()
				->insertAfter(_('Actions'),
					(new CMenuItem(_('Event correlation')))
						->setUrl(new CUrl('correlation.php'), 'correlation.php')
				);

		$menu->add(
			(new CMenuItem(_('Administration')))
				->setId('admin')
				->setIcon('icon-administration')
				->setSubMenu(new CMenu([
					(new CMenuItem(_('General')))
						->setAction('gui.edit')
						->setAliases(['autoreg.edit', 'housekeeping.edit', 'image.list', 'image.edit',
							'iconmap.list', 'iconmap.edit', 'regex.list', 'regex.edit', 'macros.edit', 'valuemap.list',
							'valuemap.edit', 'workingtime.edit', 'trigseverity.edit', 'trigdisplay.edit',
							'miscconfig.edit', 'module.list', 'module.edit', 'module.scan',
							'conf.import.php?rules_preset=valuemap'
						]),
					(new CMenuItem(_('Proxies')))
						->setAction('proxy.list')
						->setAliases(['proxy.edit']),
					(new CMenuItem(_('Authentication')))
						->setAction('authentication.edit')
						->setAliases(['authentication.update']),
					(new CMenuItem(_('User groups')))
						->setAction('usergroup.list')
						->setAliases(['usergroup.edit']),
					(new CMenuItem(_('Users')))
						->setAction('user.list')
						->setAliases(['user.edit']),
					(new CMenuItem(_('Media types')))
						->setAction('mediatype.list')
						->setAliases(['mediatype.edit', 'conf.import.php?rules_preset=mediatype']),
					(new CMenuItem(_('Scripts')))
						->setAction('script.list')
						->setAliases(['script.edit']),
					(new CMenuItem(_('Queue')))
						->setUrl(new CUrl('queue.php'), 'queue.php')
				]))
		);
	}

	return $menu;
}

function getUserMenu(): CMenu {
	$menu = new CMenu();

	if (!CBrandHelper::isRebranded()) {
		$menu
			->add(
				(new CMenuItem(_('Support')))
					->setIcon('icon-support')
					->setUrl(new CUrl(getSupportUrl(CWebUser::getLang())))
					->setTitle(_('Zabbix Technical Support'))
					->setTarget('_blank')
			)
			->add(
				(new CMenuItem(_('Share')))
					->setIcon('icon-share')
					->setUrl(new Curl('https://share.zabbix.com/'))
					->setTitle(_('Zabbix Share'))
					->setTarget('_blank')
			);
	}

	$menu->add(
		(new CMenuItem(_('Help')))
			->setIcon('icon-help')
			->setUrl(new CUrl(CBrandHelper::getHelpUrl()))
			->setTitle(_('Help'))
			->setTarget('_blank')
	);

	$user = array_intersect_key(CWebUser::$data, array_flip(['alias', 'name', 'surname'])) + [
		'name' => null,
		'surname' => null
	];

	if (CWebUser::isGuest()) {
		$menu->add(
			(new CMenuItem(_('Guest user')))
				->setIcon('icon-guest')
				->setTitle(getUserFullname($user))
		);
	}
	else {
		$menu->add(
			(new CMenuItem(_('User settings')))
				->setIcon('icon-profile')
				->setAction('userprofile.edit')
				->setTitle(getUserFullname($user))
		);
	}

	$menu->add(
		(new CMenuItem(_('Sign out')))
			->setIcon('icon-signout')
			->setUrl(new CUrl('#signout'))
			->setTitle(_('Sign out'))
			->onClick('ZABBIX.logout()')
	);

	return $menu;
}

<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CMenuHelper {

	/**
	 * Get main menu element.
	 *
	 * @return CMenu
	 */
	public static function getMainMenu(): CMenu {
		$menu = new CMenu();

		$submenu_monitoring = [
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
				? (new CMenuItem(_('Dashboard')))
					->setAction('dashboard.view')
					->setAliases(['dashboard.list'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
				? (new CMenuItem(_('Problems')))
					->setAction('problem.view')
					->setAliases(['tr_events.php'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_HOSTS)
				? (new CMenuItem(_('Hosts')))
					->setAction('host.view')
					->setAliases([
						'web.view', 'charts.view', 'chart2.php', 'chart3.php', 'chart6.php', 'chart7.php',
						'httpdetails.php', 'host.dashboard.view'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
				? (new CMenuItem(_('Latest data')))
					->setAction('latest.view')
					->setAliases(['history.php', 'chart.php'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_MAPS)
				? (new CMenuItem(_('Maps')))
					->setAction('map.view')
					->setAliases(['image.php', 'sysmaps.php', 'sysmap.php', 'map.php'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_DISCOVERY)
				? (new CMenuItem(_('Discovery')))->setAction('discovery.view')
				: null
		];
		$submenu_monitoring = array_filter($submenu_monitoring);

		if ($submenu_monitoring) {
			$menu->add(
				(new CMenuItem(_('Monitoring')))
					->setId('view')
					->setIcon('icon-monitoring')
					->setSubMenu(new CMenu($submenu_monitoring))
			);
		}

		$submenu_services = [
			CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SERVICES)
				? (new CMenuItem(_('Services')))
					->setAction('service.list')
					->setAliases(['service.list.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_SERVICES_ACTIONS)
				? (new CMenuItem(_('Service actions')))
					->setUrl(
						(new CUrl('actionconf.php'))->setArgument('eventsource', EVENT_SOURCE_SERVICE),
						'actionconf.php?eventsource='.EVENT_SOURCE_SERVICE
					)
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SLA)
				? (new CMenuItem(_('SLA')))
					->setAction('sla.list')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SLA_REPORT)
				? (new CMenuItem(_('SLA report')))
					->setAction('slareport.list')
				: null
		];

		$submenu_services = array_filter($submenu_services);

		if ($submenu_services) {
			$menu->add(
				(new CMenuItem(_('Services')))
					->setId('services')
					->setIcon('icon-services')
					->setSubMenu(new CMenu($submenu_services))
			);
		}

		$submenu_inventory = [
			CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_OVERVIEW)
				? (new CMenuItem(_('Overview')))
					->setUrl(new CUrl('hostinventoriesoverview.php'), 'hostinventoriesoverview.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_HOSTS)
				? (new CMenuItem(_('Hosts')))->setUrl(new CUrl('hostinventories.php'), 'hostinventories.php')
				: null
		];
		$submenu_inventory = array_filter($submenu_inventory);

		if ($submenu_inventory) {
			$menu->add(
				(new CMenuItem(_('Inventory')))
					->setId('cm')
					->setIcon('icon-inventory')
					->setSubMenu(new CMenu($submenu_inventory))
			);
		}

		$submenu_reports = [
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_SYSTEM_INFO)
				? (new CMenuItem(_('System information')))->setAction('report.status')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
				? (new CMenuItem(_('Scheduled reports')))
					->setAction('scheduledreport.list')
					->setAliases(['scheduledreport.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT)
				? (new CMenuItem(_('Availability report')))
					->setUrl(new CUrl('report2.php'), 'report2.php')
					->setAliases(['chart4.php'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_TOP_TRIGGERS)
				? (new CMenuItem(_('Triggers top 100')))->setUrl(new CUrl('toptriggers.php'), 'toptriggers.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_AUDIT)
				? (new CMenuItem(_('Audit')))->setAction('auditlog.list')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_ACTION_LOG)
				? (new CMenuItem(_('Action log')))->setUrl(new CUrl('auditacts.php'), 'auditacts.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_NOTIFICATIONS)
				? (new CMenuItem(_('Notifications')))->setUrl(new CUrl('report4.php'), 'report4.php')
				: null
		];
		$submenu_reports = array_filter($submenu_reports);

		if ($submenu_reports) {
			$menu->add(
				(new CMenuItem(_('Reports')))
					->setId('reports')
					->setIcon('icon-reports')
					->setSubMenu(new CMenu($submenu_reports))
			);
		}

		$submenu_configuration = [
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS)
				? (new CMenuItem(_('Host groups')))->setUrl(new CUrl('hostgroups.php'), 'hostgroups.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
				? (new CMenuItem(_('Templates')))
					->setUrl(new CUrl('templates.php'), 'templates.php')
					->setAliases([
						'template.dashboard.list', 'template.dashboard.edit', 'items.php?context=template',
						'triggers.php?context=template', 'graphs.php?context=template',
						'host_discovery.php?context=template', 'disc_prototypes.php?context=template',
						'trigger_prototypes.php?context=template', 'host_prototypes.php?context=template',
						'httpconf.php?context=template'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
				? (new CMenuItem(_('Hosts')))
					->setAction('host.list')
					->setAliases([
						'items.php?context=host', 'triggers.php?context=host', 'graphs.php?context=host',
						'host_discovery.php?context=host', 'disc_prototypes.php?context=host',
						'trigger_prototypes.php?context=host', 'host_prototypes.php?context=host',
						'httpconf.php?context=host', 'host.edit'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
				? (new CMenuItem(_('Maintenance')))->setUrl(new CUrl('maintenance.php'), 'maintenance.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_ACTIONS)
				? (new CMenuItem(_('Actions')))
					->setSubMenu(new CMenu([
						(new CMenuItem(_('Trigger actions')))
							->setUrl(
								(new CUrl('actionconf.php'))->setArgument('eventsource', EVENT_SOURCE_TRIGGERS),
								'actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS
							),
						(new CMenuItem(_('Discovery actions')))
							->setUrl(
								(new CUrl('actionconf.php'))->setArgument('eventsource', EVENT_SOURCE_DISCOVERY),
								'actionconf.php?eventsource='.EVENT_SOURCE_DISCOVERY
							),
						(new CMenuItem(_('Autoregistration actions')))
							->setUrl(
								(new CUrl('actionconf.php'))->setArgument('eventsource', EVENT_SOURCE_AUTOREGISTRATION),
								'actionconf.php?eventsource='.EVENT_SOURCE_AUTOREGISTRATION
							),
						(new CMenuItem(_('Internal actions')))
							->setUrl(
								(new CUrl('actionconf.php'))->setArgument('eventsource', EVENT_SOURCE_INTERNAL),
								'actionconf.php?eventsource='.EVENT_SOURCE_INTERNAL
							)
					]))
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION)
				? (new CMenuItem(_('Event correlation')))
					->setAction('correlation.list')
					->setAliases(['correlation.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY)
				? (new CMenuItem(_('Discovery')))
					->setAction('discovery.list')
					->setAliases(['discovery.edit'])
				: null
		];
		$submenu_configuration = array_filter($submenu_configuration);

		if ($submenu_configuration) {
			$menu->add(
				(new CMenuItem(_('Configuration')))
					->setId('config')
					->setIcon('icon-configuration')
					->setSubMenu(new CMenu($submenu_configuration))
			);
		}

		$submenu_administration = [
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
				? (new CMenuItem(_('General')))
					->setSubMenu(new CMenu(array_filter([
						(new CMenuItem(_('GUI')))
							->setAction('gui.edit'),
						(new CMenuItem(_('Autoregistration')))
							->setAction('autoreg.edit'),
						(new CMenuItem(_('Housekeeping')))
							->setAction('housekeeping.edit'),
						(new CMenuItem(_('Audit log')))
							->setAction('audit.settings.edit'),
						(new CMenuItem(_('Images')))
							->setAction('image.list')
							->setAliases(['image.edit']),
						(new CMenuItem(_('Icon mapping')))
							->setAction('iconmap.list')
							->setAliases(['iconmap.edit']),
						(new CMenuItem(_('Regular expressions')))
							->setAction('regex.list')
							->setAliases(['regex.edit']),
						(new CMenuItem(_('Macros')))
							->setAction('macros.edit'),
						(new CMenuItem(_('Trigger displaying options')))
							->setAction('trigdisplay.edit'),
						(new CMenuItem(_('Geographical maps')))
							->setAction('geomaps.edit'),
						(new CMenuItem(_('Modules')))
							->setAction('module.list')
							->setAliases(['module.edit', 'module.scan']),
						(!CWebUser::isGuest() && CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS))
							? (new CMenuItem(_('API tokens')))
								->setAction('token.list')
								->setAliases(['token.edit', 'token.view'])
							: null,
						(new CMenuItem(_('Other')))
							->setAction('miscconfig.edit')
					])))
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				? (new CMenuItem(_('Proxies')))
					->setAction('proxy.list')
					->setAliases(['proxy.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION)
				? (new CMenuItem(_('Authentication')))
					->setAction('authentication.edit')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS)
				? (new CMenuItem(_('User groups')))
					->setAction('usergroup.list')
					->setAliases(['usergroup.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_ROLES)
				? (new CMenuItem(_('User roles')))
					->setAction('userrole.list')
					->setAliases(['userrole.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)
				? (new CMenuItem(_('Users')))
					->setAction('user.list')
					->setAliases(['user.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES)
				? (new CMenuItem(_('Media types')))
					->setAction('mediatype.list')
					->setAliases(['mediatype.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS)
				? (new CMenuItem(_('Scripts')))
					->setAction('script.list')
					->setAliases(['script.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE)
				? (new CMenuItem(_('Queue')))
					->setSubMenu(new CMenu([
						(new CMenuItem(_('Queue overview')))
							->setAction('queue.overview'),
						(new CMenuItem(_('Queue overview by proxy')))
							->setAction('queue.overview.proxy'),
						(new CMenuItem(_('Queue details')))
							->setAction('queue.details')
					]))
				: null
		];
		$submenu_administration = array_filter($submenu_administration);

		if ($submenu_administration) {
			$menu->add(
				(new CMenuItem(_('Administration')))
					->setId('admin')
					->setIcon('icon-administration')
					->setSubMenu(new CMenu($submenu_administration))
			);
		}

		return $menu;
	}

	/**
	 * Get user menu element.
	 *
	 * @return CMenu
	 */
	public static function getUserMenu(): CMenu {
		$menu = new CMenu();

		if (!CBrandHelper::isRebranded()) {
			$lang = CWebUser::getLang();
			$menu
				->add(
					(new CMenuItem(_('Support')))
						->setIcon('icon-support')
						->setUrl(new CUrl(getSupportUrl($lang)))
						->setTitle(_('Zabbix Technical Support'))
						->setTarget('_blank')
				)
				->add(
					(new CMenuItem(_('Integrations')))
						->setIcon('icon-integrations')
						->setUrl(new CUrl(getIntegrationsUrl($lang)))
						->setTitle(_('Zabbix Integrations'))
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

		$user = array_intersect_key(CWebUser::$data, array_flip(['username', 'name', 'surname'])) + [
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
		elseif (CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)) {
			$menu->add(
				(new CMenuItem(_('User settings')))
					->setIcon('icon-profile')
					->setTitle(getUserFullname($user))
					->setSubMenu(new CMenu([
						(new CMenuItem(_('Profile')))
							->setAction('userprofile.edit'),
						(new CMenuItem(_('API tokens')))
							->setAction('user.token.list')
							->setAliases(['user.token.view', 'user.token.edit'])
					]))
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

	/**
	 * Get first menu item from main menu.
	 *
	 * @return CMenuItem
	 */
	private static function getFirstMenuItem(): CMenuItem {
		$menu = self::getMainMenu();

		foreach (CRoleHelper::getUiSectionsLabels(CWebUser::$data['type']) as $section_label) {
			$section_submenu = $menu->find($section_label);
			if ($section_submenu instanceof CMenuItem) {
				$menu = $section_submenu
					->getSubMenu()
					->getMenuItems();
				return $menu[0];
			}
		}

		return $menu->getMenuItems()[0];
	}

	public static function getFirstUrl(): string {
		return self::getFirstMenuItem()
			->getUrl()
			->getUrl();
	}

	public static function getFirstLabel(): string {
		return self::getFirstMenuItem()->getLabel();
	}
}

<?php declare(strict_types = 1);
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
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_OVERVIEW)
				? (new CMenuItem(_('Overview')))->setUrl(new CUrl('overview.php'), 'overview.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
				? (new CMenuItem(_('Latest data')))
					->setAction('latest.view')
					->setAliases(['history.php', 'chart.php'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_SCREENS)
				? (new CMenuItem(_('Screens')))
					->setUrl(new CUrl('screens.php'), 'screens.php')
					->setAliases([
						'screenconf.php?!templateid=*', 'screenedit.php?!templateid=*',
						'screen.import.php', 'slides.php', 'slideconf.php'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_MAPS)
				? (new CMenuItem(_('Maps')))
					->setAction('map.view')
					->setAliases(['image.php', 'sysmaps.php', 'sysmap.php', 'map.php', 'map.import.php'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_DISCOVERY)
				? (new CMenuItem(_('Discovery')))->setAction('discovery.view')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
				? (new CMenuItem(_('Services')))
					->setUrl(new CUrl('srv_status.php'), 'srv_status.php')
					->setAliases(['report.services', 'chart5.php'])
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
					->setAliases(
						['conf.import.php?rules_preset=template', 'template.dashboard.list', 'template.dashboard.edit']
					)
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
				? (new CMenuItem(_('Hosts')))
					->setUrl(new CUrl('hosts.php'), 'hosts.php')
					->setAliases([
						'items.php', 'triggers.php', 'graphs.php', 'application.list', 'application.edit',
						'host_discovery.php', 'disc_prototypes.php', 'trigger_prototypes.php',
						'host_prototypes.php', 'httpconf.php', 'conf.import.php?rules_preset=host'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
				? (new CMenuItem(_('Maintenance')))->setUrl(new CUrl('maintenance.php'), 'maintenance.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_ACTIONS)
				? (new CMenuItem(_('Actions')))->setUrl(new CUrl('actionconf.php'), 'actionconf.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION)
				? (new CMenuItem(_('Event correlation')))->setUrl(new CUrl('correlation.php'), 'correlation.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY)
				? (new CMenuItem(_('Discovery')))->setUrl(new CUrl('discoveryconf.php'), 'discoveryconf.php')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICES)
				? (new CMenuItem(_('Services')))->setUrl(new CUrl('services.php'), 'services.php')
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
					->setAction('gui.edit')
					->setAliases([
						'autoreg.edit', 'housekeeping.edit', 'image.list', 'image.edit',
						'iconmap.list', 'iconmap.edit', 'regex.list', 'regex.edit', 'macros.edit', 'valuemap.list',
						'valuemap.edit', 'workingtime.edit', 'trigseverity.edit', 'trigdisplay.edit',
						'miscconfig.edit', 'module.list', 'module.edit', 'module.scan',
						'conf.import.php?rules_preset=valuemap'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				? (new CMenuItem(_('Proxies')))
					->setAction('proxy.list')
					->setAliases(['proxy.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION)
				? (new CMenuItem(_('Authentication')))
					->setAction('authentication.edit')
					->setAliases(['authentication.update'])
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
					->setAliases(['mediatype.edit', 'conf.import.php?rules_preset=mediatype'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS)
				? (new CMenuItem(_('Scripts')))
					->setAction('script.list')
					->setAliases(['script.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE)
				? (new CMenuItem(_('Queue')))->setUrl(new CUrl('queue.php'), 'queue.php')
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

	/**
	 * Get first menu item from main menu.
	 *
	 * @return CMenuItem
	 */
	private static function getFirstMenuItem(): CMenuItem {
		// FIXME: components menu store menu for guest, not for user. Because they initialized before we login.
		// $menu = APP::Component()->get('menu.main');
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

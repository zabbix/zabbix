<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CMenuHelper {

	/**
	 * Get main menu element.
	 *
	 * @throws Exception
	 * @return CMenu
	 */
	public static function getMainMenu(): CMenu {
		$menu = new CMenu();

		if (CWebUser::checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)) {
			$menu->add(
				(new CMenuItem(_('Dashboards')))
					->setId('dashboard')
					->setIcon(ZBX_ICON_DASHBOARDS)
					->setAction('dashboard.view')
					->setAliases(['dashboard.list'])
			);
		}

		$submenu_monitoring = [
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
					->setIcon(ZBX_ICON_MONITORING)
					->setSubMenu(new CMenu($submenu_monitoring))
			);
		}

		$submenu_services = [
			CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SERVICES)
				? (new CMenuItem(_('Services')))
					->setAction('service.list')
					->setAliases(['service.list.edit'])
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
					->setIcon(ZBX_ICON_SERVICES)
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
					->setIcon(ZBX_ICON_INVENTORY)
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
					->setAction('availabilityreport.list')
					->setAliases(['availabilityreport.trigger'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_TOP_TRIGGERS)
				? (new CMenuItem(_('Top 100 triggers')))->setAction('toptriggers.list')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_AUDIT)
				? (new CMenuItem(_('Audit log')))->setAction('auditlog.list')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_REPORTS_ACTION_LOG)
				? (new CMenuItem(_('Action log')))->setAction('actionlog.list')
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
					->setIcon(ZBX_ICON_REPORTS)
					->setSubMenu(new CMenu($submenu_reports))
			);
		}

		$submenu_data_collection = [
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS)
				? (new CMenuItem(_('Template groups')))->setAction('templategroup.list')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS)
				? (new CMenuItem(_('Host groups')))->setAction('hostgroup.list')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
				? (new CMenuItem(_('Templates')))
					->setAction('template.list')
					->setAliases([
						'template.dashboard.list', 'template.dashboard.edit', 'item.list?context=template',
						'trigger.list?context=template', 'graphs.php?context=template',
						'host_discovery.php?context=template', 'item.prototype.list?context=template',
						'trigger.prototype.list?context=template', 'host_prototypes.php?context=template',
						'httpconf.php?context=template'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
				? (new CMenuItem(_('Hosts')))
					->setAction('host.list')
					->setAliases([
						'item.list?context=host', 'trigger.list?context=host', 'graphs.php?context=host',
						'host_discovery.php?context=host', 'item.prototype.list?context=host',
						'trigger.prototype.list?context=host', 'host_prototypes.php?context=host',
						'httpconf.php?context=host'
					])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
				? (new CMenuItem(_('Maintenance')))->setAction('maintenance.list')
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
		$submenu_data_collection = array_filter($submenu_data_collection);

		if ($submenu_data_collection) {
			$menu->add(
				(new CMenuItem(_('Data collection')))
					->setId('config')
					->setIcon(ZBX_ICON_DATA_COLLECTION)
					->setSubMenu(new CMenu($submenu_data_collection))
			);
		}

		$submenu_alerts = [
			(CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS) ||
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS) ||
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS) ||
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS) ||
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS))
				? (new CMenuItem(_('Actions')))
					->setSubMenu(new CMenu(array_filter([
						CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS)
							? (new CMenuItem(_('Trigger actions')))
								->setUrl(
									(new CUrl('zabbix.php'))
										->setArgument('action', 'action.list')
										->setArgument('eventsource', EVENT_SOURCE_TRIGGERS),
									'action.list?eventsource='.EVENT_SOURCE_TRIGGERS
								)
							: null,
						CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS)
							? (new CMenuItem(_('Service actions')))
								->setUrl(
									(new CUrl('zabbix.php'))
										->setArgument('action', 'action.list')
										->setArgument('eventsource', EVENT_SOURCE_SERVICE),
									'action.list?eventsource='.EVENT_SOURCE_SERVICE
								)
							: null,
						CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS)
							? (new CMenuItem(_('Discovery actions')))
								->setUrl(
									(new CUrl('zabbix.php'))
										->setArgument('action', 'action.list')
										->setArgument('eventsource', EVENT_SOURCE_DISCOVERY),
									'action.list?eventsource='.EVENT_SOURCE_DISCOVERY
								)
							: null,
						CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS)
							? (new CMenuItem(_('Autoregistration actions')))
								->setUrl(
									(new CUrl('zabbix.php'))
										->setArgument('action', 'action.list')
										->setArgument('eventsource', EVENT_SOURCE_AUTOREGISTRATION),
									'action.list?eventsource='.EVENT_SOURCE_AUTOREGISTRATION
								)
							: null,
						CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS)
							? (new CMenuItem(_('Internal actions')))
								->setUrl(
									(new CUrl('zabbix.php'))
										->setArgument('action', 'action.list')
										->setArgument('eventsource', EVENT_SOURCE_INTERNAL),
									'action.list?eventsource='.EVENT_SOURCE_INTERNAL
								)
							: null
					])))
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
				: null
		];
		$submenu_alerts = array_filter($submenu_alerts);

		if ($submenu_alerts) {
			$menu->add(
				(new CMenuItem(_('Alerts')))
					->setId('alerts')
					->setIcon(ZBX_ICON_ALERTS)
					->setSubMenu(new CMenu($submenu_alerts))
			);
		}

		$submenu_users = [
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
			(!CWebUser::isGuest() && CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_API_TOKENS) &&
				CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS))
				? (new CMenuItem(_('API tokens')))
					->setAction('token.list')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION)
				? (new CMenuItem(_('Authentication')))
					->setAction('authentication.edit')
				: null
		];
		$submenu_users = array_filter($submenu_users);

		if ($submenu_users) {
			$menu->add(
				(new CMenuItem(_('Users')))
					->setId('users-menu')
					->setIcon(ZBX_ICON_USERS)
					->setSubMenu(new CMenu($submenu_users))
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
						(new CMenuItem(_('Timeouts')))
							->setAction('timeouts.edit'),
						(new CMenuItem(_('Images')))
							->setAction('image.list')
							->setAliases(['image.edit']),
						(new CMenuItem(_('Icon mapping')))
							->setAction('iconmap.list')
							->setAliases(['iconmap.edit']),
						(new CMenuItem(_('Regular expressions')))
							->setAction('regex.list')
							->setAliases(['regex.edit']),
						(new CMenuItem(_('Trigger displaying options')))
							->setAction('trigdisplay.edit'),
						(new CMenuItem(_('Geographical maps')))
							->setAction('geomaps.edit'),
						(new CMenuItem(_('Modules')))
							->setAction('module.list')
							->setAliases(['module.edit', 'module.scan']),
						(new CMenuItem(_('Connectors')))
							->setAction('connector.list')
							->setAliases(['connector.edit']),
						(new CMenuItem(_('Other')))
							->setAction('miscconfig.edit')
					])))
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_AUDIT_LOG)
				? (new CMenuItem(_('Audit log')))
					->setAction('audit.settings.edit')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_HOUSEKEEPING)
				? (new CMenuItem(_('Housekeeping')))
					->setAction('housekeeping.edit')
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS)
				? (new CMenuItem(_('Proxy groups')))
					->setAction('proxygroup.list')
					->setAliases(['proxygroup.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				? (new CMenuItem(_('Proxies')))
					->setAction('proxy.list')
					->setAliases(['proxy.edit'])
				: null,
			CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_MACROS)
				? (new CMenuItem(_('Macros')))
					->setAction('macros.edit')
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
					->setIcon(ZBX_ICON_ADMINISTRATION)
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
						->setIcon(ZBX_ICON_SUPPORT)
						->setUrl(new CUrl(getSupportUrl($lang)))
						->setTitle(_('Zabbix Technical Support'))
						->setTarget('_blank')
				)
				->add(
					(new CMenuItem(_('Integrations')))
						->setIcon(ZBX_ICON_INTEGRATIONS)
						->setUrl(new CUrl(getIntegrationsUrl($lang)))
						->setTitle(_('Zabbix Integrations'))
						->setTarget('_blank')
				);
		}

		$menu->add(
			(new CMenuItem(_('Help')))
				->setIcon(ZBX_ICON_HELP_CIRCLED)
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
					->setIcon(ZBX_ICON_USER)
					->setTitle(getUserFullname($user))
			);
		}
		elseif (CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)) {
			$menu->add(
				(new CMenuItem(_('User settings')))
					->setIcon(ZBX_ICON_USER_SETTINGS)
					->setTitle(getUserFullname($user))
					->setSubMenu(new CMenu([
						(new CMenuItem(_('Profile')))
							->setAction('userprofile.edit'),
						(new CMenuItem(_('API tokens')))
							->setAction('user.token.list')
					]))
			);
		}
		else {
			$menu->add(
				(new CMenuItem(_('User settings')))
					->setIcon(ZBX_ICON_USER_SETTINGS)
					->setAction('userprofile.edit')
					->setTitle(getUserFullname($user))
			);
		}

		$menu->add(
			(new CMenuItem(_('Sign out')))
				->setIcon(ZBX_ICON_SIGN_OUT)
				->setUrl(new CUrl('#signout'))
				->setTitle(_('Sign out'))
				->onClick('event.preventDefault(); ZABBIX.logout(this.dataset.csrf_token)')
				->setAttribute('data-csrf_token', CCsrfTokenHelper::get('index.php'))
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

			if ($section_submenu instanceof CMenuItem && !$section_submenu->hasSubMenu()) {
				return $menu->getMenuItems()[0];
			}
			elseif ($section_submenu instanceof CMenuItem) {
				$menu = $section_submenu
					->getSubMenu()
					->getMenuItems();

				if ($menu[0]->hasSubMenu()) {
					$menu = $menu[0]
						->getSubMenu()
						->getMenuItems();
				}

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

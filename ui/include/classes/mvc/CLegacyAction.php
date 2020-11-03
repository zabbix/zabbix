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


use CController as CAction;

class CLegacyAction extends CAction {

	/**
	 * Disable SID validation for legacy actions.
	 */
	protected function init(): void {
		$this->disableSIDvalidation();
	}

	public function doAction(): void {
	}

	/**
	 * Check user input.
	 *
	 * @return bool
	 */
	public function checkInput(): bool {
		return true;
	}

	/**
	 * Check permission.
	 *
	 * @return bool
	 */
	public function checkPermissions(): bool {
		$user_type = $this->getUserType();
		$denied = [];
		$action = $this->getAction();

		if ($user_type < USER_TYPE_ZABBIX_USER) {
			$denied = ['chart.php', 'chart2.php', 'chart3.php', 'chart4.php', 'chart5.php', 'chart6.php', 'chart7.php',
				'history.php', 'hostinventories.php', 'hostinventoriesoverview.php', 'httpdetails.php', 'image.php',
				'imgstore.php', 'jsrpc.php', 'map.import.php', 'map.php', 'overview.php', 'toptriggers.php',
				'tr_events.php', 'screenconf.php', 'screenedit.php', 'screen.import.php', 'screens.php',
				'slideconf.php', 'slides.php', 'srv_status.php', 'sysmap.php', 'sysmaps.php', 'report2.php'
			];
		}

		if ($user_type < USER_TYPE_ZABBIX_ADMIN) {
			$denied = array_merge($denied, ['actionconf.php', 'conf.import.php',
				'disc_prototypes.php', 'discoveryconf.php', 'graphs.php', 'host_discovery.php', 'host_prototypes.php',
				'hostgroups.php', 'hosts.php', 'httpconf.php', 'items.php', 'maintenance.php', 'report4.php',
				'services.php', 'templates.php', 'trigger_prototypes.php', 'triggers.php'
			]);
		}

		if ($user_type != USER_TYPE_SUPER_ADMIN) {
			$denied = array_merge($denied, ['auditacts.php', 'correlation.php', 'queue.php']);
		}

		if (in_array($action, $denied)) {
			return false;
		}

		$rule_actions = [];

		if (in_array($user_type, [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])) {
			if ($action === 'screenconf.php' || $action === 'screenedit.php') {
				return getRequest('templateid', false)
					? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
					: $this->checkAccess(CRoleHelper::UI_MONITORING_SCREENS);
			}

			$rule_actions = [
				CRoleHelper::UI_MONITORING_PROBLEMS => ['tr_events.php'],
				CRoleHelper::UI_MONITORING_HOSTS => ['host_screen.php', 'httpdetails.php'],
				CRoleHelper::UI_MONITORING_OVERVIEW => ['overview.php'],
				CRoleHelper::UI_MONITORING_LATEST_DATA => ['history.php'],
				CRoleHelper::UI_MONITORING_SCREENS => ['screen.import.php', 'screens.php', 'slideconf.php',
					'slides.php'
				],
				CRoleHelper::UI_MONITORING_MAPS => ['image.php', 'map.import.php', 'map.php', 'sysmap.php',
					'sysmaps.php'
				],
				CRoleHelper::UI_MONITORING_SERVICES => ['chart5.php', 'srv_status.php'],
				CRoleHelper::UI_INVENTORY_OVERVIEW => ['hostinventoriesoverview.php'],
				CRoleHelper::UI_INVENTORY_HOSTS => ['hostinventories.php'],
				CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT => ['report2.php'],
				CRoleHelper::UI_REPORTS_TOP_TRIGGERS => ['toptriggers.php']
			];
		}

		if ($user_type == USER_TYPE_ZABBIX_ADMIN || $user_type == USER_TYPE_SUPER_ADMIN) {
			$rule_actions += [
				CRoleHelper::UI_REPORTS_NOTIFICATIONS => ['report4.php'],
				CRoleHelper::UI_CONFIGURATION_HOST_GROUPS => ['hostgroups.php'],
				CRoleHelper::UI_CONFIGURATION_TEMPLATES => ['templates.php'],
				CRoleHelper::UI_CONFIGURATION_HOSTS => ['hosts.php'],
				CRoleHelper::UI_CONFIGURATION_MAINTENANCE => ['maintenance.php'],
				CRoleHelper::UI_CONFIGURATION_ACTIONS => ['actionconf.php'],
				CRoleHelper::UI_CONFIGURATION_DISCOVERY => ['discoveryconf.php'],
				CRoleHelper::UI_CONFIGURATION_SERVICES => ['services.php']
			];
		}

		if ($user_type == USER_TYPE_SUPER_ADMIN) {
			$rule_actions += [
				CRoleHelper::UI_REPORTS_ACTION_LOG => ['auditacts.php'],
				CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION => ['correlation.php'],
				CRoleHelper::UI_ADMINISTRATION_QUEUE => ['queue.php']
			];
		}

		foreach ($rule_actions as $rule_name => $actions) {
			if (in_array($action, $actions)) {
				return $this->checkAccess($rule_name);
			}
		}

		return true;
	}
}

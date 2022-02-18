<?php declare(strict_types = 1);
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
		$json_actions = ['templates.php', 'host_prototypes.php'];

		if (in_array($this->getAction(), $json_actions) && array_key_exists('formdata_json', $_REQUEST)) {
			$_REQUEST = json_decode($_REQUEST['formdata_json'], true);
		}

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

		/*
		 * Overwrite legacy action in case user is located in sub-section like items, triggers etc. That will make
		 * sure to hide left menu and display error in case user has no access to templates or hosts.
		 */
		if (in_array(getRequest('context', ''), ['host', 'template']) && in_array($action, ['items.php', 'triggers.php',
				'graphs.php', 'host_discovery.php', 'httpconf.php', 'disc_prototypes.php', 'trigger_prototypes.php',
				'host_prototypes.php'])) {
			$action = (getRequest('context') === 'host') ? 'host.list' : 'templates.php';
		}

		if ($user_type < USER_TYPE_ZABBIX_USER) {
			$denied = ['chart.php', 'chart2.php', 'chart3.php', 'chart4.php', 'chart6.php', 'chart7.php', 'history.php',
				'hostinventories.php', 'hostinventoriesoverview.php', 'httpdetails.php', 'image.php', 'imgstore.php',
				'jsrpc.php', 'map.php', 'toptriggers.php', 'tr_events.php', 'sysmap.php', 'sysmaps.php', 'report2.php'
			];
		}

		if ($user_type < USER_TYPE_ZABBIX_ADMIN) {
			$denied = array_merge($denied, ['actionconf.php', 'disc_prototypes.php', 'graphs.php', 'host_discovery.php',
				'host_prototypes.php', 'hostgroups.php', 'host.list',  'httpconf.php', 'items.php', 'maintenance.php',
				'report4.php', 'templates.php', 'trigger_prototypes.php', 'triggers.php'
			]);
		}

		if ($user_type != USER_TYPE_SUPER_ADMIN) {
			$denied = array_merge($denied, ['auditacts.php']);
		}

		if (in_array($action, $denied)) {
			return false;
		}

		$rule_actions = [];

		if (in_array($user_type, [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])) {
			$rule_actions = [
				CRoleHelper::UI_MONITORING_HOSTS => ['httpdetails.php'],
				CRoleHelper::UI_MONITORING_LATEST_DATA => ['history.php'],
				CRoleHelper::UI_MONITORING_MAPS => ['image.php', 'map.php', 'sysmap.php', 'sysmaps.php'],
				CRoleHelper::UI_MONITORING_PROBLEMS => ['tr_events.php'],
				CRoleHelper::UI_INVENTORY_HOSTS => ['hostinventories.php'],
				CRoleHelper::UI_INVENTORY_OVERVIEW => ['hostinventoriesoverview.php'],
				CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT => ['report2.php'],
				CRoleHelper::UI_REPORTS_TOP_TRIGGERS => ['toptriggers.php']
			];
		}

		if ($user_type == USER_TYPE_ZABBIX_ADMIN || $user_type == USER_TYPE_SUPER_ADMIN) {
			$rule_actions += [
				CRoleHelper::UI_CONFIGURATION_HOST_GROUPS => ['hostgroups.php'],
				CRoleHelper::UI_CONFIGURATION_HOSTS => ['host.list'],
				CRoleHelper::UI_CONFIGURATION_MAINTENANCE => ['maintenance.php'],
				CRoleHelper::UI_CONFIGURATION_TEMPLATES => ['templates.php'],
				CRoleHelper::UI_REPORTS_NOTIFICATIONS => ['report4.php']
			];

			if ($action === 'actionconf.php' && getRequest('eventsource') === (string) EVENT_SOURCE_SERVICE) {
				$rule_actions += [
					CRoleHelper::UI_SERVICES_ACTIONS => ['actionconf.php']
				];
			}
			else {
				$rule_actions += [
					CRoleHelper::UI_CONFIGURATION_ACTIONS => ['actionconf.php']
				];
			}
		}

		if ($user_type == USER_TYPE_SUPER_ADMIN) {
			$rule_actions += [
				CRoleHelper::UI_REPORTS_ACTION_LOG => ['auditacts.php']
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

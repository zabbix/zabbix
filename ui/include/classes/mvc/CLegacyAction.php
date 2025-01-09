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


use CController as CAction;

class CLegacyAction extends CAction {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	public function doAction(): void {
	}

	/**
	 * Check user input.
	 *
	 * @return bool
	 */
	public function checkInput(): bool {
		if ($this->getAction() === 'host_prototypes.php' && array_key_exists('formdata_json', $_REQUEST)) {
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
		if (in_array(getRequest('context', ''), ['host', 'template'])
				&& in_array($action, ['graphs.php', 'host_discovery.php', 'httpconf.php', 'host_prototypes.php'])) {
			$action = (getRequest('context') === 'host') ? 'host.list' : 'template.list';
		}

		if ($user_type < USER_TYPE_ZABBIX_USER) {
			$denied = ['chart.php', 'chart2.php', 'chart3.php', 'chart4.php', 'chart6.php', 'chart7.php', 'history.php',
				'hostinventories.php', 'hostinventoriesoverview.php', 'httpdetails.php', 'image.php', 'imgstore.php',
				'jsrpc.php', 'map.php', 'tr_events.php', 'sysmap.php', 'sysmaps.php'
			];
		}

		if ($user_type < USER_TYPE_ZABBIX_ADMIN) {
			$denied = array_merge($denied, ['graphs.php', 'host_discovery.php', 'host_prototypes.php', 'host.list',
				'httpconf.php', 'report4.php', 'template.list'
			]);
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
				CRoleHelper::UI_INVENTORY_OVERVIEW => ['hostinventoriesoverview.php']
			];
		}

		if ($user_type == USER_TYPE_ZABBIX_ADMIN || $user_type == USER_TYPE_SUPER_ADMIN) {
			$rule_actions += [
				CRoleHelper::UI_CONFIGURATION_HOSTS => ['host.list'],
				CRoleHelper::UI_CONFIGURATION_TEMPLATES => ['template.list'],
				CRoleHelper::UI_REPORTS_NOTIFICATIONS => ['report4.php']
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

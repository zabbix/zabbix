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


/**
 * A class designed to perform actions related to check access of roles.
 */
class CRoleHelper {

	public const UI_MONITORING_DASHBOARD = 'ui.monitoring.dashboard';
	public const UI_MONITORING_PROBLEMS = 'ui.monitoring.problems';
	public const UI_MONITORING_HOSTS = 'ui.monitoring.hosts';
	public const UI_MONITORING_OVERVIEW = 'ui.monitoring.overview';
	public const UI_MONITORING_LATEST_DATA = 'ui.monitoring.latest_data';
	public const UI_MONITORING_SCREENS = 'ui.monitoring.screens';
	public const UI_MONITORING_MAPS = 'ui.monitoring.maps';
	public const UI_MONITORING_DISCOVERY = 'ui.monitoring.discovery';
	public const UI_MONITORING_SERVICES = 'ui.monitoring.services';
	public const UI_INVENTORY_OVERVIEW = 'ui.inventory.overview';
	public const UI_INVENTORY_HOSTS = 'ui.inventory.hosts';
	public const UI_REPORTS_SYSTEM_INFO = 'ui.reports.system_info';
	public const UI_REPORTS_AVAILABILITY_REPORT = 'ui.reports.availability_report';
	public const UI_REPORTS_TOP_TRIGGERS = 'ui.reports.top_triggers';
	public const UI_REPORTS_AUDIT = 'ui.reports.audit';
	public const UI_REPORTS_ACTION_LOG = 'ui.reports.action_log';
	public const UI_REPORTS_NOTIFICATIONS = 'ui.reports.notifications';
	public const UI_CONFIGURATION_HOST_GROUPS = 'ui.configuration.host_groups';
	public const UI_CONFIGURATION_TEMPLATES = 'ui.configuration.templates';
	public const UI_CONFIGURATION_HOSTS = 'ui.configuration.hosts';
	public const UI_CONFIGURATION_MAINTENANCE = 'ui.configuration.maintenance';
	public const UI_CONFIGURATION_ACTIONS = 'ui.configuration.actions';
	public const UI_CONFIGURATION_EVENT_CORRELATION = 'ui.configuration.event_correlation';
	public const UI_CONFIGURATION_DISCOVERY = 'ui.configuration.discovery';
	public const UI_CONFIGURATION_SERVICES = 'ui.configuration.services';
	public const UI_ADMINISTRATION_GENERAL = 'ui.administration.general';
	public const UI_ADMINISTRATION_PROXIES = 'ui.administration.proxies';
	public const UI_ADMINISTRATION_AUTHENTICATION = 'ui.administration.authentication';
	public const UI_ADMINISTRATION_USER_GROUPS = 'ui.administration.user_groups';
	public const UI_ADMINISTRATION_USER_ROLES = 'ui.administration.user_roles';
	public const UI_ADMINISTRATION_USERS = 'ui.administration.users';
	public const UI_ADMINISTRATION_MEDIA_TYPES = 'ui.administration.media_types';
	public const UI_ADMINISTRATION_SCRIPTS = 'ui.administration.scripts';
	public const UI_ADMINISTRATION_QUEUE = 'ui.administration.queue';
	public const UI_DEFAULT_ACCESS = 'ui.default_access';
	public const MODULES_MODULE = 'modules.module.';
	public const MODULES_MODULE_STATUS = 'modules.module.status.';
	public const MODULES_DEFAULT_ACCESS = 'modules.default_access';
	public const API = 'api';
	public const API_ACCESS_MODE = 'api.access.mode';
	public const API_METHOD = 'api.method.';
	public const ACTIONS_EDIT_DASHBOARDS = 'actions.edit.dashboards';
	public const ACTIONS_EDIT_MAPS = 'actions.edit.maps';
	public const ACTIONS_EDIT_MAINTENANCE = 'actions.edit.maintenance';
	public const ACTIONS_UPDATE_PROBLEMS = 'actions.update.problems';
	public const ACTIONS_EXECUTE_SCRIPTS = 'actions.execute.scripts';
	public const ACTIONS_DEFAULT_ACCESS = 'actions.default_access';

	public const API_WILDCARD = '*';
	public const API_WILDCARD_ALIAS = '*.*';
	public const API_ANY_SERVICE = '*.';
	public const API_ANY_METHOD = '.*';

	/**
	 * Array for storing the list of all available role rules for each user type.
	 *
	 * @static
	 *
	 * @var array
	 */
	private static $all_rules = [];

	/**
	 * Array for storing roles data (including rules) loaded from Role API object and converted to one format.
	 *
	 * @static
	 *
	 * @var array
	 */
	private static $roles = [];

	/**
	 * Checks the access of specific role to specific rule.
	 *
	 * @static
	 *
	 * @param string $rule_name  Name of the rule to check access for.
	 * @param string $roleid     ID of the role where check of access is necessary to perform.
	 *
	 * @return bool  Returns true if role have access to specified rule, false - otherwise.
	 */
	public static function checkAccess(string $rule_name, int $roleid): bool {
		self::loadRoleRules($roleid);

		$role_rules = self::$roles[$roleid]['rules'];
		$default_access_name = explode('.', $rule_name, 2)[0].'.default_access';
		$default_access_is_enabled = in_array($default_access_name, [self::UI_DEFAULT_ACCESS,
			self::MODULES_DEFAULT_ACCESS, self::ACTIONS_DEFAULT_ACCESS
		]) && (!array_key_exists($default_access_name, $role_rules) || $role_rules[$default_access_name]);

		$rule_is_in_scope_of_role_type = in_array($rule_name, self::getAllRules(self::$roles[$roleid]['type']));
		$role_rule_exists = array_key_exists($rule_name, $role_rules);
		$role_rule_is_enabled = $role_rule_exists ? $role_rules[$rule_name] : 1;

		if ($rule_is_in_scope_of_role_type) {
			if ($role_rule_exists && $role_rule_is_enabled) {
				return true;
			}
			elseif (!$role_rule_exists && $default_access_is_enabled) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Loads once all rules of specified Role API object by ID and converts rule data to one format.
	 *
	 * @static
	 *
	 * @throws Exception
	 *
	 * @param string $roleid  Role ID.
	 */
	public static function loadRoleRules(int $roleid): void {
		if (array_key_exists($roleid, self::$roles)) {
			return;
		}

		$roles = API::Role()->get([
			'output' => ['roleid', 'name', 'type'],
			'selectRules' => ['name', 'value'],
			'roleids' => $roleid
		]);

		$role = reset($roles);

		if (!$role) {
			throw new Exception(_('Specified role was not found.'));
		}

		$rules = [];
		$modules = [];

		foreach ($role['rules'] as $rule) {
			if (strncmp($rule['name'], self::MODULES_MODULE_STATUS, strlen(self::MODULES_MODULE_STATUS)) === 0) {
				$modules[substr($rule['name'], strrpos($rule['name'], '.') + 1)]['status'] = $rule['value'];
			}
			elseif (strncmp($rule['name'], self::MODULES_MODULE, strlen(self::MODULES_MODULE)) === 0) {
				$modules[substr($rule['name'], strrpos($rule['name'], '.') + 1)]['id'] = $rule['value'];
			}
			else {
				$rules[$rule['name']] = $rule['value'];
			}
		}

		foreach ($modules as $module) {
			$rules[self::MODULES_MODULE.$module['id']] = $module['status'];
		}

		$role['type'] = (int) $role['type'];
		$role['rules'] = $rules;

		self::$roles[$roleid] = $role;
	}

	/**
	 * Gets once loaded all available rules for all user types or for specific user type.
	 *
	 * @static
	 *
	 * @param integer|null $user_type  User type value.
	 *
	 * @return array       Returns the array of rule names for specified user type. If user type was not specified,
	 *                     returns array of rules for all user types where key is user type value and values is rule
	 *                     names.
	 */
	public static function getAllRules(?int $user_type = null): array {
		self::loadAllRules();

		return array_key_exists($user_type, self::$all_rules) ? self::$all_rules[$user_type] : self::$all_rules;
	}

	/**
	 * Collects once all available rules for project into $all_rules property.
	 *
	 * @static
	 */
	public static function loadAllRules(): void {
		if (self::$all_rules) {
			return;
		}

		$all_rules = [
			USER_TYPE_ZABBIX_USER => [
				self::UI_MONITORING_DASHBOARD, self::UI_MONITORING_PROBLEMS, self::UI_MONITORING_HOSTS,
				self::UI_MONITORING_OVERVIEW, self::UI_MONITORING_LATEST_DATA, self::UI_MONITORING_SCREENS,
				self::UI_MONITORING_MAPS, self::UI_MONITORING_SERVICES, self::UI_INVENTORY_OVERVIEW,
				self::UI_INVENTORY_HOSTS, self::UI_REPORTS_AVAILABILITY_REPORT, self::UI_REPORTS_TOP_TRIGGERS,
				self::API, self::API_ACCESS_MODE, self::ACTIONS_EDIT_DASHBOARDS, self::ACTIONS_EDIT_MAPS,
				self::ACTIONS_UPDATE_PROBLEMS, self::ACTIONS_EXECUTE_SCRIPTS
			]
		];

		$modules = API::Module()->get([
			'output' => ['moduleid'],
			'filter' => ['status' => MODULE_STATUS_ENABLED]
		]);

		foreach ($modules as $module) {
			$all_rules[USER_TYPE_ZABBIX_USER][] = self::MODULES_MODULE.$module['moduleid'];
		}

		$all_rules[USER_TYPE_ZABBIX_ADMIN] = array_merge($all_rules[USER_TYPE_ZABBIX_USER], [
			self::UI_MONITORING_DISCOVERY, self::UI_REPORTS_NOTIFICATIONS, self::UI_CONFIGURATION_HOST_GROUPS,
			self::UI_CONFIGURATION_TEMPLATES, self::UI_CONFIGURATION_HOSTS, self::UI_CONFIGURATION_MAINTENANCE,
			self::UI_CONFIGURATION_ACTIONS, self::UI_CONFIGURATION_DISCOVERY, self::UI_CONFIGURATION_SERVICES,
			self::ACTIONS_EDIT_MAINTENANCE
		]);

		$all_rules[USER_TYPE_SUPER_ADMIN] = array_merge($all_rules[USER_TYPE_ZABBIX_ADMIN], [
			self::UI_REPORTS_SYSTEM_INFO, self::UI_REPORTS_AUDIT, self::UI_REPORTS_ACTION_LOG,
			self::UI_CONFIGURATION_EVENT_CORRELATION, self::UI_ADMINISTRATION_GENERAL, self::UI_ADMINISTRATION_PROXIES,
			self::UI_ADMINISTRATION_AUTHENTICATION, self::UI_ADMINISTRATION_USER_GROUPS,
			self::UI_ADMINISTRATION_USER_ROLES, self::UI_ADMINISTRATION_USERS, self::UI_ADMINISTRATION_MEDIA_TYPES,
			self::UI_ADMINISTRATION_SCRIPTS, self::UI_ADMINISTRATION_QUEUE
		]);

		self::$all_rules = $all_rules;
	}
}

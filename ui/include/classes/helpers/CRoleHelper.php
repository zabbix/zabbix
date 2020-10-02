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

	public const SECTION_UI = 'ui';
	public const SECTION_MODULES = 'modules';
	public const SECTION_API = 'api';
	public const SECTION_ACTIONS = 'actions';

	public const UI_SECTION_MONITORING = 'ui.monitoring';
	public const UI_SECTION_INVENTORY = 'ui.inventory';
	public const UI_SECTION_REPORTS = 'ui.reports';
	public const UI_SECTION_CONFIGURATION = 'ui.configuration';
	public const UI_SECTION_ADMINISTRATION = 'ui.administration';

	public const API_WILDCARD = '*';
	public const API_WILDCARD_ALIAS = '*.*';
	public const API_ANY_METHOD = '.*';
	public const API_ANY_SERVICE = '*.';

	/**
	 * Array for storing roles data (including rules) loaded from Role API object and converted to one format. The data
	 * of specific role can be accessed in following way: self::roles[{role ID}].
	 *
	 * @static
	 *
	 * @var array
	 */
	private static $roles = [];

	/**
	 * Array for storing all loaded role rules for specific section name and user type. The rules of specific section
	 * name and user type can be accessed in following way: self::$section_rules[{section name}][{user type}].
	 *
	 * @static
	 *
	 * @var array
	 */
	private static $section_rules = [];

	/**
	 * Array for storing all API methods by user type.
	 *
	 * @var array
	 */
	private static $api_methods = [];

	/**
	 * Checks the access of specific role to specific rule.
	 *
	 * @static
	 *
	 * @param string  $rule_name  Name of the rule to check access for.
	 * @param integer $roleid     ID of the role where check of access is necessary to perform.
	 *
	 * @return bool  Returns true if role have access to specified rule, false - otherwise.
	 */
	public static function checkAccess(string $rule_name, int $roleid): bool {
		self::loadRoleRules($roleid);

		$section_name = self::getRuleSection($rule_name);
		$user_type = self::$roles[$roleid]['type'];
		self::loadSectionRules($section_name, $user_type);

		$rule_exists_in_section_rules = in_array($rule_name, self::$section_rules[$section_name][$user_type]);

		$role_rules = self::$roles[$roleid]['rules'];
		$role_rule_exists = array_key_exists($rule_name, $role_rules);
		$role_rule_is_enabled = false;

		if ($section_name === self::SECTION_API || $rule_name === $section_name.'.default_access') {
			if ($rule_name === self::API_ACCESS_MODE) {
				$role_rule_is_enabled = $role_rule_exists ? (bool) $role_rules[$rule_name] : false;
			}
			else {
				$role_rule_is_enabled = (bool) $role_rules[$rule_name];
			}
		}
		else {
			$default_access_is_enabled = $section_name !== self::SECTION_API
					&& $role_rules[$section_name.'.default_access'];
			$role_rule_is_enabled = ($default_access_is_enabled && !$role_rule_exists)
					|| (!$default_access_is_enabled && $role_rule_exists);
		}

		if ($rule_exists_in_section_rules && $role_rule_is_enabled) {
			return true;
		}

		return false;
	}

	/**
	 * Gets list of API methods (with wildcards if that exists) that are considered allowed or denied (depending from
	 * API access mode) for specific role.
	 *
	 * @static
	 *
	 * @param integer $roleid  Role ID.
	 *
	 * @return array  Returns the array of API methods.
	 */
	public static function getRoleApiMethods(int $roleid): array {
		self::loadRoleRules($roleid);

		return self::$roles[$roleid]['rules']['api_methods'];
	}

	/**
	 * Loads once all rules of specified Role API object by ID and converts rule data to one format.
	 *
	 * @static
	 *
	 * @throws Exception
	 *
	 * @param integer $roleid  Role ID.
	 */
	private static function loadRoleRules(int $roleid): void {
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

		$rules = ['api_methods' => []];
		$modules = [];

		foreach ($role['rules'] as $rule) {
			if (strncmp($rule['name'], self::MODULES_MODULE_STATUS, strlen(self::MODULES_MODULE_STATUS)) === 0) {
				$modules[substr($rule['name'], strrpos($rule['name'], '.') + 1)]['status'] = $rule['value'];
			}
			elseif (strncmp($rule['name'], self::MODULES_MODULE, strlen(self::MODULES_MODULE)) === 0) {
				$modules[substr($rule['name'], strrpos($rule['name'], '.') + 1)]['id'] = $rule['value'];
			}
			elseif (strncmp($rule['name'], self::API_METHOD, strlen(self::API_METHOD)) === 0) {
				$rules['api_methods'][] = $rule['value'];
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
	 * Collects once all available rules for specific rule section and user type into $section_rules property.
	 *
	 * @static
	 *
	 * @throws Exception
	 *
	 * @param string  $section_name  Section name.
	 * @param integer $user_type     User type.
	 */
	private static function loadSectionRules(string $section_name, int $user_type): void {
		if (array_key_exists($section_name, self::$section_rules)
				&& array_key_exists($user_type, self::$section_rules[$section_name])) {
			return;
		}

		switch ($section_name) {
			case self::SECTION_UI:
				self::$section_rules[$section_name][$user_type] = self::getAllUiElements($user_type);
				break;
			case self::SECTION_MODULES:
				self::$section_rules[$section_name][$user_type] = self::getAllModules($user_type);
				break;
			case self::SECTION_API:
				$rules = [self::API, self::API_ACCESS_MODE];
				self::$section_rules[$section_name][$user_type] = $rules;
				break;
			case self::SECTION_ACTIONS:
				self::$section_rules[$section_name][$user_type] = self::getAllActions($user_type);
				break;
			default:
				throw new Exception(_('Rule section was not found.'));
		}
	}

	/**
	 * Gets the section name of specific rule name.
	 *
	 * @static
	 *
	 * @throws Exception
	 *
	 * @param string $rule_name  Rule name.
	 *
	 * @return array Returns name of rules section.
	 */
	public static function getRuleSection(string $rule_name): string {
		$section = explode('.', $rule_name, 2)[0];
		if (in_array($section, [self::SECTION_UI, self::SECTION_MODULES, self::SECTION_API, self::SECTION_ACTIONS])) {
			return $section;
		}

		throw new Exception(_('Rule section was not found.'));
	}

	/**
	 * Gets all available UI elements rules for specific user type.
	 *
	 * @static
	 *
	 * @param integer $user_type  User type.
	 *
	 * @return array  Returns the array of rule names for specified user type.
	 */
	public static function getAllUiElements(int $user_type): array {
		$rules = [
			self::UI_MONITORING_DASHBOARD, self::UI_MONITORING_PROBLEMS, self::UI_MONITORING_HOSTS,
			self::UI_MONITORING_OVERVIEW, self::UI_MONITORING_LATEST_DATA, self::UI_MONITORING_SCREENS,
			self::UI_MONITORING_MAPS, self::UI_MONITORING_SERVICES, self::UI_INVENTORY_OVERVIEW,
			self::UI_INVENTORY_HOSTS, self::UI_REPORTS_AVAILABILITY_REPORT, self::UI_REPORTS_TOP_TRIGGERS
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$rules = array_merge($rules, [
				self::UI_MONITORING_DISCOVERY, self::UI_REPORTS_NOTIFICATIONS, self::UI_CONFIGURATION_HOST_GROUPS,
				self::UI_CONFIGURATION_TEMPLATES, self::UI_CONFIGURATION_HOSTS, self::UI_CONFIGURATION_MAINTENANCE,
				self::UI_CONFIGURATION_ACTIONS, self::UI_CONFIGURATION_DISCOVERY, self::UI_CONFIGURATION_SERVICES
			]);
		}

		if ($user_type === USER_TYPE_SUPER_ADMIN) {
			$rules = array_merge($rules, [
				self::UI_REPORTS_SYSTEM_INFO, self::UI_REPORTS_AUDIT, self::UI_REPORTS_ACTION_LOG,
				self::UI_CONFIGURATION_EVENT_CORRELATION, self::UI_ADMINISTRATION_GENERAL, self::UI_ADMINISTRATION_PROXIES,
				self::UI_ADMINISTRATION_AUTHENTICATION, self::UI_ADMINISTRATION_USER_GROUPS,
				self::UI_ADMINISTRATION_USER_ROLES, self::UI_ADMINISTRATION_USERS, self::UI_ADMINISTRATION_MEDIA_TYPES,
				self::UI_ADMINISTRATION_SCRIPTS, self::UI_ADMINISTRATION_QUEUE
			]);
		}

		return $rules;
	}

	/**
	 * Gets all available modules rules.
	 *
	 * @static
	 *
	 * @return array  Returns the array of rule names.
	 */
	public static function getAllModules(): array {
		$rules = [];

		$modules = API::Module()->get([
			'output' => ['moduleid'],
			'filter' => ['status' => MODULE_STATUS_ENABLED]
		]);

		foreach ($modules as $module) {
			$rules[] = self::MODULES_MODULE.$module['moduleid'];
		}

		return $rules;
	}

	/**
	 * Gets all available actions rules for specific user type.
	 *
	 * @static
	 *
	 * @param integer $user_type  User type.
	 *
	 * @return array  Returns the array of rule names for specified user type.
	 */
	public static function getAllActions(int $user_type): array {
		$rules = [
			self::ACTIONS_EDIT_DASHBOARDS, self::ACTIONS_EDIT_MAPS, self::ACTIONS_UPDATE_PROBLEMS,
			self::ACTIONS_EXECUTE_SCRIPTS
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$rules[] = self::ACTIONS_EDIT_MAINTENANCE;
		}

		return $rules;
	}

	/**
	 * Gets labels of all available UI sections for specific user type in order as it need to display in UI.
	 *
	 * @static
	 *
	 * @param integer $user_type  User type.
	 *
	 * @return array  Returns the array where key of each element is UI section name and value is UI section label.
	 */
	public static function getUiSectionsLabels(int $user_type): array {
		$sections = [
			self::UI_SECTION_MONITORING => _('Monitoring'),
			self::UI_SECTION_INVENTORY => _('Inventory'),
			self::UI_SECTION_REPORTS => _('Reports')
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$sections += [self::UI_SECTION_CONFIGURATION => _('Configuration')];
		}

		if ($user_type === USER_TYPE_SUPER_ADMIN) {
			$sections += [self::UI_SECTION_ADMINISTRATION => _('Administration')];
		}

		return $sections;
	}

	/**
	 * Gets labels of all available rules for specific UI section and user type in order as it need to display in UI.
	 *
	 * @static
	 *
	 * @param string  $ui_section_name  UI section name.
	 * @param integer $user_type        User type.
	 *
	 * @return array  Returns the array where key of each element is rule name and value is rule label.
	 */
	public static function getUiSectionRulesLabels(string $ui_section_name, int $user_type): array {
		switch ($ui_section_name) {
			case self::UI_SECTION_MONITORING:
				$labels = [
					self::UI_MONITORING_DASHBOARD => _('Dashboard'),
					self::UI_MONITORING_PROBLEMS => _('Problems'),
					self::UI_MONITORING_HOSTS => _('Hosts'),
					self::UI_MONITORING_OVERVIEW => _('Overview'),
					self::UI_MONITORING_LATEST_DATA => _('Latest data'),
					self::UI_MONITORING_SCREENS => _('Screens'),
					self::UI_MONITORING_MAPS => _('Maps')
				];

				if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [self::UI_MONITORING_DISCOVERY => _('Discovery')];
				}

				$labels += [self::UI_MONITORING_SERVICES => _('Services')];

				return $labels;
			case self::UI_SECTION_INVENTORY:
				return [
					self::UI_INVENTORY_OVERVIEW => _('Overview'),
					self::UI_INVENTORY_HOSTS => _('Hosts')
				];
			case self::UI_SECTION_REPORTS:
				$labels = [];

				if ($user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [self::UI_REPORTS_SYSTEM_INFO => _('System information')];
				}

				$labels += [
					self::UI_REPORTS_AVAILABILITY_REPORT => _('Availability report'),
					self::UI_REPORTS_TOP_TRIGGERS => _('Triggers top 100')
				];

				if ($user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [
						self::UI_REPORTS_AUDIT => _('Audit'),
						self::UI_REPORTS_ACTION_LOG => _('Action log')
					];
				}

				if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [self::UI_REPORTS_NOTIFICATIONS => _('Notifications')];
				}

				return $labels;
			case self::UI_SECTION_CONFIGURATION:
				$labels = [];

				if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
					$labels = [
						self::UI_CONFIGURATION_HOST_GROUPS => _('Host groups'),
						self::UI_CONFIGURATION_TEMPLATES => _('Templates'),
						self::UI_CONFIGURATION_HOSTS => _('Hosts'),
						self::UI_CONFIGURATION_MAINTENANCE => _('Maintenance'),
						self::UI_CONFIGURATION_ACTIONS => _('Actions')
					];
				}

				if ($user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [self::UI_CONFIGURATION_EVENT_CORRELATION => _('Event correlation')];
				}

				if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [
						self::UI_CONFIGURATION_DISCOVERY => _('Discovery'),
						self::UI_CONFIGURATION_SERVICES => _('Services')
					];
				}

				return $labels;
			case self::UI_SECTION_ADMINISTRATION:
				$labels = [];

				if ($user_type === USER_TYPE_SUPER_ADMIN) {
					$labels = [
						self::UI_ADMINISTRATION_GENERAL => _('General'),
						self::UI_ADMINISTRATION_PROXIES => _('Proxies'),
						self::UI_ADMINISTRATION_AUTHENTICATION => _('Authentication'),
						self::UI_ADMINISTRATION_USER_GROUPS => _('User groups'),
						self::UI_ADMINISTRATION_USER_ROLES => _('User roles'),
						self::UI_ADMINISTRATION_USERS => _('Users'),
						self::UI_ADMINISTRATION_MEDIA_TYPES => _('Media types'),
						self::UI_ADMINISTRATION_SCRIPTS => _('Scripts'),
						self::UI_ADMINISTRATION_QUEUE => _('Queue')
					];
				}

				return $labels;
			default:
				return [];
		}
	}

	/**
	 * Gets labels of all available actions rules for specific user type in order as it need to display on user roles
	 * page.
	 *
	 * @static
	 *
	 * @param integer $user_type  User type.
	 *
	 * @return array  Returns the array where key of each element is rule name and value is rule label.
	 */
	public static function getActionsLabels(int $user_type): array {
		$labels = [
			self::ACTIONS_EDIT_DASHBOARDS => _('Create and edit dashboards and screens'),
			self::ACTIONS_EDIT_MAPS => _('Create and edit maps')
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$labels += [self::ACTIONS_EDIT_MAINTENANCE => _('Create and edit maintenance')];
		}

		$labels += [
			self::ACTIONS_UPDATE_PROBLEMS => _('Acknowledge problems'),
			self::ACTIONS_EXECUTE_SCRIPTS => _('Execute scripts')
		];

		return $labels;
	}

	/**
	 * Returns a list of all API methods by user type or API methods available only for the given user type.
	 *
	 * @static
	 *
	 * @param int|null $user_type
	 *
	 * @return array
	 */
	public static function getApiMethods(?int $user_type = null): array {
		if (!self::$api_methods) {
			self::loadApiMethods();
		}

		return ($user_type !== null) ? self::$api_methods[$user_type] : self::$api_methods;
	}

	/**
	 * Collects all API methods for all user types.
	 *
	 * @static
	 */
	private static function loadApiMethods(): void {
		$api_methods = [
			USER_TYPE_ZABBIX_USER => [],
			USER_TYPE_ZABBIX_ADMIN => [],
			USER_TYPE_SUPER_ADMIN => []
		];

		foreach (CApiServiceFactory::API_SERVICES as $service => $class_name) {
			foreach (constant($class_name.'::ACCESS_RULES') as $method => $rules) {
				if (array_key_exists('min_user_type', $rules)) {
					switch ($rules['min_user_type']) {
						case USER_TYPE_ZABBIX_USER:
							$api_methods[USER_TYPE_ZABBIX_USER][] = $service.'.'.$method;
							// break; is not missing here
						case USER_TYPE_ZABBIX_ADMIN:
							$api_methods[USER_TYPE_ZABBIX_ADMIN][] = $service.'.'.$method;
							// break; is not missing here
						case USER_TYPE_SUPER_ADMIN:
							$api_methods[USER_TYPE_SUPER_ADMIN][] = $service.'.'.$method;
					}
				}
			}
		}

		self::$api_methods = $api_methods;
	}
}

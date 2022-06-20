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


/**
 * A class designed to perform actions related to check access of roles.
 */
class CRoleHelper {

	public const UI_MONITORING_DASHBOARD = 'ui.monitoring.dashboard';
	public const UI_MONITORING_PROBLEMS = 'ui.monitoring.problems';
	public const UI_MONITORING_HOSTS = 'ui.monitoring.hosts';
	public const UI_MONITORING_LATEST_DATA = 'ui.monitoring.latest_data';
	public const UI_MONITORING_MAPS = 'ui.monitoring.maps';
	public const UI_MONITORING_DISCOVERY = 'ui.monitoring.discovery';
	public const UI_INVENTORY_OVERVIEW = 'ui.inventory.overview';
	public const UI_INVENTORY_HOSTS = 'ui.inventory.hosts';
	public const UI_REPORTS_SYSTEM_INFO = 'ui.reports.system_info';
	public const UI_REPORTS_AVAILABILITY_REPORT = 'ui.reports.availability_report';
	public const UI_REPORTS_TOP_TRIGGERS = 'ui.reports.top_triggers';
	public const UI_REPORTS_AUDIT = 'ui.reports.audit';
	public const UI_REPORTS_ACTION_LOG = 'ui.reports.action_log';
	public const UI_REPORTS_NOTIFICATIONS = 'ui.reports.notifications';
	public const UI_REPORTS_SCHEDULED_REPORTS = 'ui.reports.scheduled_reports';
	public const UI_SERVICES_SERVICES = 'ui.services.services';
	public const UI_SERVICES_ACTIONS = 'ui.services.actions';
	public const UI_SERVICES_SLA = 'ui.services.sla';
	public const UI_SERVICES_SLA_REPORT = 'ui.services.sla_report';
	public const UI_CONFIGURATION_TEMPLATE_GROUPS = 'ui.configuration.template_groups';
	public const UI_CONFIGURATION_HOST_GROUPS = 'ui.configuration.host_groups';
	public const UI_CONFIGURATION_TEMPLATES = 'ui.configuration.templates';
	public const UI_CONFIGURATION_HOSTS = 'ui.configuration.hosts';
	public const UI_CONFIGURATION_MAINTENANCE = 'ui.configuration.maintenance';
	public const UI_CONFIGURATION_ACTIONS = 'ui.configuration.actions';
	public const UI_CONFIGURATION_EVENT_CORRELATION = 'ui.configuration.event_correlation';
	public const UI_CONFIGURATION_DISCOVERY = 'ui.configuration.discovery';
	public const UI_ADMINISTRATION_GENERAL = 'ui.administration.general';
	public const UI_ADMINISTRATION_PROXIES = 'ui.administration.proxies';
	public const UI_ADMINISTRATION_AUTHENTICATION = 'ui.administration.authentication';
	public const UI_ADMINISTRATION_USER_GROUPS = 'ui.administration.user_groups';
	public const UI_ADMINISTRATION_USER_ROLES = 'ui.administration.user_roles';
	public const UI_ADMINISTRATION_USERS = 'ui.administration.users';
	public const UI_ADMINISTRATION_MEDIA_TYPES = 'ui.administration.media_types';
	public const UI_ADMINISTRATION_SCRIPTS = 'ui.administration.scripts';
	public const UI_ADMINISTRATION_QUEUE = 'ui.administration.queue';

	public const ACTIONS_EDIT_DASHBOARDS = 'actions.edit_dashboards';
	public const ACTIONS_EDIT_MAPS = 'actions.edit_maps';
	public const ACTIONS_EDIT_MAINTENANCE = 'actions.edit_maintenance';
	public const ACTIONS_ADD_PROBLEM_COMMENTS = 'actions.add_problem_comments';
	public const ACTIONS_CHANGE_SEVERITY = 'actions.change_severity';
	public const ACTIONS_ACKNOWLEDGE_PROBLEMS = 'actions.acknowledge_problems';
	public const ACTIONS_SUPPRESS_PROBLEMS = 'actions.suppress_problems';
	public const ACTIONS_CLOSE_PROBLEMS = 'actions.close_problems';
	public const ACTIONS_EXECUTE_SCRIPTS = 'actions.execute_scripts';
	public const ACTIONS_MANAGE_API_TOKENS = 'actions.manage_api_tokens';
	public const ACTIONS_MANAGE_SCHEDULED_REPORTS = 'actions.manage_scheduled_reports';
	public const ACTIONS_MANAGE_SLA = 'actions.manage_sla';
	public const ACTIONS_INVOKE_EXECUTE_NOW = 'actions.invoke_execute_now';

	public const UI_SECTION_MONITORING = 'ui.monitoring';
	public const UI_SECTION_SERVICES = 'ui.services';
	public const UI_SECTION_INVENTORY = 'ui.inventory';
	public const UI_SECTION_REPORTS = 'ui.reports';
	public const UI_SECTION_CONFIGURATION = 'ui.configuration';
	public const UI_SECTION_ADMINISTRATION = 'ui.administration';

	public const API_ANY_METHOD = '.*';
	public const API_ANY_SERVICE = '*.';

	public const SERVICES_ACCESS_NONE = 0;
	public const SERVICES_ACCESS_ALL = 1;
	public const SERVICES_ACCESS_LIST = 2;

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
	 * Array for storing all API methods by user type.
	 *
	 * @var array
	 */
	private static $api_methods = [];

	/**
	 * Array for storing all API methods with masks by user type.
	 *
	 * @var array
	 */
	private static $api_method_masks = [];

	/**
	 * Checks the access of specific role to specific rule.
	 *
	 * @static
	 *
	 * @param string $rule_name  Name of the rule to check access for.
	 * @param string $roleid     ID of the role where check of access is necessary to perform.
	 *
	 * @return bool  Returns true if role have access to specified rule, false - otherwise.
	 *
	 * @throws Exception
	 */
	public static function checkAccess(string $rule_name, string $roleid): bool {
		self::loadRoleRules($roleid);

		if (!array_key_exists($rule_name, self::$roles[$roleid]['rules']) || $rule_name === 'api') {
			return false;
		}

		return self::$roles[$roleid]['rules'][$rule_name];
	}

	/**
	 * Gets list of API methods (with wildcards if that exists) that are considered allowed or denied (depending on
	 * API access mode) for specific role.
	 *
	 * @static
	 *
	 * @param string $roleid  Role ID.
	 *
	 * @return array  Returns the array of API methods.
	 *
	 * @throws Exception
	 */
	public static function getRoleApiMethods(string $roleid): array {
		self::loadRoleRules($roleid);

		return self::$roles[$roleid]['rules']['api'];
	}

	/**
	 * Loads once all rules of specified Role API objects by ID and converts rule data to one format.
	 *
	 * @static
	 *
	 * @param string $roleid  Role ID.
	 *
	 * @throws Exception
	 */
	private static function loadRoleRules(string $roleid): void {
		if (array_key_exists($roleid, self::$roles)) {
			return;
		}

		$roles = API::Role()->get([
			'output' => ['roleid', 'name', 'type'],
			'selectRules' => ['ui', 'ui.default_access', 'modules', 'modules.default_access', 'api.access', 'api.mode',
				'api', 'actions', 'actions.default_access'
			],
			'roleids' => $roleid
		]);

		if (!$roles) {
			throw new Exception(_('Specified role was not found.'));
		}

		$role = $roles[0];

		$rules = [
			'ui.default_access' => (bool) $role['rules']['ui.default_access'],
			'modules.default_access' => (bool) $role['rules']['modules.default_access'],
			'api.access' => (bool) $role['rules']['api.access'],
			'api.mode' => (bool) $role['rules']['api.mode'],
			'api' => $role['rules']['api'],
			'actions.default_access' => (bool) $role['rules']['actions.default_access']
		];

		foreach ($role['rules']['ui'] as $rule) {
			$rules['ui.'.$rule['name']] = (bool) $rule['status'];
		}

		foreach ($role['rules']['modules'] as $module) {
			$rules['modules.module.'.$module['moduleid']] = (bool) $module['status'];
		}

		foreach ($role['rules']['actions'] as $rule) {
			$rules['actions.'.$rule['name']] = (bool) $rule['status'];
		}

		$role['type'] = (int) $role['type'];
		$role['rules'] = $rules;

		self::$roles[$roleid] = $role;
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
	public static function getUiElementsByUserType(int $user_type): array {
		$rules = [
			self::UI_INVENTORY_HOSTS,
			self::UI_INVENTORY_OVERVIEW,
			self::UI_MONITORING_DASHBOARD,
			self::UI_MONITORING_HOSTS,
			self::UI_MONITORING_LATEST_DATA,
			self::UI_MONITORING_MAPS,
			self::UI_MONITORING_PROBLEMS,
			self::UI_REPORTS_AVAILABILITY_REPORT,
			self::UI_REPORTS_TOP_TRIGGERS,
			self::UI_SERVICES_SERVICES,
			self::UI_SERVICES_SLA_REPORT
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$rules = array_merge($rules, [
				self::UI_CONFIGURATION_ACTIONS,
				self::UI_CONFIGURATION_DISCOVERY,
				self::UI_CONFIGURATION_HOST_GROUPS,
				self::UI_CONFIGURATION_HOSTS,
				self::UI_CONFIGURATION_MAINTENANCE,
				self::UI_CONFIGURATION_TEMPLATES,
				self::UI_CONFIGURATION_TEMPLATE_GROUPS,
				self::UI_MONITORING_DISCOVERY,
				self::UI_REPORTS_NOTIFICATIONS,
				self::UI_REPORTS_SCHEDULED_REPORTS,
				self::UI_SERVICES_ACTIONS,
				self::UI_SERVICES_SLA
			]);
		}

		if ($user_type === USER_TYPE_SUPER_ADMIN) {
			$rules = array_merge($rules, [
				self::UI_ADMINISTRATION_AUTHENTICATION,
				self::UI_ADMINISTRATION_GENERAL,
				self::UI_ADMINISTRATION_MEDIA_TYPES,
				self::UI_ADMINISTRATION_PROXIES,
				self::UI_ADMINISTRATION_QUEUE,
				self::UI_ADMINISTRATION_SCRIPTS,
				self::UI_ADMINISTRATION_USER_GROUPS,
				self::UI_ADMINISTRATION_USER_ROLES,
				self::UI_ADMINISTRATION_USERS,
				self::UI_CONFIGURATION_EVENT_CORRELATION,
				self::UI_REPORTS_ACTION_LOG,
				self::UI_REPORTS_AUDIT,
				self::UI_REPORTS_SYSTEM_INFO
			]);
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
	public static function getActionsByUserType(int $user_type): array {
		$rules = [
			self::ACTIONS_EDIT_DASHBOARDS, self::ACTIONS_EDIT_MAPS, self::ACTIONS_ACKNOWLEDGE_PROBLEMS,
			self::ACTIONS_SUPPRESS_PROBLEMS, self::ACTIONS_CLOSE_PROBLEMS, self::ACTIONS_CHANGE_SEVERITY,
			self::ACTIONS_ADD_PROBLEM_COMMENTS, self::ACTIONS_EXECUTE_SCRIPTS, self::ACTIONS_MANAGE_API_TOKENS
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$rules[] = self::ACTIONS_EDIT_MAINTENANCE;
			$rules[] = self::ACTIONS_MANAGE_SCHEDULED_REPORTS;
			$rules[] = self::ACTIONS_MANAGE_SLA;
		}

		$rules[] = self::ACTIONS_INVOKE_EXECUTE_NOW;

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
			self::UI_SECTION_SERVICES => _('Services'),
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
					self::UI_MONITORING_LATEST_DATA => _('Latest data'),
					self::UI_MONITORING_MAPS => _('Maps')
				];

				if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [self::UI_MONITORING_DISCOVERY => _('Discovery')];
				}

				return $labels;

			case self::UI_SECTION_SERVICES:
				$labels = [
					self::UI_SERVICES_SERVICES => _('Services'),
					self::UI_SERVICES_ACTIONS => _('Service actions')
				];

				if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [self::UI_SERVICES_SLA => _('SLA')];
				}

				$labels += [self::UI_SERVICES_SLA_REPORT => _('SLA report')];

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

				if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
					$labels += [self::UI_REPORTS_SCHEDULED_REPORTS => _('Scheduled reports')];
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
						self::UI_CONFIGURATION_TEMPLATE_GROUPS => _('Template groups'),
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
					$labels += [self::UI_CONFIGURATION_DISCOVERY => _('Discovery')];
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
			self::ACTIONS_EDIT_DASHBOARDS => _('Create and edit dashboards'),
			self::ACTIONS_EDIT_MAPS => _('Create and edit maps')
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$labels += [self::ACTIONS_EDIT_MAINTENANCE => _('Create and edit maintenance')];
		}

		$labels += [
			self::ACTIONS_ADD_PROBLEM_COMMENTS => _('Add problem comments'),
			self::ACTIONS_CHANGE_SEVERITY => _('Change severity'),
			self::ACTIONS_ACKNOWLEDGE_PROBLEMS => _('Acknowledge problems'),
			self::ACTIONS_SUPPRESS_PROBLEMS => _('Suppress problems'),
			self::ACTIONS_CLOSE_PROBLEMS => _('Close problems'),
			self::ACTIONS_EXECUTE_SCRIPTS => _('Execute scripts'),
			self::ACTIONS_MANAGE_API_TOKENS => _('Manage API tokens')
		];

		if ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN) {
			$labels += [
				self::ACTIONS_MANAGE_SCHEDULED_REPORTS => _('Manage scheduled reports'),
				self::ACTIONS_MANAGE_SLA => _('Manage SLA')
			];
		}

		$labels += [
			self::ACTIONS_INVOKE_EXECUTE_NOW => _('Invoke "Execute now" on read-only hosts')
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
	 * Returns a list of API methods with masks for the given user type.
	 *
	 * @static
	 *
	 * @param int|null $user_type
	 *
	 * @return array
	 */
	public static function getApiMethodMasks(?int $user_type = null): array {
		if (!self::$api_method_masks) {
			self::loadApiMethods();
		}

		return ($user_type !== null) ? self::$api_method_masks[$user_type] : self::$api_method_masks;
	}

	/**
	 * Returns a list of API methods for each method mask for the given user type.
	 *
	 * @static
	 *
	 * @param int $user_type
	 *
	 * @return array
	 */
	public static function getApiMaskMethods(int $user_type): array {
		$api_methods = self::getApiMethods($user_type);
		$result = [ZBX_ROLE_RULE_API_WILDCARD => $api_methods, ZBX_ROLE_RULE_API_WILDCARD_ALIAS => $api_methods];

		foreach ($api_methods as $api_method) {
			[$service, $method] = explode('.', $api_method, 2);
			$result[$service.self::API_ANY_METHOD][] = $api_method;
			$result[self::API_ANY_SERVICE.$method][] = $api_method;
		}

		return $result;
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
		$api_method_masks = $api_methods;

		foreach (CApiServiceFactory::API_SERVICES as $service => $class_name) {
			foreach (constant($class_name.'::ACCESS_RULES') as $method => $rules) {
				if (array_key_exists('min_user_type', $rules)) {
					switch ($rules['min_user_type']) {
						case USER_TYPE_ZABBIX_USER:
							$api_methods[USER_TYPE_ZABBIX_USER][] = $service.'.'.$method;
							$api_method_masks[USER_TYPE_ZABBIX_USER][$service.self::API_ANY_METHOD] = true;
							$api_method_masks[USER_TYPE_ZABBIX_USER][self::API_ANY_SERVICE.$method] = true;
							// break; is not missing here
						case USER_TYPE_ZABBIX_ADMIN:
							$api_methods[USER_TYPE_ZABBIX_ADMIN][] = $service.'.'.$method;
							$api_method_masks[USER_TYPE_ZABBIX_ADMIN][$service.self::API_ANY_METHOD] = true;
							$api_method_masks[USER_TYPE_ZABBIX_ADMIN][self::API_ANY_SERVICE.$method] = true;
							// break; is not missing here
						case USER_TYPE_SUPER_ADMIN:
							$api_methods[USER_TYPE_SUPER_ADMIN][] = $service.'.'.$method;
							$api_method_masks[USER_TYPE_SUPER_ADMIN][$service.self::API_ANY_METHOD] = true;
							$api_method_masks[USER_TYPE_SUPER_ADMIN][self::API_ANY_SERVICE.$method] = true;
					}
				}
			}
		}

		foreach ($api_method_masks as $user_type => $masks) {
			$api_method_masks[$user_type] = array_merge([ZBX_ROLE_RULE_API_WILDCARD, ZBX_ROLE_RULE_API_WILDCARD_ALIAS],
				array_keys($masks)
			);
		}

		self::$api_methods = $api_methods;
		self::$api_method_masks = $api_method_masks;
	}
}

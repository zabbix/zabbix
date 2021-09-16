<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class to log audit records.
 */
class CAudit {
	/**
	 * Audit actions.
	 *
	 * @var int
	 */
	public const ACTION_ADD = 0;
	public const ACTION_UPDATE = 1;
	public const ACTION_DELETE = 2;
	public const ACTION_LOGIN = 3;
	public const ACTION_LOGOUT = 4;
	public const ACTION_EXECUTE = 7;

	/**
	 * Audit resources.
	 *
	 * @var int
	 */
	public const RESOURCE_USER = 0;
	public const RESOURCE_MEDIA_TYPE = 3;
	public const RESOURCE_HOST = 4;
	public const RESOURCE_ACTION = 5;
	public const RESOURCE_GRAPH = 6;
	public const RESOURCE_USER_GROUP = 11;
	public const RESOURCE_TRIGGER = 13;
	public const RESOURCE_HOST_GROUP = 14;
	public const RESOURCE_ITEM = 15;
	public const RESOURCE_IMAGE = 16;
	public const RESOURCE_VALUE_MAP = 17;
	public const RESOURCE_IT_SERVICE = 18;
	public const RESOURCE_MAP = 19;
	public const RESOURCE_SCENARIO = 22;
	public const RESOURCE_DISCOVERY_RULE = 23;
	public const RESOURCE_SCRIPT = 25;
	public const RESOURCE_PROXY = 26;
	public const RESOURCE_MAINTENANCE = 27;
	public const RESOURCE_REGEXP = 28;
	public const RESOURCE_MACRO = 29;
	public const RESOURCE_TEMPLATE = 30;
	public const RESOURCE_TRIGGER_PROTOTYPE = 31;
	public const RESOURCE_ICON_MAP = 32;
	public const RESOURCE_DASHBOARD = 33;
	public const RESOURCE_CORRELATION = 34;
	public const RESOURCE_GRAPH_PROTOTYPE = 35;
	public const RESOURCE_ITEM_PROTOTYPE = 36;
	public const RESOURCE_HOST_PROTOTYPE = 37;
	public const RESOURCE_AUTOREGISTRATION = 38;
	public const RESOURCE_MODULE = 39;
	public const RESOURCE_SETTINGS = 40;
	public const RESOURCE_HOUSEKEEPING = 41;
	public const RESOURCE_AUTHENTICATION = 42;
	public const RESOURCE_TEMPLATE_DASHBOARD = 43;
	public const RESOURCE_USER_ROLE = 44;
	public const RESOURCE_AUTH_TOKEN = 45;
	public const RESOURCE_SCHEDULED_REPORT = 46;
	public const RESOURCE_GLOBAL_USER_MACRO = 47;

	/**
	 * Audit details actions.
	 *
	 * @var string
	 */
	public const DETAILS_ACTION_ADD = 'add';
	public const DETAILS_ACTION_UPDATE = 'update';
	public const DETAILS_ACTION_DELETE = 'delete';

	/**
	 * Auditlog enabled value.
	 *
	 * @var int
	 */
	private const AUDITLOG_ENABLE = 1;

	/**
	 * Table names of audit resources.
	 * resource => table name
	 *
	 * @var array
	 */
	private const TABLE_NAMES = [
		self::RESOURCE_AUTHENTICATION => 'config',
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_AUTOREGISTRATION => 'config',
		self::RESOURCE_GLOBAL_USER_MACRO => 'globalmacro',
		self::RESOURCE_HOUSEKEEPING => 'config',
		self::RESOURCE_MODULE => 'module',
		self::RESOURCE_REGEXP => 'regexps',
		self::RESOURCE_SCHEDULED_REPORT => 'report',
		self::RESOURCE_SETTINGS => 'config',
		self::RESOURCE_USER => 'users',
		self::RESOURCE_USER_GROUP => 'usrgrp'
	];

	/**
	 * Name field names of audit resources.
	 * resource => name field
	 *
	 * @var array
	 */
	private const FIELD_NAMES = [
		self::RESOURCE_AUTHENTICATION => null,
		self::RESOURCE_AUTH_TOKEN => 'name',
		self::RESOURCE_AUTOREGISTRATION => null,
		self::RESOURCE_GLOBAL_USER_MACRO => 'macro',
		self::RESOURCE_HOUSEKEEPING => null,
		self::RESOURCE_MODULE => 'id',
		self::RESOURCE_REGEXP => 'name',
		self::RESOURCE_SCHEDULED_REPORT => 'name',
		self::RESOURCE_SETTINGS => null,
		self::RESOURCE_USER => 'username',
		self::RESOURCE_USER_GROUP => 'name'
	];

	/**
	 * API names of audit resources.
	 * resource => API name
	 *
	 * @var array
	 */
	private const API_NAMES = [
		self::RESOURCE_AUTHENTICATION => 'authentication',
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_AUTOREGISTRATION => 'autoregistration',
		self::RESOURCE_GLOBAL_USER_MACRO => 'usermacro',
		self::RESOURCE_HOUSEKEEPING => 'housekeeping',
		self::RESOURCE_MODULE => 'module',
		self::RESOURCE_REGEXP => 'regexp',
		self::RESOURCE_SETTINGS => 'settings',
		self::RESOURCE_SCHEDULED_REPORT => 'report',
		self::RESOURCE_USER => 'user',
		self::RESOURCE_USER_GROUP => 'usergroup'
	];

	/**
	 * Array of paths that should be masked in audit details.
	 *
	 * @var array
	 */
	private const MASKED_PATHS = [
		self::RESOURCE_AUTHENTICATION => ['paths' => ['authentication.ldap_bind_password']],
		self::RESOURCE_AUTH_TOKEN => ['paths' => ['token.token']],
		self::RESOURCE_AUTOREGISTRATION => ['paths' => ['autoregistration.tls_psk_identity', 'autoregistration.tls_psk']],
		// self::RESOURCE_MACRO => [
		// 	'paths' => ['usermacro.value'],
		// 	'conditions' => ['usermacro.type' => ZBX_MACRO_TYPE_SECRET]
		// ],
		self::RESOURCE_GLOBAL_USER_MACRO => [
			'paths' => ['usermacro.value'],
			'conditions' => ['usermacro.type' => ZBX_MACRO_TYPE_SECRET]
		],
		self::RESOURCE_USER => ['paths' => ['user.passwd']]
	];

	/**
	 * Table names of nested objects.
	 * path => table name
	 *
	 * @var array
	 */
	private const NESTED_OBJECTS_TABLE_NAMES = [
		'regexp.expressions' => 'expressions',
		'report.users' => 'report_user',
		'report.user_groups' => 'report_usrgrp',
		'user.medias' => 'media',
		'user.usrgrps' => 'users_groups',
		'usergroup.rights' => 'rights',
		'usergroup.tag_filters' => 'tag_filter',
		'usergroup.users' => 'users_groups'
	];

	/**
	 * ID field names of nested objects.
	 * path => id field
	 *
	 * @var array
	 */
	private const NESTED_OBJECTS_IDS = [
		'regexp.expressions' => 'expressionid',
		'report.users' => 'reportuserid',
		'report.user_groups' => 'reportusrgrpid',
		'user.medias' => 'mediaid',
		'user.usrgrps' => 'id',
		'usergroup.rights' => 'rightid',
		'usergroup.tag_filters' => 'tag_filterid',
		'usergroup.users' => 'id'
	];

	/**
	 * Array of paths that should be skipped in audit details.
	 *
	 * @var array
	 */
	private const SKIP_FIELDS = ['token.creator_userid', 'token.created_at'];

	/**
	 * Add audit records.
	 *
	 * @param string     $userid
	 * @param string     $ip
	 * @param string     $username
	 * @param int        $action
	 * @param int        $resource
	 * @param array      $objects
	 * @param array|null $db_objects
	 */
	public static function log(string $userid, string $ip, string $username, int $action, int $resource, array $objects,
			?array $db_objects): void {
		if (!self::isAuditEnabled() && ($resource != self::RESOURCE_SETTINGS
					|| !array_key_exists(CSettingsHelper::AUDITLOG_ENABLED, current($objects)))) {
			return;
		}

		$auditlog = [];
		$table_key = DB::getPk(self::TABLE_NAMES[$resource]);
		$clock = time();
		$ip = substr($ip, 0, DB::getFieldLength('auditlog', 'ip'));
		$recordsetid = self::getRecordSetId();

		foreach ($objects as $object) {
			$resourceid = $object[$table_key];
			$db_object = ($action == self::ACTION_UPDATE) ? $db_objects[$resourceid] : [];
			$resource_name = self::getResourceName($resource, $action, $object, $db_object);

			$diff = self::handleObjectDiff($resource, $action, $object, $db_object);

			if ($action == self::ACTION_UPDATE && count($diff) === 0) {
				continue;
			}

			$auditlog[] = [
				'userid' => $userid,
				'username' => $username,
				'clock' => $clock,
				'ip' => $ip,
				'action' => $action,
				'resourcetype' => $resource,
				'resourceid' => $resourceid,
				'resourcename' => $resource_name,
				'recordsetid' => $recordsetid,
				'details' => (count($diff) == 0) ? '' : json_encode($diff)
			];
		}

		DB::insertBatch('auditlog', $auditlog);
	}

	/**
	 * Return recordsetid. Generate recordsetid if its not been generated yet.
	 *
	 * @return string
	 */
	private static function getRecordSetId(): string {
		static $recordsetid = null;

		if ($recordsetid === null) {
			$recordsetid = CCuid::generate();
		}

		return $recordsetid;
	}

	/**
	 * Check audit logging is enabled.
	 *
	 * @return bool
	 */
	private static function isAuditEnabled(): bool {
		return CSettingsHelper::get(CSettingsHelper::AUDITLOG_ENABLED) == self::AUDITLOG_ENABLE;
	}

	/**
	 * Return resource name of logging object.
	 *
	 * @param int   $resource
	 * @param int   $action
	 * @param array $object
	 * @param array $db_object
	 *
	 * @return string
	 */
	private static function getResourceName(int $resource, int $action, array $object, array $db_object): string {
		$field_name = self::FIELD_NAMES[$resource];
		$resource_name = ($field_name !== null)
			? (($action == self::ACTION_UPDATE)
				? $db_object[$field_name]
				: $object[$field_name])
			: '';

		if (mb_strlen($resource_name) > 255) {
			$resource_name = mb_substr($resource_name, 0, 252).'...';
		}

		return $resource_name;
	}

	/**
	 * Prepares the details for audit log.
	 *
	 * @param int   $resource
	 * @param int   $action
	 * @param array $object
	 * @param array $db_object
	 *
	 * @return array
	 */
	private static function handleObjectDiff(int $resource, int $action, array $object, array $db_object): array {
		if (!in_array($action, [self::ACTION_ADD, self::ACTION_UPDATE])) {
			return [];
		}

		$api_name = self::API_NAMES[$resource];
		$object = self::convertKeysToPaths($api_name, $object);

		switch ($action) {
			case self::ACTION_ADD:
				return self::handleAdd($resource, $object);

			case self::ACTION_UPDATE:
				$db_object = self::convertKeysToPaths($api_name, $db_object);
				return self::handleUpdate($resource, $object, $db_object);
		}
	}

	/**
	 * Checks by path, whether the value of the object should be masked.
	 *
	 * @param int    $resource
	 * @param string $path
	 * @param array  $object
	 *
	 * @return bool
	 */
	private static function isValueToMask(int $resource, string $path, array $object): bool {
		if (!array_key_exists($resource, self::MASKED_PATHS)) {
			return false;
		}

		if (strpos($path, '[') !== false) {
			$path = preg_replace('/\[[0-9]+\]/', '', $path);
		}

		if (!array_key_exists('conditions', self::MASKED_PATHS[$resource])) {
			return in_array($path, self::MASKED_PATHS[$resource]['paths']);
		}

		if (in_array($path, self::MASKED_PATHS[$resource]['paths'])) {
			$all_counditions = count(self::MASKED_PATHS[$resource]['conditions']);
			$true_conditions = 0;

			foreach (self::MASKED_PATHS[$resource]['conditions'] as $condition_path => $value) {
				if (array_key_exists($condition_path, $object) && $object[$condition_path] == $value) {
					$true_conditions++;
				}
			}

			return ($true_conditions == $all_counditions);
		}

		return false;
	}

	/**
	 * Converts the object properties to the one-dimensional array where the key is a path.
	 *
	 * @param string $prefix
	 * @param array  $object
	 *
	 * @return array
	 */
	private static function convertKeysToPaths(string $prefix, array $object): array {
		$result = [];

		foreach ($object as $key => $value) {
			$index = '.'.$key;

			if (array_key_exists($prefix, self::NESTED_OBJECTS_IDS)) {
				$index = '['.$value[self::NESTED_OBJECTS_IDS[$prefix]].']';
				unset($value[self::NESTED_OBJECTS_IDS[$prefix]]);
			}

			$new_prefix = $prefix.$index;

			if (in_array($new_prefix, self::SKIP_FIELDS)) {
				continue;
			}

			if (is_array($value)) {
				$result += self::convertKeysToPaths($new_prefix, $value);
			}
			else {
				$result[$new_prefix] = (string) $value;
			}
		}

		return $result;
	}

	/**
	 * Checks by path, whether the value is equal to default value from the database schema.
	 *
	 * @param int     $resource
	 * @param string  $path
	 * @param string  $value
	 *
	 * @return bool
	 */
	private static function isDefaultValue(int $resource, string $path, string $value): bool {
		$object_path = self::getLastObjectPath($path);
		$table_name = self::TABLE_NAMES[$resource];

		if ($object_path !== self::API_NAMES[$resource]) {
			if (strpos($object_path, '[') !== false) {
				$object_path = preg_replace('/\[[0-9]+\]/', '', $object_path);
			}

			$table_name = self::NESTED_OBJECTS_TABLE_NAMES[$object_path];
		}

		$schema_fields = DB::getSchema($table_name)['fields'];
		$field_name = substr($path, strrpos($path, '.') + 1);

		if (!array_key_exists($field_name, $schema_fields)) {
			return false;
		}

		if (!array_key_exists('default', $schema_fields[$field_name])) {
			return false;
		}

		return $value == $schema_fields[$field_name]['default'];
	}

	/**
	 * Checks whether a path is path to nested object property.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	private static function isNestedObjectProperty(string $path): bool {
		return (count(explode('.', $path)) > 2);
	}

	/**
	 * Return the path to the parent property object from the passed path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private static function getLastObjectPath(string $path): string {
		return substr($path, 0, strrpos($path, '.'));
	}

	/**
	 * Return the paths to nested object properties from the paths of passing object.
	 *
	 * @param array $object
	 *
	 * @return array
	 */
	private static function getNestedObjectsPaths(array $object): array {
		$paths = [];

		foreach ($object as $path => $foo) {
			if (!self::isNestedObjectProperty($path)) {
				continue;
			}

			$object_path = self::getLastObjectPath($path);

			if (!in_array($object_path, $paths)) {
				$paths[] = $object_path;
			}
		}

		return $paths;
	}

	/**
	 * Prepares the audit details for add action.
	 *
	 * @param int   $resource
	 * @param array $object
	 *
	 * @return array
	 */
	private static function handleAdd(int $resource, array $object): array {
		$result = [];

		foreach ($object as $path => $value) {
			if (self::isNestedObjectProperty($path)) {
				$result[self::getLastObjectPath($path)] = [self::DETAILS_ACTION_ADD];
			}

			if (self::isDefaultValue($resource, $path, $value)) {
				continue;
			}

			if (self::isValueToMask($resource, $path, $object)) {
				$result[$path] = [self::DETAILS_ACTION_ADD, ZBX_SECRET_MASK];
			}
			else {
				$result[$path] = [self::DETAILS_ACTION_ADD, $value];
			}
		}

		return $result;
	}

	/**
	 * Prepares the audit details for update action.
	 *
	 * @param int   $resource
	 * @param array $object
	 * @param array $db_object
	 *
	 * @return array
	 */
	private static function handleUpdate(int $resource, array $object, array $db_object): array {
		$result = [];
		$full_object = $object + $db_object;
		$nested_objects_paths = self::getNestedObjectsPaths($object);
		$db_nested_objects_paths = self::getNestedObjectsPaths($db_object);

		foreach ($db_nested_objects_paths as $path) {
			if (!in_array($path, $nested_objects_paths)) {
				$result[$path] = [self::DETAILS_ACTION_DELETE];
			}
		}

		foreach ($nested_objects_paths as $path) {
			if (!in_array($path, $db_nested_objects_paths)) {
				$result[$path] = [self::DETAILS_ACTION_ADD];
			}
		}

		foreach ($object as $path => $foo) {
			$value = array_key_exists($path, $object) ? $object[$path] : null;
			$db_value = array_key_exists($path, $db_object) ? $db_object[$path] : null;

			if ($db_value === null) {
				if (self::isDefaultValue($resource, $path, $value)) {
					continue;
				}

				if (self::isValueToMask($resource, $path, $object)) {
					$result[$path] = [self::DETAILS_ACTION_ADD, ZBX_SECRET_MASK];
				}
				else {
					$result[$path] = [self::DETAILS_ACTION_ADD, $value];
				}
			}
			else {
				if ($value != $db_value) {
					if (self::isNestedObjectProperty($path)) {
						$result[self::getLastObjectPath($path)] = [self::DETAILS_ACTION_UPDATE];
					}

					$is_value_to_mask = self::isValueToMask($resource, $path, $full_object);
					$is_db_value_to_mask = self::isValueToMask($resource, $path, $db_object);

					if ($is_value_to_mask || $is_db_value_to_mask) {
						$secret_value = $is_value_to_mask ? ZBX_SECRET_MASK : $value;
						$db_secret_value = $is_db_value_to_mask ? ZBX_SECRET_MASK : $db_value;

						$result[$path] = [self::DETAILS_ACTION_UPDATE, $secret_value, $db_secret_value];
					}
					else {
						$result[$path] = [self::DETAILS_ACTION_UPDATE, $value, $db_value];
					}
				}
			}
		}

		return $result;
	}
}

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


class CAudit {
	public const ACTION_ADD = 0;
	public const ACTION_UPDATE = 1;
	public const ACTION_DELETE = 2;
	public const ACTION_LOGIN = 3;
	public const ACTION_LOGOUT = 4;
	public const ACTION_EXECUTE = 7;

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

	public const DETAILS_ACTION_ADD = 'add';
	public const DETAILS_ACTION_UPDATE = 'update';
	public const DETAILS_ACTION_DELETE = 'delete';

	private const AUDITLOG_ENABLE = 1;

	private const RESOURCES_TABLE_NAME = [
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_USER => 'users'
	];

	private const RESOURCES_FIELD_NAME = [
		self::RESOURCE_AUTH_TOKEN => 'name',
		self::RESOURCE_USER => 'username'
	];

	private const RESOURCES_API_NAME = [
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_USER => 'user'
	];

	private const PATHS_TABLE_NAME = [
		self::RESOURCES_API_NAME[self::RESOURCE_AUTH_TOKEN] => 'token',
		self::RESOURCES_API_NAME[self::RESOURCE_USER] => 'users',
		self::RESOURCES_API_NAME[self::RESOURCE_USER].'.medias' => 'media',
		self::RESOURCES_API_NAME[self::RESOURCE_USER].'.usrgrps' => 'users_groups',
	];

	private const MASKED_PATHS = [
		['path' => self::RESOURCES_API_NAME[self::RESOURCE_AUTH_TOKEN].'.token'],
		// [
		// 	'path' => self::RESOURCES_API_NAME[self::RESOURCE_MACRO].'.value',
		// 	'conditions' => [self::RESOURCES_API_NAME[self::RESOURCE_MACRO].'.type' => ZBX_MACRO_TYPE_SECRET]
		// ],
		['path' => self::RESOURCES_API_NAME[self::RESOURCE_USER].'.passwd']
	];

	public static function log(string $userid, string $ip, string $username, int $action, int $resource, array $objects,
			?array $old_objects): void {
		if (!self::isAuditEnabled() && ($resource != self::RESOURCE_SETTINGS
					|| !array_key_exists(CSettingsHelper::AUDITLOG_ENABLED, current($objects)))) {
			return;
		}

		$auditlog = [];
		$table_key = DB::getSchema(self::RESOURCES_TABLE_NAME[$resource])['key'];
		$clock = time();
		$recordsetid = self::getRecordSetId();

		foreach ($objects as $object) {
			$resourceid = $object[$table_key];
			$old_object = ($action == self::ACTION_UPDATE) ? $old_objects[$resourceid] : [];
			$resource_name = self::getResourceName($resource, $action, $object, $old_object);

			$diff = self::handleObjectDiff($resource, $action, $object, $old_object);

			$auditlog[] = [
				'userid' => $userid,
				'username' => $username,
				'clock' => $clock,
				'ip' => substr($ip, 0, 39),
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

	private static function getRecordSetId(): string {
		static $recordsetid = null;

		if ($recordsetid === null) {
			$recordsetid = CCuid::generate();
		}

		return $recordsetid;
	}

	private static function isAuditEnabled(): bool {
		return CSettingsHelper::get(CSettingsHelper::AUDITLOG_ENABLED) == self::AUDITLOG_ENABLE;
	}

	private static function getResourceName(int $resource, int $action, array $object, array $old_object): string {
		$field_name = self::RESOURCES_FIELD_NAME[$resource];
		$resource_name = ($field_name !== null)
			? (($action == self::ACTION_UPDATE)
				? $old_object[$field_name]
				: $object[$field_name])
			: '';

		if (mb_strlen($resource_name) > 255) {
			$resource_name = mb_substr($resource_name, 0, 252).'...';
		}

		return $resource_name;
	}

	private static function handleObjectDiff(int $resource, int $action, array $object, array $old_object): array {
		if (!in_array($action, [self::ACTION_ADD, self::ACTION_UPDATE])) {
			return [];
		}

		$api_name = self::RESOURCES_API_NAME[$resource];
		$object = self::convertKeysToPaths($api_name, $object);

		switch ($action) {
			case self::ACTION_ADD:
				return self::handleAdd($object);

			case self::ACTION_UPDATE:
				$old_object = self::convertKeysToPaths($api_name, $old_object);
				return self::handleUpdate($object, $old_object);
			default:
				return [];
		}
	}

	private static function isValueToMask(string $path, array $object): bool {
		$index = array_search($path, array_column(self::MASKED_PATHS, 'path'));

		if ($index === false) {
			return false;
		}

		if (!array_key_exists('conditions', self::MASKED_PATHS[$index])) {
			return true;
		}

		$all_counditions = count(self::MASKED_PATHS[$index]['conditions']);
		$true_conditions = 0;

		foreach (self::MASKED_PATHS[$index]['conditions'] as $condition_path => $value) {
			if (array_key_exists($condition_path, $object) && $object[$condition_path] == $value) {
				$true_conditions++;
			}
		}

		return ($true_conditions == $all_counditions);
	}

	private static function convertKeysToPaths(string $prefix, array $object): array {
		$result = [];

		foreach ($object as $key => $value) {
			$index = is_numeric($key) ? '['.$key.']' : '.'.$key;

			if (is_array($value)) {
				$new_prefix = $prefix . $index;
				$result += self::convertKeysToPaths($new_prefix, $value);
			}
			else {
				$result[$prefix.$index] = (string) $value;
			}
		}

		return $result;
	}

	private static function isDefaultValue(string $path, string $value): bool {
		$object_path = self::getLastObjectPath($path);

		if (substr($object_path, -1) === ']') {
			$pos = strrpos($object_path, '[');
			$object_path = substr($object_path, 0, $pos);
		}

		$schema_fields = DB::getSchema(self::PATHS_TABLE_NAME[$object_path])['fields'];
		$field_name = substr($path, strrpos($path, '.') + 1);

		if (!array_key_exists($field_name, $schema_fields)) {
			return false;
		}

		if (!array_key_exists('default', $schema_fields[$field_name])) {
			return false;
		}

		return $value == $schema_fields[$field_name]['default'];
	}

	private static function isNestedObjectProperty(string $path): bool {
		return (count(explode('.', $path)) > 2);
	}

	private static function getLastObjectPath(string $path): string {
		return substr($path, 0, strrpos($path, '.'));
	}

	private static function handleAdd(array $object): array {
		$nested_objects = [];

		foreach ($object as $path => &$value) {
			if (self::isNestedObjectProperty($path)) {
				$nested_objects[self::getLastObjectPath($path)] = true;
			}

			if (self::isDefaultValue($path, $value)) {
				unset($object[$path]);
				continue;
			}

			if (self::isValueToMask($path, $object)) {
				$value = [self::DETAILS_ACTION_ADD, ZBX_SECRET_MASK];
			}
			else {
				$value = [self::DETAILS_ACTION_ADD, $value];
			}
		}
		unset($value);

		foreach (array_keys($nested_objects) as $path) {
			$object[$path] = [self::DETAILS_ACTION_ADD];
		}

		return $object;
	}

	private static function handleUpdate(array $object, array $old_object): array {
		$result = [];
		$nested_objects_actions = [];

		$full_object = $object + $old_object;

		foreach (array_keys($full_object) as $path) {
			$value = array_key_exists($path, $object) ? $object[$path] : null;
			$old_value = array_key_exists($path, $old_object) ? $old_object[$path] : null;

			if ($value === null) {
				if (self::isNestedObjectProperty($path)) {
					$nested_objects_actions[self::getLastObjectPath($path)] = self::DETAILS_ACTION_DELETE;
				}
			}
			else if ($old_value === null) {
				if (self::isNestedObjectProperty($path)) {
					$nested_objects_actions[self::getLastObjectPath($path)] = self::DETAILS_ACTION_ADD;
				}

				if (self::isValueToMask($path, $object)) {
					$result[$path] = [self::DETAILS_ACTION_ADD, ZBX_SECRET_MASK];
				}
				else {
					$result[$path] = [self::DETAILS_ACTION_ADD, $value];
				}
			}
			else {
				if (self::isNestedObjectProperty($path)) {
					$nested_objects_actions[self::getLastObjectPath($path)] = self::DETAILS_ACTION_UPDATE;
				}

				if (self::isValueToMask($path, $full_object)) {
					$result[$path] = [self::DETAILS_ACTION_UPDATE, ZBX_SECRET_MASK, ZBX_SECRET_MASK];
				} elseif ($value != $old_value) {
					$result[$path] = [self::DETAILS_ACTION_UPDATE, $value, $old_value];
				}
			}
		}

		$result_paths = array_keys($result);

		foreach ($nested_objects_actions as $path => $action) {
			if ($action === self::DETAILS_ACTION_UPDATE) {
				if (preg_grep('/'.preg_quote($path).'/', $result_paths)) {
					$result[$path] = [$action];
				}
			}
			else {
				$result[$path] = [$action];
			}
		}

		return $result;
	}
}

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

	private static $masked_fields = [
		self::RESOURCE_AUTH_TOKEN => [
			'fields' => ['token' => true]
		],
		self::RESOURCE_USER => [
			'fields' => ['passwd' => true]
		]
	];

	public static function log(string $userid, string $ip, string $username, int $resource, int $action, array $objects,
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
		if (!in_array($action, [self::ACTION_ADD, self::ACTION_UPDATE, self::ACTION_EXECUTE])) {
			return [];
		}

		$api_name = self::RESOURCES_API_NAME[$resource];

		$object = self::maskValues($resource, $object);

		$object = self::convertKeysToDetailsFormat($api_name, $object);

		switch ($action) {
			case self::ACTION_ADD:
				return self::handleAdd($object, $resource);

			case self::ACTION_UPDATE:
				$old_object = self::convertKeysToDetailsFormat($api_name, self::maskValues($resource, $old_object));

				return self::handleUpdate($object, $old_object, $resource);
		}

		return [];
	}

	private static function isMaskField(int $resource, array $object, string $key): bool {
		$table_masked_fields = [];

		if (!array_key_exists($resource, self::$masked_fields)) {
			return false;
		}

		$table_masked_fields = self::$masked_fields[$resource]['fields'];

		if (!array_key_exists('conditions', self::$masked_fields[$resource])) {
			return array_key_exists($key, $table_masked_fields);
		}

		if (array_key_exists($key, $table_masked_fields)) {
			if (!array_key_exists('conditions', self::$masked_fields[$resource])) {
				return true;
			}

			foreach (self::$masked_fields[$resource]['conditions'] as $field_name => $value) {
				if (array_key_exists($field_name, $object) && $object[$field_name] == $value) {
					return true;
				}
			}
		}

		return false;
	}

	private static function maskValues(int $resource, array $object): array {
		foreach ($object as $key => &$value) {
			$value = self::isMaskField($resource, $object, $key) ? ZBX_SECRET_MASK : $value;
		}
		unset($value);

		return $object;
	}

	private static function convertKeysToDetailsFormat(string $prefix, array $object): array {
		$result = [];

		foreach ($object as $key => $value) {
			$index = is_numeric($key) ? '['.$key.']' : '.'.$key;

			if (is_array($value)) {
				$new_prefix = $prefix . $index;

				if (is_numeric($key)) {
					// Add object marker key.
					$result[$new_prefix] = '';
				}

				$result += self::convertKeysToDetailsFormat($new_prefix, $value);
			}
			else {
				$result[$prefix.$index] = (string) $value;
			}
		}

		return $result;
	}

	private static function isDefaultValue(int $resource, string $path, string $value): bool {
		$schema_fields = DB::getSchema(self::RESOURCES_TABLE_NAME[$resource])['fields'];
		$keys = explode('.', $path);
		$key = end($keys);

		if (!array_key_exists($key, $schema_fields)) {
			return false;
		}

		if (!array_key_exists('default', $schema_fields[$key])) {
			return false;
		}

		return $value == $schema_fields[$key]['default'];
	}

	private static function isPathMasked(int $resource, string $path, ?string $value): bool {
		$path = explode('.', $path);

		if (!array_key_exists($resource, self::$masked_fields)) {
			return false;
		}

		if (!array_key_exists($path[1], self::$masked_fields[$resource]['fields'])) {
			return false;
		}

		return $value === ZBX_SECRET_MASK;
	}

	private static function getObjectMarkers(array $keys): array {
		$object_keys = [];

		foreach ($keys as $key) {
			$pos = strrpos($key, ']');

			if ($pos === false) {
				continue;
			}

			$key = substr($key, 0, $pos + 1);

			if(array_key_exists($key, $object_keys)) {
				$object_keys[substr($key, 0, $pos + 1)]++;
			}
			else {
				$object_keys[$key] = 1;
			}
		}

		return $object_keys;
	}

	private static function handleAdd(array $object, int $resource): array {
		$object_markers = self::getObjectMarkers(array_keys($object));

		foreach ($object as $key => &$value) {
			$result = [];
			$path = preg_replace('/\[[0-9]+\]/', '', $key);
			$is_object_marker = (array_key_exists($key, $object_markers) && $object_markers[$key] > 1);

			if (self::isDefaultValue($resource, $path, $value)) {
				unset($object[$key]);
				continue;
			}

			$result = [self::DETAILS_ACTION_ADD];

			if (!$is_object_marker) {
				$result[] = $value;
			}

			$value = $result;
		}
		unset($value);

		return $object;
	}

	private static function handleUpdate(array $object, array $old_object, int $resource): array {
		$result = [];
		$diff_keys = array_keys(array_merge($object, $old_object));
		$object_markers = self::getObjectMarkers($diff_keys);

		// $object += $old_object;

		foreach ($diff_keys as $key) {
			$value = array_key_exists($key, $object) ? $object[$key] : null;
			$old_value = array_key_exists($key, $old_object) ? $old_object[$key] : null;

			$path = preg_replace('/\[[0-9]+\]/', '', $key);
			// Non associative arrays should not be detected as object marker.
			$is_object_marker = (array_key_exists($key, $object_markers) && $object_markers[$key] > 1);
			$is_value_masked = self::isPathMasked($resource, $path, $value);

			if ($value === null) {
				if ($is_object_marker) {
					$result[$key] = [self::DETAILS_ACTION_DELETE];
				}
			}
			elseif ($old_value === null) {
				$result[$key] = [self::DETAILS_ACTION_ADD];

				if (!$is_object_marker) {
					$result[$key][] = $value;
				}
			}
			else {
				if ($is_object_marker) {
					$result[$key] = [self::DETAILS_ACTION_UPDATE];
				}
				elseif ($value != $old_value || $is_value_masked) {
					$result[$key] = [self::DETAILS_ACTION_UPDATE, $value, $old_value];
				}
			}
		}

		return $result;
	}
}

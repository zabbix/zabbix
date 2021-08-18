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
	public const RESOURCE_REGEXP = 28; // FIXME: add to resource name consts
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
	public const RESOURCE_USER_ROLE = 44; // FIXME: add to resource name consts
	public const RESOURCE_AUTH_TOKEN = 45;
	public const RESOURCE_SCHEDULED_REPORT = 46;

	public const METHOD_ADD = 'add';
	public const METHOD_UPDATE = 'update';
	public const METHOD_ATTACH = 'attach';
	public const METHOD_DETACH = 'detach';
	public const METHOD_DELETE = 'delete';

	private const AUDITLOG_ENABLE = 1;

	private const RESOURCES_TABLE_NAME = [ // FIXME: convert from const to static array
		self::RESOURCE_ACTION => 'actions',
		self::RESOURCE_AUTHENTICATION => 'config',
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_AUTOREGISTRATION => 'config',
		self::RESOURCE_CORRELATION => 'correlation',
		self::RESOURCE_DASHBOARD => 'dashboard',
		self::RESOURCE_DISCOVERY_RULE => 'drules',
		self::RESOURCE_GRAPH => 'graphs',
		self::RESOURCE_GRAPH_PROTOTYPE => 'graphs',
		self::RESOURCE_HOST => 'hosts',
		self::RESOURCE_HOST_GROUP => 'groups',
		self::RESOURCE_HOST_PROTOTYPE => 'hosts',
		self::RESOURCE_HOUSEKEEPING => 'config',
		self::RESOURCE_ICON_MAP => 'icon_map',
		self::RESOURCE_IMAGE => 'images',
		self::RESOURCE_IT_SERVICE => 'services',
		self::RESOURCE_ITEM => 'items',
		self::RESOURCE_ITEM_PROTOTYPE => 'items',
		self::RESOURCE_MACRO => 'globalmacro',
		self::RESOURCE_MAINTENANCE => 'maintenances',
		self::RESOURCE_MAP => 'sysmaps',
		self::RESOURCE_MEDIA_TYPE => 'media_type',
		self::RESOURCE_MODULE => 'module',
		self::RESOURCE_PROXY => 'hosts',
		self::RESOURCE_SCENARIO => 'httptest',
		self::RESOURCE_SCHEDULED_REPORT => 'report',
		self::RESOURCE_SCRIPT => 'scripts',
		self::RESOURCE_SETTINGS => 'config',
		self::RESOURCE_TEMPLATE => 'hosts',
		self::RESOURCE_TEMPLATE_DASHBOARD => 'dashboard',
		self::RESOURCE_TRIGGER => 'triggers',
		self::RESOURCE_TRIGGER_PROTOTYPE => 'triggers',
		self::RESOURCE_USER => 'users',
		self::RESOURCE_USER_GROUP => 'usrgrp',
		self::RESOURCE_VALUE_MAP => 'valuemaps'
	];

	private const RESOURCES_FIELD_NAME = [
		self::RESOURCE_ACTION => 'name',
		self::RESOURCE_AUTHENTICATION => null,
		self::RESOURCE_AUTH_TOKEN => 'name',
		self::RESOURCE_AUTOREGISTRATION => null,
		self::RESOURCE_CORRELATION => 'name',
		self::RESOURCE_DASHBOARD => 'name',
		self::RESOURCE_DISCOVERY_RULE => 'name',
		self::RESOURCE_GRAPH => 'name',
		self::RESOURCE_GRAPH_PROTOTYPE => 'name',
		self::RESOURCE_HOST => 'name',
		self::RESOURCE_HOST_GROUP => 'name',
		self::RESOURCE_HOST_PROTOTYPE => 'host',
		self::RESOURCE_HOUSEKEEPING => null,
		self::RESOURCE_ICON_MAP => 'name',
		self::RESOURCE_IMAGE => 'name',
		self::RESOURCE_IT_SERVICE => 'name',
		self::RESOURCE_ITEM => 'name',
		self::RESOURCE_ITEM_PROTOTYPE => 'name',
		self::RESOURCE_MACRO => 'macro',
		self::RESOURCE_MAINTENANCE => 'name',
		self::RESOURCE_MAP => 'name',
		self::RESOURCE_MEDIA_TYPE => 'name',
		self::RESOURCE_MODULE => 'id',
		self::RESOURCE_PROXY => 'host',
		self::RESOURCE_SCENARIO => 'name',
		self::RESOURCE_SCHEDULED_REPORT => 'name',
		self::RESOURCE_SCRIPT => 'name',
		self::RESOURCE_SETTINGS => null,
		self::RESOURCE_TEMPLATE => 'name',
		self::RESOURCE_TEMPLATE_DASHBOARD => 'name',
		self::RESOURCE_TRIGGER => 'description',
		self::RESOURCE_TRIGGER_PROTOTYPE => 'description',
		self::RESOURCE_USER => 'username',
		self::RESOURCE_USER_GROUP => 'name',
		self::RESOURCE_VALUE_MAP => 'name'
	];

	private const RESOURCES_API_NAME = [
		self::RESOURCE_ACTION => 'action',
		self::RESOURCE_AUTHENTICATION => 'authentication',
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_AUTOREGISTRATION => 'autoregistration',
		self::RESOURCE_CORRELATION => 'correlation',
		self::RESOURCE_DASHBOARD => 'dashboard',
		self::RESOURCE_DISCOVERY_RULE => 'drule',
		self::RESOURCE_GRAPH => 'graph',
		self::RESOURCE_GRAPH_PROTOTYPE => 'graphprototype',
		self::RESOURCE_HOST => 'host',
		self::RESOURCE_HOST_GROUP => 'hostgroup',
		self::RESOURCE_HOST_PROTOTYPE => 'hostprototype',
		self::RESOURCE_HOUSEKEEPING => 'housekeeping',
		self::RESOURCE_ICON_MAP => 'iconmap',
		self::RESOURCE_IMAGE => 'image',
		self::RESOURCE_IT_SERVICE => 'service',
		self::RESOURCE_ITEM => 'item',
		self::RESOURCE_ITEM_PROTOTYPE => 'itemprototype',
		self::RESOURCE_MACRO => 'usermacro',
		self::RESOURCE_MAINTENANCE => 'maintenance',
		self::RESOURCE_MAP => 'map',
		self::RESOURCE_MEDIA_TYPE => 'mediatype',
		self::RESOURCE_MODULE => 'module',
		self::RESOURCE_PROXY => 'proxy',
		self::RESOURCE_SCENARIO => 'httptest',
		self::RESOURCE_SCHEDULED_REPORT => 'report',
		self::RESOURCE_SCRIPT => 'script',
		self::RESOURCE_SETTINGS => 'settings',
		self::RESOURCE_TEMPLATE => 'template',
		self::RESOURCE_TEMPLATE_DASHBOARD => 'templatedashboard',
		self::RESOURCE_TRIGGER => 'trigger',
		self::RESOURCE_TRIGGER_PROTOTYPE => 'triggerprototype',
		self::RESOURCE_USER => 'user',
		self::RESOURCE_USER_GROUP => 'usergroup',
		self::RESOURCE_VALUE_MAP => 'valuemap'
	];

	private static $masked_fields = [
		self::RESOURCE_MACRO => [
			'fields' => ['value' => true],
			'conditions' => ['type' => ZBX_MACRO_TYPE_SECRET]
		],
		self::RESOURCE_HOST => [
			'fields' => ['tls_psk_identity' => true, 'tls_psk' => true]
		],
		self::RESOURCE_TEMPLATE => [
			'fields' => ['tls_psk_identity' => true, 'tls_psk' => true]
		],
		self::RESOURCE_MEDIA_TYPE => [
			'fields' => ['passwd' => true]
		],
		self::RESOURCE_AUTH_TOKEN => [
			'fields' => ['token' => true]
		],
		self::RESOURCE_USER => [
			'fields' => ['passwd' => true]
		]
	];

	private static $relatable_id_mapping = [
		'user.usrgrps' => 'usrgrpid'
	];

	private static $relatable_object = [
		self::RESOURCE_USER => ['user.usrgrps']
	];

	private static $updatable_object = [
		self::RESOURCE_USER => ['user.medias']
	];

	public static function log(int $resource, int $action, array $objects, ?array $old_objects): void {
		if (!self::isAuditEnabled() && ($resource != self::RESOURCE_SETTINGS
					|| !array_key_exists(CSettingsHelper::AUDITLOG_ENABLED, current($objects)))) {
			return;
		}

		$auditlog = [];
		$table_key = DB::getSchema(self::RESOURCES_TABLE_NAME[$resource])['key'];
		$user_data = CApiService::$userData;
		$clock = time();
		$ip = substr($user_data['userip'], 0, 39);
		$recordsetid = self::getRecordSetId();

		foreach ($objects as $object) {
			$resourceid = $object[$table_key];
			$old_object = ($action == self::ACTION_UPDATE) ? $old_objects[$resourceid] : [];
			$resource_name = self::getResourceName($resource, $action, $object, $old_object);

			$diff = self::handleObjectDiff($resource, $action, $object, $old_object);

			$auditlog[] = [
				'userid' => $user_data['userid'],
				'username' => $user_data['username'],
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
		if (in_array($action, [self::ACTION_DELETE, self::ACTION_LOGIN, self::ACTION_LOGOUT])) {
			return [];
		}

		$api_name = self::RESOURCES_API_NAME[$resource];

		$object = self::maskValues($resource, $object);

		$dot_object = self::dotArrayConverter($api_name, $object);

		switch ($action) {
			case CAudit::ACTION_ADD:
				return self::handleAdd($dot_object, $resource);
			case CAudit::ACTION_UPDATE:
				$dot_old_object = self::dotArrayConverter($api_name, self::maskValues($resource, $old_object));

				return self::handleUpdate($dot_object, $dot_old_object, $resource);
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

	private static function dotArrayConverter(string $prefix, array $object): array {
		$result = [];

		foreach ($object as $key => $value) {
			$index = is_numeric($key) ? '['.$key.']' : '.'.$key;

			if (array_key_exists($prefix, self::$relatable_id_mapping)) {
				$index = '['.$value[self::$relatable_id_mapping[$prefix]].']';
			}

			if (is_array($value)) {
				$new_prefix = $prefix . $index;

				if (is_numeric($key)) {
					// Add object marker key.
					$result[$new_prefix] = '';
				}

				$result += self::dotArrayConverter($new_prefix, $value);
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

	private static function isObjectRelatable(int $resource, string $path): bool {
		if (!array_key_exists($resource, self::$relatable_object)) {
			return false;
		}

		$relatable_objects = self::$relatable_object[$resource];

		foreach ($relatable_objects as $relatable_key) {
			if (strpos($path, $relatable_key) === 0) {
				return true;
			}
		}

		return false;
	}

	private static function isObjectUpdatable(int $resource, string $path): bool {
		if (!array_key_exists($resource, self::$updatable_object)) {
			return false;
		}

		$updatable_objects = self::$updatable_object[$resource];

		foreach ($updatable_objects as $updatable_key) {
			if (strpos($path, $updatable_key) === 0) {
				return true;
			}
		}

		return false;
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
		$object_keys = self::getObjectMarkers(array_keys($object));

		foreach ($object as $key => &$value) {
			$result = [];
			$path = preg_replace('/\[[0-9]+\]/', '', $key);
			$is_object_marker = (array_key_exists($key, $object_keys) && $object_keys[$key] > 1);
			$is_relatable = self::isObjectRelatable($resource, $path);

			if (self::isDefaultValue($resource, $path, $value)) {
				unset($object[$key]);
				continue;
			}

			$result = [self::METHOD_ADD];

			if ($is_relatable) {
				$result = [self::METHOD_ATTACH];
			}

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
		$object_keys = self::getObjectMarkers($diff_keys);

		$object += $old_object;

		foreach ($diff_keys as $key) {
			$value = array_key_exists($key, $object) ? $object[$key] : null;
			$old_value = array_key_exists($key, $old_object) ? $old_object[$key] : null;

			$path = preg_replace('/\[[0-9]+\]/', '', $key);
			// Non associative arrays should not be detected as object marker.
			$is_object_marker = (array_key_exists($key, $object_keys) && $object_keys[$key] > 1);
			$is_value_masked = self::isPathMasked($resource, $path, $value);
			$is_relatable = self::isObjectRelatable($resource, $path);
			$is_updatable = self::isObjectUpdatable($resource, $path);

			if (!$is_relatable && !$is_updatable && $value === null) {
				continue;
			}

			if ($value === null) {
				if ($is_updatable && $is_object_marker) {
					$result[$key] = [self::METHOD_DELETE];
				}
				elseif ($is_updatable) {
					continue;
				}

				if ($is_relatable) {
					$result[$key] = [self::METHOD_DETACH];
				}

				if (!$is_object_marker) {
					$result[$key][] = $old_value;
				}
			}
			elseif ($old_value === null) {
				$result[$key] = [self::METHOD_ADD];

				if ($is_relatable) {
					$result[$key] = [self::METHOD_ATTACH];
				}

				if (!$is_object_marker) {
					$result[$key][] = $value;
				}
			}
			else {
				if ($is_object_marker && $is_updatable) {
					$result[$key] = [self::METHOD_UPDATE];
				}
				elseif ($value != $old_value || $is_value_masked) {
					$result[$key] = [self::METHOD_UPDATE, $value, $old_value];
				}
			}
		}

		return $result;
	}
}

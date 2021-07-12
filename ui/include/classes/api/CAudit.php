<?php
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
	/**
	 * Supported resources list, every record contains:
	 * resource id field name
	 * resource name field name
	 * resource table name
	 */
	static private $supported_type = [
		AUDIT_RESOURCE_ACTION => 				['actionid', 'name', 'actions'],
		AUDIT_RESOURCE_AUTHENTICATION =>		['configid', null, 'config'],
		AUDIT_RESOURCE_AUTH_TOKEN =>			['tokenid', 'name', 'token'],
		AUDIT_RESOURCE_AUTOREGISTRATION =>		['configid', null, 'config'],
		AUDIT_RESOURCE_CORRELATION =>			['correlationid', 'name', 'correlation'],
		AUDIT_RESOURCE_DASHBOARD =>				['dashboardid', 'name', 'dashboard'],
		AUDIT_RESOURCE_DISCOVERY_RULE =>		['druleid', 'name', 'drules'],
		AUDIT_RESOURCE_GRAPH =>					['graphid', 'name', 'graphs'],
		AUDIT_RESOURCE_GRAPH_PROTOTYPE =>		['graphid', 'name', 'graphs'],
		AUDIT_RESOURCE_HOST =>					['hostid', 'name', 'hosts'],
		AUDIT_RESOURCE_HOST_GROUP =>			['groupid', 'name', 'groups'],
		AUDIT_RESOURCE_HOST_PROTOTYPE =>		['hostid', 'host', 'hosts'],
		AUDIT_RESOURCE_HOUSEKEEPING =>			['configid', null, 'config'],
		AUDIT_RESOURCE_ICON_MAP =>				['iconmapid', 'name', 'icon_map'],
		AUDIT_RESOURCE_ITEM =>					['itemid', 'name', 'items'],
		AUDIT_RESOURCE_ITEM_PROTOTYPE =>		['itemid', 'name', 'items'],
		AUDIT_RESOURCE_MACRO =>					['globalmacroid', 'macro', 'globalmacro'],
		AUDIT_RESOURCE_MAINTENANCE =>			['maintenanceid', 'name', 'maintenances'],
		AUDIT_RESOURCE_MEDIA_TYPE =>			['mediatypeid', 'name', 'media_type'],
		AUDIT_RESOURCE_MODULE =>				['moduleid', 'id', 'module'],
		AUDIT_RESOURCE_PROXY =>					['proxyid', 'host', 'hosts'],
		AUDIT_RESOURCE_SCENARIO =>				['httptestid', 'name', 'httptest'],
		AUDIT_RESOURCE_SCHEDULED_REPORT =>		['reportid', 'name', 'report'],
		AUDIT_RESOURCE_SCRIPT =>				['scriptid', 'name', 'scripts'],
		AUDIT_RESOURCE_SETTINGS =>				['configid', null, 'config'],
		AUDIT_RESOURCE_TEMPLATE =>				['templateid', 'name', 'hosts'],
		AUDIT_RESOURCE_TRIGGER =>				['triggerid', 'description', 'triggers'],
		AUDIT_RESOURCE_TRIGGER_PROTOTYPE =>		['triggerid', 'description', 'triggers'],
		AUDIT_RESOURCE_USER =>					['userid', 'username', 'users'],
		AUDIT_RESOURCE_USER_GROUP =>			['usrgrpid', 'name', 'usrgrp'],
		AUDIT_RESOURCE_VALUE_MAP =>				['valuemapid', 'name', 'valuemaps'],
		AUDIT_RESOURCE_TEMPLATE_DASHBOARD =>	['dashboardid', 'name', 'dashboard']
	];

	static private $masked_fields = [
		'config' => [
			'fields' => ['tls_psk_identity' => true, 'tls_psk' => true]
		],
		'globalmacro' => [
			'fields' => ['value' => true],
			'conditions' => ['type' => ZBX_MACRO_TYPE_SECRET]
		],
		'hosts' => [
			'fields' => ['tls_psk_identity' => true, 'tls_psk' => true]
		],
		'media_type' => [
			'fields' => ['passwd' => true]
		],
		'token' => [
			'fields' => ['token' => true]
		],
		'users' => [
			'fields' => ['passwd' => true]
		]
	];

	/**
	 * Add simple audit record.
	 *
	 * @param string $userid
	 * @param string $ip
	 * @param int    $action        AUDIT_ACTION_*
	 * @param int    $resourcetype  AUDIT_RESOURCE_*
	 * @param string $note
	 */
	/* static public function addDetails($userid, $ip, $action, $resourcetype, $note = '') {
		DB::insert('auditlog', [[
			'userid' => $userid,
			'clock' => time(),
			'ip' => substr($ip, 0, 39),
			'action' => $action,
			'resourcetype' => $resourcetype,
			'note' => $note
		]]);
	} */

	static private function getDetailsArrayRecursive(array $new_object, array $old_object, string $parent_key = '',
			int $resourcetype): array {
		$array = [];
		$i = 0;
		foreach ($new_object as $key => $value) {
			if (!array_key_exists($key, $old_object)) {
				continue;
			}

			$old_value = $old_object[$key];
			$current_key = $parent_key !== '' ? $parent_key.'.'.$key : $key;

			if (is_array($value)) {
				$current_key .= '['.$i.']';
				$array[$current_key] = self::getDetailsArrayRecursive($value, $old_value, $current_key, $resourcetype);
				$i++;
			}
			else {
				if ($value == $old_value) {
					continue;
				}


				$array[$current_key] = ['update', self::sanitizeValue($value, $new_object, $key, $resourcetype),
					self::sanitizeValue($old_value, $old_object, $key, $resourcetype)
				];
			}
		}

		return $array;
	}

	static private function isSanitizeField(array $object, string $key, int $resourcetype): bool {
		$table_masked_fields = [];
		list(, , $table_name) = self::$supported_type[$resourcetype];

		if (!array_key_exists($table_name, self::$masked_fields)) {
			return false;
		}

		$table_masked_fields = self::$masked_fields[$table_name]['fields'];

		if (!array_key_exists('conditions', self::$masked_fields[$table_name])) {
			return array_key_exists($key, $table_masked_fields);
		}

		if (array_key_exists($key, $table_masked_fields)) {
			if (!array_key_exists('conditions', self::$masked_fields[$table_name])) {
				return true;
			}

			foreach (self::$masked_fields[$table_name]['conditions'] as $field_name => $value) {
				if (array_key_exists($field_name, $object) && $object[$field_name] == $value) {
					return true;
				}
			}
		}

		return false;
	}

	static private function sanitizeValue(string $value, array $object, string $key, int $resourcetype): string {
		return self::isSanitizeField($object, $key, $resourcetype) ? ZBX_SECRET_MASK : $value;
	}

	/**
	 * Add audit records.
	 *
	 * @param string $userid
	 * @param string $ip
	 * @param int    $action        AUDIT_ACTION_*
	 * @param int    $resourcetype  AUDIT_RESOURCE_*
	 * @param array  $objects
	 * @param array  $objects_old
	 */
	static public function addBulk($userid, $ip, $action, $resourcetype, array $objects, array $objects_old = null) {
		if (!array_key_exists($resourcetype, self::$supported_type)) {
			return;
		}

		if (CSettingsHelper::get(CSettingsHelper::AUDIT_LOGGING_ENABLED) != 1) {
			return;
		}

		$auditid = generateCuid();
		$recordsetid = generateCuid();

		list($field_name_resourceid, $field_name_resourcename) = self::$supported_type[$resourcetype];

		$clock = time();
		$ip = substr($ip, 0, 39);

		$auditlog = [];

		foreach ($objects as $object) {
			$resourceid = $object[$field_name_resourceid];
			$diff = [];

			if ($action == AUDIT_ACTION_UPDATE) {
				$object_old = $objects_old[$resourceid];

				$diff[] = self::getDetailsArrayRecursive($object, $object_old, '', $resourcetype);

				$resourcename = ($field_name_resourcename !== null) ? $object_old[$field_name_resourcename] : '';
			}
			else {
				$resourcename = ($field_name_resourcename !== null) ? $object[$field_name_resourcename] : '';
			}

			if (mb_strlen($resourcename) > 255) {
				$resourcename = mb_substr($resourcename, 0, 252).'...';
			}

			$auditlog[] = [
				'auditid' => $auditid,
				'userid' => $userid,
				'clock' => $clock,
				'ip' => $ip,
				'action' => $action,
				'resourcetype' => $resourcetype,
				'resourceid' => $resourceid,
				'resourcename' => $resourcename,
				'recordsetid' => $recordsetid,
				'details' => json_encode($diff)
			];
		}

		if ($auditlog) {
			DB::insertBatch('auditlog', $auditlog, false);
		}
	}
}

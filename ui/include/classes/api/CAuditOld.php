<?php
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


class CAuditOld {
	private const AUDITLOG_ENABLE = 1;

	/**
	 * Supported resources list, every record contains:
	 * resource id field name
	 * resource name field name
	 * resource table name
	 * resource API name
	 */
	private static $supported_type = [
		CAudit::RESOURCE_ACTION => 				['actionid', 'name', 'actions', 'action'],
		CAudit::RESOURCE_AUTHENTICATION =>		['configid', null, 'config', 'authentication'],
		CAudit::RESOURCE_AUTH_TOKEN =>			['tokenid', 'name', 'token', 'token'],
		CAudit::RESOURCE_AUTOREGISTRATION =>	['configid', null, 'config', 'autoregistration'],
		CAudit::RESOURCE_CORRELATION =>			['correlationid', 'name', 'correlation', 'correlation'],
		CAudit::RESOURCE_DASHBOARD =>			['dashboardid', 'name', 'dashboard', 'dashboard'],
		CAudit::RESOURCE_DISCOVERY_RULE =>		['druleid', 'name', 'drules', 'drule'],
		CAudit::RESOURCE_GRAPH =>				['graphid', 'name', 'graphs', 'graph'],
		CAudit::RESOURCE_GRAPH_PROTOTYPE =>		['graphid', 'name', 'graphs', 'graphprototype'],
		CAudit::RESOURCE_HOST =>				['hostid', 'name', 'hosts', 'host'],
		CAudit::RESOURCE_HOST_GROUP =>			['groupid', 'name', 'groups', 'hostgroup'],
		CAudit::RESOURCE_HOST_PROTOTYPE =>		['hostid', 'host', 'hosts', 'hostprototype'],
		CAudit::RESOURCE_HOUSEKEEPING =>		['configid', null, 'config', 'housekeeping'],
		CAudit::RESOURCE_ICON_MAP =>			['iconmapid', 'name', 'icon_map', 'iconmap'],
		CAudit::RESOURCE_IMAGE =>				['imageid', 'name', 'images', 'image'],
		CAudit::RESOURCE_IT_SERVICE =>			['serviceid', 'name', 'services', 'service'],
		CAudit::RESOURCE_ITEM =>				['itemid', 'name', 'items', 'item'],
		CAudit::RESOURCE_ITEM_PROTOTYPE =>		['itemid', 'name', 'items', 'itemprototype'],
		CAudit::RESOURCE_MACRO =>				['globalmacroid', 'macro', 'globalmacro', 'usermacro'],
		CAudit::RESOURCE_MAINTENANCE =>			['maintenanceid', 'name', 'maintenances', 'maintenance'],
		CAudit::RESOURCE_MAP =>					['sysmapid', 'name', 'sysmaps', 'map'],
		CAudit::RESOURCE_MEDIA_TYPE =>			['mediatypeid', 'name', 'media_type', 'mediatype'],
		CAudit::RESOURCE_MODULE =>				['moduleid', 'id', 'module', 'module'],
		CAudit::RESOURCE_PROXY =>				['proxyid', 'name', 'proxy', 'proxy'],
		CAudit::RESOURCE_SCENARIO =>			['httptestid', 'name', 'httptest', 'httptest'],
		CAudit::RESOURCE_SCHEDULED_REPORT =>	['reportid', 'name', 'report', 'report'],
		CAudit::RESOURCE_SCRIPT =>				['scriptid', 'name', 'scripts', 'script'],
		CAudit::RESOURCE_SETTINGS =>			['configid', null, 'config', 'settings'],
		CAudit::RESOURCE_TEMPLATE =>			['templateid', 'name', 'hosts', 'template'],
		CAudit::RESOURCE_TRIGGER =>				['triggerid', 'description', 'triggers', 'trigger'],
		CAudit::RESOURCE_TRIGGER_PROTOTYPE =>	['triggerid', 'description', 'triggers', 'triggerprototype'],
		CAudit::RESOURCE_USER =>				['userid', 'username', 'users', 'user'],
		CAudit::RESOURCE_USER_GROUP =>			['usrgrpid', 'name', 'usrgrp', 'usergroup'],
		CAudit::RESOURCE_USER_ROLE =>			['roleid', 'name', 'role', 'role'],
		CAudit::RESOURCE_VALUE_MAP =>			['valuemapid', 'name', 'valuemaps', 'valuemap'],
		CAudit::RESOURCE_TEMPLATE_DASHBOARD =>	['dashboardid', 'name', 'dashboard', 'templatedashboard']
	];

	private static $masked_fields = [
		'config' => [
			'fields' => ['tls_psk_identity' => true, 'tls_psk' => true]
		],
		'globalmacro' => [
			'fields' => ['value' => true],
			'conditions' => ['type' => ZBX_MACRO_TYPE_SECRET]
		],
		'hosts' => [
			'fields' => ['tls_psk_identity' => true, 'tls_psk' => true, 'ipmi_password' => true]
		],
		'httptest' => [
			'fields' => ['http_password' => true, 'ssl_key_password' => true]
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
	// static public function addDetails($userid, $ip, $action, $resourcetype, $note = '') {
	// 	DB::insert('auditlog', [[
	// 		'userid' => $userid,
	// 		'clock' => time(),
	// 		'ip' => substr($ip, 0, 39),
	// 		'action' => $action,
	// 		'resourcetype' => $resourcetype,
	// 		'note' => $note
	// 	]]);
	// }

	private static function getRecordSetId(): string {
		static $recordsetid = null;

		if ($recordsetid === null) {
			$recordsetid = CCuid::generate();
		}

		return $recordsetid;
	}

	/**
	 * Add audit records.
	 *
	 * @param string $userid
	 * @param string $ip
	 * @param string $username
	 * @param int    $action        CAudit::ACTION_*
	 * @param int    $resourcetype  CAudit::RESOURCE_*
	 * @param array  $objects
	 * @param array  $objects_old
	 */
	public static function addBulk(string $userid, string $ip, string $username, int $action, int $resourcetype,
			array $objects, ?array $objects_old = null) {
		if (!array_key_exists($resourcetype, self::$supported_type)) {
			return;
		}

		if (CSettingsHelper::get(CSettingsHelper::AUDITLOG_ENABLED) != self::AUDITLOG_ENABLE
				&& ($resourcetype != CAudit::RESOURCE_SETTINGS
					|| !array_key_exists(CSettingsHelper::AUDITLOG_ENABLED, current($objects)))) {
			return;
		}

		$recordsetid = self::getRecordSetId();

		[$field_name_resourceid, $field_name_resourcename, $table_name, $api_name] = self::$supported_type[$resourcetype];

		$clock = time();
		$ip = substr($ip, 0, 39);

		$auditlog = [];

		foreach ($objects as $object) {
			$resourceid = $object[$field_name_resourceid];
			$diff = '';

			if ($action == CAudit::ACTION_UPDATE) {
				$object_old = $objects_old[$resourceid];

				/**
				 * Convert two dimension array to one dimension array,
				 * because array_diff and array_intersect work only with one dimension array.
				 */
				$object_old = array_filter($object_old, function ($val) {
					return !is_array($val);
				});
				$object = array_filter($object, function ($val) {
					return !is_array($val);
				});

				$object_diff = array_diff_assoc(array_intersect_key($object_old, $object), $object);

				if (!$object_diff) {
					continue;
				}

				if (array_key_exists($table_name, self::$masked_fields)) {
					$table_masked_fields = self::$masked_fields[$table_name]['fields'];
					$mask_object_old = true;
					$mask_object = true;

					if (array_key_exists('conditions', self::$masked_fields[$table_name])) {
						foreach (self::$masked_fields[$table_name]['conditions'] as $field_name => $value) {
							if ($mask_object_old) {
								$mask_object_old = ($object_old[$field_name] == $value);
							}
							if ($mask_object) {
								$mask_object = array_key_exists($field_name, $object)
									? ($object[$field_name] == $value)
									: ($object_old[$field_name] == $value);
							}
						}
					}
				}
				else {
					$table_masked_fields = [];
					$mask_object_old = false;
					$mask_object = false;
				}

				$details = [];

				foreach (array_keys($object_diff) as $field_name) {
					if (array_key_exists($field_name, $table_masked_fields)) {
						if ($mask_object_old) {
							$object_old[$field_name] = ZBX_SECRET_MASK;
						}
						if ($mask_object) {
							$object[$field_name] = ZBX_SECRET_MASK;
						}
					}

					$details[$api_name.'.'.$field_name] = ['update', $object[$field_name], $object_old[$field_name]];
				}

				$diff = json_encode($details);

				$resourcename = ($field_name_resourcename !== null) ? $object_old[$field_name_resourcename] : '';
			}
			else {
				$resourcename = ($field_name_resourcename !== null) ? $object[$field_name_resourcename] : '';
			}

			if (mb_strlen($resourcename) > 255) {
				$resourcename = mb_substr($resourcename, 0, 252).'...';
			}

			$auditlog[] = [
				'userid' => $userid,
				'username' => $username,
				'clock' => $clock,
				'ip' => $ip,
				'action' => $action,
				'resourcetype' => $resourcetype,
				'resourceid' => $resourceid,
				'resourcename' => $resourcename,
				'recordsetid' => $recordsetid,
				'details' => $diff
			];
		}

		DB::insertBatch('auditlog', $auditlog);
	}
}

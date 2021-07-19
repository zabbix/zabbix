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
	private const AUDITLOG_ENABLE = 1;

	/**
	 * Supported resources list, every record contains:
	 * resource id field name
	 * resource name field name
	 * resource table name
	 * resource API name
	 */
	private static $supported_type = [
		AUDIT_RESOURCE_ACTION => 				['actionid', 'name', 'actions', 'action'],
		AUDIT_RESOURCE_AUTHENTICATION =>		['configid', null, 'config', 'authentication'],
		AUDIT_RESOURCE_AUTH_TOKEN =>			['tokenid', 'name', 'token', 'token'],
		AUDIT_RESOURCE_AUTOREGISTRATION =>		['configid', null, 'config', 'autoregistration'],
		AUDIT_RESOURCE_CORRELATION =>			['correlationid', 'name', 'correlation', 'correlation'],
		AUDIT_RESOURCE_DASHBOARD =>				['dashboardid', 'name', 'dashboard', 'dashboard'],
		AUDIT_RESOURCE_DISCOVERY_RULE =>		['druleid', 'name', 'drules', 'drule'],
		AUDIT_RESOURCE_GRAPH =>					['graphid', 'name', 'graphs', 'graph'],
		AUDIT_RESOURCE_GRAPH_PROTOTYPE =>		['graphid', 'name', 'graphs', 'graphprototype'],
		AUDIT_RESOURCE_HOST =>					['hostid', 'name', 'hosts', 'host'],
		AUDIT_RESOURCE_HOST_GROUP =>			['groupid', 'name', 'groups', 'hostgroup'],
		AUDIT_RESOURCE_HOST_PROTOTYPE =>		['hostid', 'host', 'hosts', 'hostprototype'],
		AUDIT_RESOURCE_HOUSEKEEPING =>			['configid', null, 'config', 'housekeeping'],
		AUDIT_RESOURCE_ICON_MAP =>				['iconmapid', 'name', 'icon_map', 'iconmap'],
		AUDIT_RESOURCE_IMAGE =>					['imageid', 'name', 'images', 'image'],
		AUDIT_RESOURCE_IT_SERVICE =>			['serviceid', 'name', 'services', 'service'],
		AUDIT_RESOURCE_ITEM =>					['itemid', 'name', 'items', 'item'],
		AUDIT_RESOURCE_ITEM_PROTOTYPE =>		['itemid', 'name', 'items', 'itemprototype'],
		AUDIT_RESOURCE_MACRO =>					['globalmacroid', 'macro', 'globalmacro', 'usermacro'],
		AUDIT_RESOURCE_MAINTENANCE =>			['maintenanceid', 'name', 'maintenances', 'maintenance'],
		AUDIT_RESOURCE_MAP =>					['sysmapid', 'name', 'sysmaps', 'map'],
		AUDIT_RESOURCE_MEDIA_TYPE =>			['mediatypeid', 'name', 'media_type', 'mediatype'],
		AUDIT_RESOURCE_MODULE =>				['moduleid', 'id', 'module', 'module'],
		AUDIT_RESOURCE_PROXY =>					['proxyid', 'host', 'hosts', 'proxy'],
		AUDIT_RESOURCE_SCENARIO =>				['httptestid', 'name', 'httptest', 'httptest'],
		AUDIT_RESOURCE_SCHEDULED_REPORT =>		['reportid', 'name', 'report', 'report'],
		AUDIT_RESOURCE_SCRIPT =>				['scriptid', 'name', 'scripts', 'script'],
		AUDIT_RESOURCE_SETTINGS =>				['configid', null, 'config', 'settings'],
		AUDIT_RESOURCE_TEMPLATE =>				['templateid', 'name', 'hosts', 'template'],
		AUDIT_RESOURCE_TRIGGER =>				['triggerid', 'description', 'triggers', 'trigger'],
		AUDIT_RESOURCE_TRIGGER_PROTOTYPE =>		['triggerid', 'description', 'triggers', 'triggerprototype'],
		AUDIT_RESOURCE_USER =>					['userid', 'username', 'users', 'user'],
		AUDIT_RESOURCE_USER_GROUP =>			['usrgrpid', 'name', 'usrgrp', 'usergroup'],
		AUDIT_RESOURCE_VALUE_MAP =>				['valuemapid', 'name', 'valuemaps', 'valuemap'],
		AUDIT_RESOURCE_TEMPLATE_DASHBOARD =>	['dashboardid', 'name', 'dashboard', 'templatedashboard']
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

	/**
	 * Add audit records.
	 *
	 * @param array $userdata      CApiService::$userData
	 * @param int   $action        AUDIT_ACTION_*
	 * @param int   $resourcetype  AUDIT_RESOURCE_*
	 * @param array $objects
	 * @param array $objects_old
	 */
	public static function addBulk(array $userdata, int $action, int $resourcetype, array $objects,
			array $objects_old = null) {
		if (!array_key_exists($resourcetype, self::$supported_type)) {
			return;
		}

		if (CSettingsHelper::get(CSettingsHelper::AUDITLOG_ENABLED) != self::AUDITLOG_ENABLE) {
			return;
		}

		$recordsetid = CCuid::generate();

		[$field_name_resourceid, $field_name_resourcename, $table_name, $api_name] = self::$supported_type[$resourcetype];

		$clock = time();
		$ip = substr($userdata['userip'], 0, 39);

		$auditlog = [];

		foreach ($objects as $object) {
			$resourceid = $object[$field_name_resourceid];
			$diff = '';

			if ($action == AUDIT_ACTION_UPDATE) {
				$details = [];
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

				foreach (array_keys($object_diff) as $path) {
					if (array_key_exists($path, $table_masked_fields)) {
						if ($mask_object_old) {
							$object_old[$path] = ZBX_SECRET_MASK;
						}
						if ($mask_object) {
							$object[$path] = ZBX_SECRET_MASK;
						}
					}

					$details[$api_name.'.'.$path ] = ['update', $object[$path], $object_old[$path]];
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
				'userid' => $userdata['userid'],
				'username' => $userdata['username'],
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

		if ($auditlog) {
			DB::insertBatch('auditlog', $auditlog);
		}
	}
}

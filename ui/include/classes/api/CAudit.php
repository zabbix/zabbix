<?php
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


class CAudit {
	/**
	 * Supported resources list, every record contains:
	 * resource id field name
	 * resource name field name
	 * resource table name
	 */
	static private $supported_type = [
		AUDIT_RESOURCE_ACTION => 				['actionid', 'name', 'actions'],
		AUDIT_RESOURCE_APPLICATION =>			['applicationid', 'name', 'applications'],
		AUDIT_RESOURCE_AUTHENTICATION =>		['configid', null, 'config'],
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
		AUDIT_RESOURCE_MEDIA_TYPE =>			['mediatypeid', 'name', 'media_type'],
		AUDIT_RESOURCE_MODULE =>				['moduleid', 'id', 'module'],
		AUDIT_RESOURCE_PROXY =>					['proxyid', 'host', 'hosts'],
		AUDIT_RESOURCE_SCENARIO =>				['httptestid', 'name', 'httptest'],
		AUDIT_RESOURCE_SCRIPT =>				['scriptid', 'name', 'scripts'],
		AUDIT_RESOURCE_SETTINGS =>				['configid', null, 'config'],
		AUDIT_RESOURCE_TRIGGER =>				['triggerid', 'description', 'triggers'],
		AUDIT_RESOURCE_TRIGGER_PROTOTYPE =>		['triggerid', 'description', 'triggers'],
		AUDIT_RESOURCE_USER =>					['userid', 'alias', 'users'],
		AUDIT_RESOURCE_USER_GROUP =>			['usrgrpid', 'name', 'usrgrp'],
		AUDIT_RESOURCE_VALUE_MAP =>				['valuemapid', 'name', 'valuemaps'],
		AUDIT_RESOURCE_TEMPLATE_DASHBOARD =>	['dashboardid', 'name', 'dashboard']
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
	static public function addDetails($userid, $ip, $action, $resourcetype, $note = '') {
		DB::insert('auditlog', [[
			'userid' => $userid,
			'clock' => time(),
			'ip' => substr($ip, 0, 39),
			'action' => $action,
			'resourcetype' => $resourcetype,
			'note' => $note
		]]);
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
		$masked_fields = [
			'users' => [
				'fields' => ['passwd' => true]
			],
			'config' => [
				'fields' => ['tls_psk_identity' => true, 'tls_psk' => true]
			],
			'media_type' => [
				'fields' => ['passwd' => true]
			],
			'globalmacro' => [
				'fields' => ['value' => true],
				'conditions' => ['type' => ZBX_MACRO_TYPE_SECRET]
			]
		];

		if (!array_key_exists($resourcetype, self::$supported_type)) {
			return;
		}

		list($field_name_resourceid, $field_name_resourcename, $table_name) = self::$supported_type[$resourcetype];

		$clock = time();
		$ip = substr($ip, 0, 39);

		$auditlog = [];
		$objects_diff = [];

		foreach ($objects as $object) {
			$resourceid = $object[$field_name_resourceid];

			if ($action == AUDIT_ACTION_UPDATE) {
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

				if (array_key_exists($table_name, $masked_fields)) {
					$table_masked_fields = $masked_fields[$table_name]['fields'];
					$mask_object_old = true;
					$mask_object = true;

					if (array_key_exists('conditions', $masked_fields[$table_name])) {
						foreach ($masked_fields[$table_name]['conditions'] as $field_name => $value) {
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

				foreach ($object_diff as $field_name => &$values) {
					if (array_key_exists($field_name, $table_masked_fields)) {
						if ($mask_object_old) {
							$object_old[$field_name] = ZBX_SECRET_MASK;
						}
						if ($mask_object) {
							$object[$field_name] = ZBX_SECRET_MASK;
						}
					}

					$values = [
						'old' => $object_old[$field_name],
						'new' => $object[$field_name]
					];
				}
				unset($values);

				$objects_diff[] = $object_diff;

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
				'clock' => $clock,
				'ip' => $ip,
				'action' => $action,
				'resourcetype' => $resourcetype,
				'resourceid' => $resourceid,
				'resourcename' => $resourcename
			];
		}

		if ($auditlog) {
			$auditids = DB::insertBatch('auditlog', $auditlog);

			if ($action == AUDIT_ACTION_UPDATE) {
				$auditlog_details = [];

				foreach ($objects_diff as $index => $object_diff) {
					foreach ($object_diff as $field_name => $values) {
						$auditlog_details[] = [
							'auditid' => $auditids[$index],
							'table_name' => $table_name,
							'field_name' => $field_name,
							'oldvalue' => $values['old'],
							'newvalue' => $values['new']
						];
					}
				}

				DB::insertBatch('auditlog_details', $auditlog_details);
			}
		}
	}
}

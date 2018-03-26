<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * Add simple audit record.
	 *
	 * @param string $userid
	 * @param string $ip
	 * @param int    $action        AUDIT_ACTION_*
	 * @param int    $resourcetype  AUDIT_RESOURCE_*
	 * @param string $details
	 */
	static public function addDetails($userid, $ip, $action, $resourcetype, $details = '') {
		DB::insert('auditlog', [[
			'userid' => $userid,
			'clock' => time(),
			'ip' => substr($ip, 0, 39),
			'action' => $action,
			'resourcetype' => $resourcetype,
			'details' => $details
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
			'users' => ['passwd' => true]
		];

		switch ($resourcetype) {
			case AUDIT_RESOURCE_ACTION:
				$field_name_resourceid = 'actionid';
				$field_name_resourcename = 'name';
				$table_name = 'actions';
				break;

			case AUDIT_RESOURCE_APPLICATION:
				$field_name_resourceid = 'applicationid';
				$field_name_resourcename = 'name';
				$table_name = 'applications';
				break;

			case AUDIT_RESOURCE_DASHBOARD:
				$field_name_resourceid = 'dashboardid';
				$field_name_resourcename = 'name';
				$table_name = 'dashboard';
				break;

			case AUDIT_RESOURCE_ICON_MAP:
				$field_name_resourceid = 'iconmapid';
				$field_name_resourcename = 'name';
				$table_name = 'icon_map';
				break;

			case AUDIT_RESOURCE_HOST_GROUP:
				$field_name_resourceid = 'groupid';
				$field_name_resourcename = 'name';
				$table_name = 'groups';
				break;

			case AUDIT_RESOURCE_MACRO:
				$field_name_resourceid = 'globalmacroid';
				$field_name_resourcename = 'macro';
				$table_name = 'globalmacro';
				break;

			case AUDIT_RESOURCE_PROXY:
				$field_name_resourceid = 'proxyid';
				$field_name_resourcename = 'host';
				$table_name = 'hosts';
				break;

			case AUDIT_RESOURCE_SCENARIO:
				$field_name_resourceid = 'httptestid';
				$field_name_resourcename = 'name';
				$table_name = 'httptest';
				break;

			case AUDIT_RESOURCE_SCRIPT:
				$field_name_resourceid = 'scriptid';
				$field_name_resourcename = 'name';
				$table_name = 'scripts';
				break;

			case AUDIT_RESOURCE_USER:
				$field_name_resourceid = 'userid';
				$field_name_resourcename = 'alias';
				$table_name = 'users';
				break;

			case AUDIT_RESOURCE_USER_GROUP:
				$field_name_resourceid = 'usrgrpid';
				$field_name_resourcename = 'name';
				$table_name = 'usrgrp';
				break;

			case AUDIT_RESOURCE_VALUE_MAP:
				$field_name_resourceid = 'valuemapid';
				$field_name_resourcename = 'name';
				$table_name = 'valuemaps';
				break;

			default:
				return;
		}

		$clock = time();
		$ip = substr($ip, 0, 39);

		$auditlog = [];
		$objects_diff = [];

		foreach ($objects as $object) {
			$resourceid = $object[$field_name_resourceid];

			if ($action == AUDIT_ACTION_UPDATE) {
				$object_old = $objects_old[$resourceid];

				$object_diff = array_diff_assoc(array_intersect_key($object_old, $object), $object);

				if (!$object_diff) {
					continue;
				}

				foreach ($object_diff as $field_name => &$values) {
					if (array_key_exists($table_name, $masked_fields)
							&& array_key_exists($field_name, $masked_fields[$table_name])) {
						$object_old[$field_name] = '********';
						$object[$field_name] = '********';
					}

					$values = [
						'old' => $object_old[$field_name],
						'new' => $object[$field_name]
					];
				}
				unset($values);

				$objects_diff[] = $object_diff;

				$resourcename = $object_old[$field_name_resourcename];
			}
			else {
				$resourcename = $object[$field_name_resourcename];
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

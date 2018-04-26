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


class CTestDbHelper {

	/**
	 * Returns comma-delimited list of the fields.
	 *
	 * @param string $table_name
	 * @param array  $exlude_fields
	 */
	public static function getTableFields($table_name, array $exlude_fields = []) {
		$fields = [];

		foreach (DB::getSchema($table_name)['fields'] as $field_name) {
			if (!in_array($field_name, $exlude_fields, true)) {
				$fields[] = $field_name;
			}
		}

		return implode(', ', $fields);
	}

	/**
	 * Add host groups to user group with these rights.
	 *
	 * @param string $usergroup_name
	 * @param string $hostgroup_name
	 * @param int $permission
	 * @param bool $subgroups
	 */
	public static function setHostGroupPermissions($usergroup_name, $hostgroup_name, $permission, $subgroups = false) {
		$usergroup = DB::find('usrgrp', ['name' => $usergroup_name]);
		$hostgroups = DB::find('groups', ['name' => $hostgroup_name]);

		if ($usergroup && $hostgroups) {
			$usergroup = $usergroup[0];

			if ($subgroups) {
				$hostgroups = array_merge($hostgroups, DBfetchArray(DBselect(
					'SELECT * FROM groups WHERE name LIKE '.zbx_dbstr($hostgroups[0]['name'].'/%')
				)));
			}

			$rights_old = DB::find('rights', [
				'groupid' => $usergroup['usrgrpid'],
				'id' => array_column($hostgroups, 'groupid')
			]);

			$rights_new = [];
			foreach ($hostgroups as $hostgroup) {
				$rights_new[] = [
					'groupid' => $usergroup['usrgrpid'],
					'permission' => $permission,
					'id' => $hostgroup['groupid']
				];
			}
			DB::replace('rights', $rights_old, $rights_new);
		}
	}

	/**
	 * Create problem or resolved events of trigger.
	 *
	 * @param string $trigger_name
	 * @param int $value TRIGGER_VALUE_FALSE
	 * @param array $event_fields
	 */
	public static function setTriggerProblem($trigger_name, $value = TRIGGER_VALUE_TRUE, $event_fields = []) {
		$trigger = DB::find('triggers', ['description' => $trigger_name ]);

		if ($trigger) {
			$trigger = $trigger[0];

			$tags = DB::select('trigger_tag', [
				'output' => ['tag', 'value'],
				'filter' => ['triggerid' => $trigger['triggerid']],
				'preservekeys' => true
			]);

			$fields = [
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectid' => $trigger['triggerid'],
				'value' => $value,
				'name' => $trigger['description'],
				'clock' => array_key_exists('clock', $event_fields) ? $event_fields['clock'] : time(),
				'ns' => array_key_exists('ns', $event_fields) ? $event_fields['ns'] : 0,
				'acknowledged' => array_key_exists('acknowledged', $event_fields)
					? $event_fields['acknowledged']
					: EVENT_NOT_ACKNOWLEDGED
			];

			$eventid = DB::insert('events', [$fields]);

			if ($eventid) {
				$fields['eventid'] = $eventid[0];

				if ($value == TRIGGER_VALUE_TRUE) {
					DB::insert('problem', [$fields], false);
				} else {
					$problems = DBfetchArray(DBselect(
						'SELECT *'.
						' FROM problem'.
						' WHERE objectid = '.$trigger['triggerid'].
							' AND r_eventid IS NULL'
					));

					if ($problems) {
						DB::update('problem', [
							'values' => [
								'r_eventid' => $fields['eventid'],
								'r_clock' => $fields['clock'],
								'r_ns' => $fields['ns'],
							],
							'where' => ['eventid' => array_column($problems, 'eventid')]
						]);

						$recovery = [];
						foreach ($problems as $problem) {
							$recovery[] = [
								'eventid' => $problem['eventid'],
								'r_eventid' => $fields['eventid']
							];
						}
						DB::insert('event_recovery', $recovery, false);
					}
				}

				if ($tags) {
					foreach ($tags as &$tag) {
						$tag['eventid'] = $fields['eventid'];
					}
					unset($tag);

					DB::insertBatch('event_tag', $tags);

					if ($value == TRIGGER_VALUE_TRUE) {
						DB::insertBatch('problem_tag', $tags);
					}
				}
			}
		}
	}
}

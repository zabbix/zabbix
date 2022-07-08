<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class to perform low level trigger prototype related actions.
 */
class CTriggerPrototypeManager {

	/**
	 * Deletes trigger prototypes and related entities without permission check.
	 *
	 * @param array $triggerids
	 */
	public static function delete(array $triggerids) {
		$del_triggerids = [];

		// Selecting all inherited triggers.
		$parent_triggerids = array_flip($triggerids);
		do {
			$db_triggers = DBselect(
				'SELECT t.triggerid'.
				' FROM triggers t'.
				' WHERE '.dbConditionInt('t.templateid', array_keys($parent_triggerids))
			);

			$del_triggerids += $parent_triggerids;
			$parent_triggerids = [];

			while ($db_trigger = DBfetch($db_triggers)) {
				if (!array_key_exists($db_trigger['triggerid'], $del_triggerids)) {
					$parent_triggerids[$db_trigger['triggerid']] = true;
				}
			}
		} while ($parent_triggerids);

		$del_triggerids = array_keys($del_triggerids);

		// Lock trigger prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM triggers t'.
			' WHERE '.dbConditionInt('t.triggerid', $del_triggerids).
			' FOR UPDATE'
		);

		// Deleting discovered triggers.
		$del_discovered_triggerids = DBfetchColumn(DBselect(
			'SELECT td.triggerid'.
			' FROM trigger_discovery td'.
			' WHERE '.dbConditionInt('td.parent_triggerid', $del_triggerids)
		), 'triggerid');

		if ($del_discovered_triggerids) {
			CTriggerManager::delete($del_discovered_triggerids);
		}

		DB::delete('functions', ['triggerid' => $del_triggerids]);
		DB::delete('trigger_depends', ['triggerid_down' => $del_triggerids]);
		DB::delete('trigger_depends', ['triggerid_up' => $del_triggerids]);
		DB::delete('trigger_tag', ['triggerid' => $del_triggerids]);
		DB::update('triggers', [
			'values' => ['templateid' => 0],
			'where' => ['triggerid' => $del_triggerids]
		]);
		DB::delete('triggers', ['triggerid' => $del_triggerids]);
	}
}

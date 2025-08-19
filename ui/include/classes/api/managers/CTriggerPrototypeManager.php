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

		// Deleting discovered trigger prototypes.
		$db_triggerids = DBfetchColumn(DBselect(
			'SELECT td.triggerid'.
			' FROM trigger_discovery td'.
			' JOIN triggers t ON td.triggerid=t.triggerid'.
			' WHERE '.dbConditionId('td.parent_triggerid', $del_triggerids).
				' AND '.dbConditionInt('t.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED])
		), 'triggerid');

		if ($db_triggerids) {
			self::delete($db_triggerids);
		}

		// Deleting discovered triggers.
		$db_triggerids = DBfetchColumn(DBselect(
			'SELECT td.triggerid'.
			' FROM trigger_discovery td'.
			' JOIN triggers t ON td.triggerid=t.triggerid'.
			' WHERE '.dbConditionId('td.parent_triggerid', $del_triggerids).
				' AND '.dbConditionInt('t.flags', [ZBX_FLAG_DISCOVERY_CREATED])
		), 'triggerid');

		if ($db_triggerids) {
			CTriggerManager::delete($db_triggerids);
		}

		DB::delete('functions', ['triggerid' => $del_triggerids]);
		DB::delete('trigger_tag', ['triggerid' => $del_triggerids]);
		DB::update('triggers', [
			'values' => ['templateid' => 0],
			'where' => ['triggerid' => $del_triggerids]
		]);
		DB::delete('triggers', ['triggerid' => $del_triggerids]);
	}
}

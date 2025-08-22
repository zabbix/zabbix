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
 * Class to perform low level trigger related actions.
 */
class CTriggerManager {

	/**
	 * Deletes triggers and related entities without permission check.
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

		self::checkUsedInActions($del_triggerids);

		API::Map()->unlinkTriggers($del_triggerids);

		// Remove related events.
		$ins_housekeeper = [];

		foreach ($del_triggerids as $del_triggerid) {
			$ins_housekeeper[] = [
				'tablename' => 'events',
				'field' => 'triggerid',
				'value' => $del_triggerid
			];
		}

		DB::insertBatch('housekeeper', $ins_housekeeper);

		DB::delete('functions', ['triggerid' => $del_triggerids]);
		DB::delete('trigger_tag', ['triggerid' => $del_triggerids]);
		DB::update('triggers', [
			'values' => ['templateid' => 0],
			'where' => ['triggerid' => $del_triggerids]
		]);
		DB::delete('triggers', ['triggerid' => $del_triggerids]);
	}

	/**
	 * @throws APIException
	 */
	private static function checkUsedInActions(array $del_triggerids): void {
		$row = DBfetch(DBselect(
			'SELECT a.name,t.description'.
			' FROM conditions c'.
			' JOIN actions a ON c.actionid=a.actionid'.
			' JOIN triggers t ON '.zbx_dbcast_2bigint('c.value').'=t.triggerid'.
			' WHERE c.conditiontype='.ZBX_CONDITION_TYPE_TRIGGER.
				' AND '.dbConditionString('c.value', $del_triggerids),
			1
		));

		if ($row) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete trigger "%1$s": %2$s.',
				$row['description'], _s('action "%1$s" uses this trigger', $row['name'])
			));
		}
	}
}

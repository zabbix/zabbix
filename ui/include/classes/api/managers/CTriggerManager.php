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

		// Disable actions.
		$actionids = [];
		$conditionids = [];

		$db_actions = DBselect(
			'SELECT ac.conditionid,ac.actionid'.
			' FROM conditions ac'.
			' WHERE ac.conditiontype='.CONDITION_TYPE_TRIGGER.
				' AND '.dbConditionString('ac.value', $del_triggerids)
		);
		while ($db_action = DBfetch($db_actions)) {
			$conditionids[] = $db_action['conditionid'];
			$actionids[$db_action['actionid']] = true;
		}

		if ($actionids) {
			DB::update('actions', [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => array_keys($actionids)]
			]);

			// Delete action conditions.
			DB::delete('conditions', ['conditionid' => $conditionids]);
		}

		// Remove trigger sysmap elements.
		$selement_triggerids = [];
		$selementids = [];

		$db_selement_triggers = DBselect(
			'SELECT st.selement_triggerid,st.selementid'.
			' FROM sysmap_element_trigger st'.
			' WHERE '.dbConditionInt('st.triggerid', $del_triggerids)
		);

		while ($db_selement_trigger = DBfetch($db_selement_triggers)) {
			$selement_triggerids[] = $db_selement_trigger['selement_triggerid'];
			$selementids[$db_selement_trigger['selementid']] = true;
		}

		if ($selement_triggerids) {
			DB::delete('sysmap_element_trigger', ['selement_triggerid' => $selement_triggerids]);

			// Remove map elements without triggers.
			$db_selement_triggers = DBselect(
				'SELECT DISTINCT st.selementid'.
				' FROM sysmap_element_trigger st'.
				' WHERE '.dbConditionInt('st.selementid', array_keys($selementids))
			);
			while ($db_selement_trigger = DBfetch($db_selement_triggers)) {
				unset($selementids[$db_selement_trigger['selementid']]);
			}

			if ($selementids) {
				DB::delete('sysmaps_elements', ['selementid' => array_keys($selementids)]);
			}
		}

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
		DB::delete('trigger_discovery', ['triggerid' => $del_triggerids]);
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

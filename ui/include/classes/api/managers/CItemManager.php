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
 * Class to perform low level item related actions.
 */
class CItemManager {

	/**
	 * Deletes items and related entities without permission check.
	 *
	 * @param array $itemids
	 */
	public static function delete(array $itemids) {
		global $DB;

		$del_itemids = [];
		$del_ruleids = [];
		$del_item_prototypeids = [];

		// Selecting all inherited items.
		$parent_itemids = array_flip($itemids);
		do {
			$db_items = DBselect(
				'SELECT i.itemid FROM items i WHERE '.dbConditionInt('i.templateid', array_keys($parent_itemids))
			);

			$del_itemids += $parent_itemids;
			$parent_itemids = [];

			while ($db_item = DBfetch($db_items)) {
				if (!array_key_exists($db_item['itemid'], $del_itemids)) {
					$parent_itemids[$db_item['itemid']] = true;
				}
			}
		} while ($parent_itemids);

		// Selecting all dependent items.
		// Note: We are not separating normal from discovered items at this point.
		$dep_itemids = [
			ZBX_FLAG_DISCOVERY_NORMAL => $del_itemids,
			ZBX_FLAG_DISCOVERY_RULE => [],
			ZBX_FLAG_DISCOVERY_CREATED => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		do {
			$db_items = DBselect(
				'SELECT i.itemid,i.flags'.
				' FROM items i'.
				' WHERE i.type='.ITEM_TYPE_DEPENDENT.
					' AND '.dbConditionInt('i.master_itemid',
						array_keys($dep_itemids[ZBX_FLAG_DISCOVERY_NORMAL]
							+ $dep_itemids[ZBX_FLAG_DISCOVERY_CREATED]
							+ $dep_itemids[ZBX_FLAG_DISCOVERY_PROTOTYPE]
						)
					)
			);

			$dep_itemids = [
				ZBX_FLAG_DISCOVERY_NORMAL => [],
				ZBX_FLAG_DISCOVERY_RULE => [],
				ZBX_FLAG_DISCOVERY_CREATED => [],
				ZBX_FLAG_DISCOVERY_PROTOTYPE => []
			];

			while ($db_item = DBfetch($db_items)) {
				switch ($db_item['flags']) {
					case ZBX_FLAG_DISCOVERY_NORMAL:
						if (!array_key_exists($db_item['itemid'], $del_itemids)) {
							$dep_itemids[ZBX_FLAG_DISCOVERY_NORMAL][$db_item['itemid']] = true;
						}
						break;

					case ZBX_FLAG_DISCOVERY_RULE:
						$dep_itemids[ZBX_FLAG_DISCOVERY_RULE][$db_item['itemid']] = true;
						break;

					case ZBX_FLAG_DISCOVERY_CREATED:
						if (!array_key_exists($db_item['itemid'], $del_itemids)) {
							$dep_itemids[ZBX_FLAG_DISCOVERY_CREATED][$db_item['itemid']] = true;
						}
						break;

					case ZBX_FLAG_DISCOVERY_PROTOTYPE:
						$dep_itemids[ZBX_FLAG_DISCOVERY_PROTOTYPE][$db_item['itemid']] = true;
						break;
				}
			}

			$del_itemids += $dep_itemids[ZBX_FLAG_DISCOVERY_NORMAL];
			$del_itemids += $dep_itemids[ZBX_FLAG_DISCOVERY_CREATED];
			$del_ruleids += $dep_itemids[ZBX_FLAG_DISCOVERY_RULE];
			$del_item_prototypeids += $dep_itemids[ZBX_FLAG_DISCOVERY_PROTOTYPE];

		} while ($dep_itemids[ZBX_FLAG_DISCOVERY_NORMAL]
			|| $dep_itemids[ZBX_FLAG_DISCOVERY_CREATED]
			|| $dep_itemids[ZBX_FLAG_DISCOVERY_PROTOTYPE]
		);

		$del_itemids = array_keys($del_itemids);

		if ($del_ruleids) {
			CDiscoveryRuleManager::delete(array_keys($del_ruleids));
		}

		if ($del_item_prototypeids) {
			CItemPrototypeManager::delete(array_keys($del_item_prototypeids));
		}

		// Deleting graphs and graph prototypes, which will remain without items.
		$db_graphs = DBselect(
			'SELECT DISTINCT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.dbConditionInt('gi.itemid', $del_itemids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gii'.
					' WHERE gii.graphid=gi.graphid'.
						' AND '.dbConditionInt('gii.itemid', $del_itemids, true).
				')'
		);

		$del_graphids = [];

		while ($db_graph = DBfetch($db_graphs)) {
			$del_graphids[] = $db_graph['graphid'];
		}

		if ($del_graphids) {
			CGraphManager::delete($del_graphids);
		}

		// Cleanup ymin_itemid and ymax_itemid fields for graphs and graph prototypes.
		DB::update('graphs', [
			'values' => [
				'ymin_type' => GRAPH_YAXIS_TYPE_CALCULATED,
				'ymin_itemid' => null
			],
			'where' => ['ymin_itemid' => $del_itemids]
		]);

		DB::update('graphs', [
			'values' => [
				'ymax_type' => GRAPH_YAXIS_TYPE_CALCULATED,
				'ymax_itemid' => null
			],
			'where' => ['ymax_itemid' => $del_itemids]
		]);

		// Deleting triggers and trigger prototypes.
		$db_triggers = DBselect(
			'SELECT DISTINCT t.triggerid,t.flags'.
			' FROM triggers t,functions f'.
			' WHERE t.triggerid=f.triggerid'.
				' AND '.dbConditionInt('f.itemid', $del_itemids)
		);

		$del_triggerids = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_CREATED => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		while ($db_trigger = DBfetch($db_triggers)) {
			$del_triggerids[$db_trigger['flags']][] = $db_trigger['triggerid'];
		}

		if ($del_triggerids[ZBX_FLAG_DISCOVERY_NORMAL] || $del_triggerids[ZBX_FLAG_DISCOVERY_CREATED]) {
			CTriggerManager::delete(array_merge(
				$del_triggerids[ZBX_FLAG_DISCOVERY_NORMAL],
				$del_triggerids[ZBX_FLAG_DISCOVERY_CREATED]
			));
		}

		if ($del_triggerids[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			CTriggerPrototypeManager::delete($del_triggerids[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}

		DB::delete('profiles', [
			'idx' => 'web.favorite.graphids',
			'source' => 'itemid',
			'value_id' => $del_itemids
		]);

		$table_names = ['events'];

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_MODE) != 0) {
			array_push($table_names, 'history', 'history_str', 'history_uint', 'history_log', 'history_text');
		}

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_MODE) != 0) {
			array_push($table_names,'trends', 'trends_uint');
		}

		if ($DB['TYPE'] === ZBX_DB_POSTGRESQL) {
			if (CHousekeepingHelper::get(CHousekeepingHelper::DB_EXTENSION) === ZBX_DB_EXTENSION_TIMESCALEDB) {
				if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_MODE) != 0
						&& CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 1) {
					$table_names = array_diff($table_names,
						['history', 'history_str', 'history_uint', 'history_log', 'history_text']
					);
				}

				if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_MODE) != 0
						&& CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL) == 1) {
					$table_names = array_diff($table_names, ['trends', 'trends_uint']);
				}
			}
		}

		$ins_housekeeper = [];

		foreach ($del_itemids as $del_itemid) {
			foreach ($table_names as $table_name) {
				$ins_housekeeper[] = [
					'tablename' => $table_name,
					'field' => 'itemid',
					'value' => $del_itemid
				];

				if (count($ins_housekeeper) == ZBX_DB_MAX_INSERTS) {
					DB::insertBatch('housekeeper', $ins_housekeeper);
					$ins_housekeeper = [];
				}
			}
		}

		if ($ins_housekeeper) {
			DB::insertBatch('housekeeper', $ins_housekeeper);
		}

		DB::delete('item_tag', ['itemid' => $del_itemids]);
		DB::delete('item_preproc', ['itemid' => $del_itemids]);
		DB::update('items', [
			'values' => ['templateid' => 0, 'master_itemid' => 0],
			'where' => ['itemid' => $del_itemids]
		]);
		DB::delete('items', ['itemid' => $del_itemids]);
	}
}

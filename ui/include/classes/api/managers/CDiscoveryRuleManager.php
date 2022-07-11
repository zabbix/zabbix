<?php declare(strict_types = 0);
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
 * Class to perform low level discovery rule related actions.
 */
class CDiscoveryRuleManager {

	/**
	 * Deletes discovery rule and related entities without permission check.
	 *
	 * @param array $ruleids
	 */
	public static function delete(array $ruleids): void {
		// Get child discovery rules.
		$parent_itemids = $ruleids;
		$child_ruleids = [];
		do {
			$db_items = DBselect('SELECT i.itemid FROM items i WHERE '.dbConditionInt('i.templateid', $parent_itemids));
			$parent_itemids = [];
			while ($db_item = DBfetch($db_items)) {
				$parent_itemids[$db_item['itemid']] = $db_item['itemid'];
				$child_ruleids[$db_item['itemid']] = $db_item['itemid'];
			}
		} while ($parent_itemids);

		$ruleids = array_merge($ruleids, $child_ruleids);

		// Delete item prototypes.
		$iprototypeids = [];
		$db_items = DBselect(
			'SELECT i.itemid'.
			' FROM item_discovery id,items i'.
			' WHERE i.itemid=id.itemid'.
				' AND '.dbConditionInt('parent_itemid', $ruleids)
		);
		while ($item = DBfetch($db_items)) {
			$iprototypeids[$item['itemid']] = $item['itemid'];
		}
		if ($iprototypeids) {
			CItemPrototypeManager::delete($iprototypeids);
		}

		// Delete host prototypes.
		$db_host_prototypes = DBfetchArrayAssoc(DBselect(
			'SELECT hd.hostid,h.host'.
			' FROM host_discovery hd,hosts h'.
			' WHERE hd.hostid=h.hostid'.
				' AND '.dbConditionInt('hd.parent_itemid', $ruleids)
		), 'hostid');

		if ($db_host_prototypes) {
			CHostPrototype::deleteForce($db_host_prototypes);
		}

		// Delete LLD rules.
		DB::delete('items', ['itemid' => $ruleids]);

		$insert = [];
		foreach ($ruleids as $ruleid) {
			$insert[] = [
				'tablename' => 'events',
				'field' => 'lldruleid',
				'value' => $ruleid
			];
		}
		DB::insertBatch('housekeeper', $insert);
	}
}

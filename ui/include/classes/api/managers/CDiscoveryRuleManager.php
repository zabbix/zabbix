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


/**
 * Class to perform low level discovery rule related actions.
 */
class CDiscoveryRuleManager {

	/**
	 * Deletes discovery rule and related entities without permission check.
	 *
	 * @param array $ruleids
	 */
	public static function delete(array &$ruleids, array $delRules) {
		// get child discovery rules
		$parentItemids = $ruleids;
		$childTuleids = [];
		do {
			$dbItems = DBselect('SELECT i.itemid FROM items i WHERE '.dbConditionInt('i.templateid', $parentItemids));
			$parentItemids = [];
			while ($dbItem = DBfetch($dbItems)) {
				$parentItemids[$dbItem['itemid']] = $dbItem['itemid'];
				$childTuleids[$dbItem['itemid']] = $dbItem['itemid'];
			}
		} while (!empty($parentItemids));

		$delRulesChildren = API::DiscoveryRule()->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $childTuleids,
			'nopermissions' => true,
			'preservekeys' => true
		]);

		$delRules = array_merge($delRules, $delRulesChildren);
		$ruleids = array_merge($ruleids, $childTuleids);

		$iprototypeids = [];
		$dbItems = DBselect(
			'SELECT i.itemid'.
			' FROM item_discovery id,items i'.
			' WHERE i.itemid=id.itemid'.
				' AND '.dbConditionInt('parent_itemid', $ruleids)
		);
		while ($item = DBfetch($dbItems)) {
			$iprototypeids[$item['itemid']] = $item['itemid'];
		}
		if ($iprototypeids) {
			CItemPrototypeManager::delete($iprototypeids);
		}

		// delete host prototypes
		$hostPrototypeIds = DBfetchColumn(DBselect(
			'SELECT hd.hostid'.
			' FROM host_discovery hd'.
			' WHERE '.dbConditionInt('hd.parent_itemid', $ruleids)
		), 'hostid');
		if ($hostPrototypeIds) {
			if (!API::HostPrototype()->delete($hostPrototypeIds, true)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete host prototype.'));
			}
		}

		// delete LLD rules
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

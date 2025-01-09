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
 * Class to perform low level graph prototype related actions.
 */
class CGraphPrototypeManager {

	/**
	 * Deletes graph prototypes and related entities without permission check.
	 *
	 * @param array $graphids
	 */
	public static function delete(array $graphids) {
		$del_graphids = [];

		// Selecting all inherited graphs.
		$parent_graphids = array_flip($graphids);
		do {
			$db_graphs = DBselect(
				'SELECT g.graphid FROM graphs g WHERE '.dbConditionInt('g.templateid', array_keys($parent_graphids))
			);

			$del_graphids += $parent_graphids;
			$parent_graphids = [];

			while ($db_graph = DBfetch($db_graphs)) {
				if (!array_key_exists($db_graph['graphid'], $del_graphids)) {
					$parent_graphids[$db_graph['graphid']] = true;
				}
			}
		} while ($parent_graphids);

		$del_graphids = array_keys($del_graphids);

		// Lock graph prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM graphs g'.
			' WHERE '.dbConditionInt('g.graphid', $del_graphids).
			' FOR UPDATE'
		);

		// Deleting discovered graphs.
		$del_discovered_graphids = DBfetchColumn(DBselect(
			'SELECT gd.graphid FROM graph_discovery gd WHERE '.dbConditionInt('gd.parent_graphid', $del_graphids)
		), 'graphid');

		if ($del_discovered_graphids) {
			CGraphManager::delete($del_discovered_graphids);
		}

		DB::delete('graphs_items', ['graphid' => $del_graphids]);
		DB::delete('graphs', ['graphid' => $del_graphids]);
	}
}

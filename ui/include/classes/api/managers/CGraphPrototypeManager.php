<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Class to perform low level graph prototype related actions.
 */
class CGraphPrototypeManager {

	/**
	 * Deletes graph prototypes and related entities without permission check.
	 *
	 * @param array $del_graphids
	 */
	public static function delete(array $del_graphids) {
		// Add the inherited graphs.
		$options = [
			'output' => ['graphid'],
			'filter' => ['templateid' => $del_graphids]
		];
		$del_graphids = array_merge($del_graphids,
			DBfetchColumn(DBselect(DB::makeSql('graphs', $options)), 'graphid')
		);

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

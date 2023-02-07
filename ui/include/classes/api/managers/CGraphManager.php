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
 * Class to perform low level graph related actions.
 */
class CGraphManager {

	/**
	 * Deletes graphs and related entities without permission check.
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

		DB::delete('profiles', [
			'idx' => 'web.latest.graphid',
			'value_id' => $del_graphids
		]);

		DB::delete('widget_field', ['value_graphid' => $del_graphids]);
		DB::delete('graph_discovery', ['graphid' => $del_graphids]);
		DB::delete('graphs_items', ['graphid' => $del_graphids]);
		DB::delete('graphs', ['graphid' => $del_graphids]);
	}
}

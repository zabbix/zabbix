<?php declare(strict_types = 0);
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


class CGraphPrototypeHelper extends CGraphGeneralHelper {

	/**
	 * @param array $src_options
	 * @param array $dst_options
	 *
	 * @return bool
	 */
	public static function copy(array $src_options, array $dst_options): bool {
		$src_graphs = self::getSourceGraphs($src_options);

		if (!$src_graphs) {
			return true;
		}

		$dst_itemids = self::getDestinationItems($src_graphs, $dst_options);

		$dst_hostids = reset($dst_options);
		$dst_graphs = [];

		foreach ($dst_hostids as $dst_hostid) {
			foreach ($src_graphs as $src_graph) {
				$dst_graph = array_diff_key($src_graph, array_flip(['graphid']));

				if ($dst_graph['ymin_itemid'] != 0 && array_key_exists($dst_graph['ymin_itemid'], $dst_itemids)) {
					$dst_graph['ymin_itemid'] = $dst_itemids[$dst_graph['ymin_itemid']][$dst_hostid];
				}

				if ($dst_graph['ymax_itemid'] != 0 && array_key_exists($dst_graph['ymax_itemid'], $dst_itemids)) {
					$dst_graph['ymax_itemid'] = $dst_itemids[$dst_graph['ymax_itemid']][$dst_hostid];
				}

				foreach ($dst_graph['gitems'] as &$dst_gitem) {
					if (array_key_exists($dst_gitem['itemid'], $dst_itemids)) {
						$dst_gitem['itemid'] = $dst_itemids[$dst_gitem['itemid']][$dst_hostid];
					}
				}
				unset($dst_gitem);

				$dst_graphs[] = $dst_graph;
			}
		}

		$response = API::GraphPrototype()->create($dst_graphs);

		return $response !== false;
	}

	/**
	 * @param array  $src_options
	 *
	 * @return array
	 */
	private static function getSourceGraphs(array $src_options): array {
		return API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'width', 'height', 'graphtype', 'show_legend', 'show_work_period',
				'show_3d', 'show_triggers', 'percent_left', 'percent_right', 'ymin_type', 'yaxismin', 'ymin_itemid',
				'ymax_type', 'yaxismax', 'ymax_itemid', 'discover'
			],
			'selectGraphItems' => ['sortorder', 'itemid', 'type', 'calc_fnc', 'drawtype', 'yaxisside', 'color'],
			'preservekeys' => true
		] + $src_options);
	}

	/**
	 * @param array       $src_graphs
	 * @param array       $dst_options
	 * @param array|null  $src_options
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected static function getDestinationItems(array $src_graphs, array $dst_options,
			?array $src_options = null): array {
		$dst_hostids = reset($dst_options);
		$src_item_graphs = [];

		foreach ($src_graphs as $src_graph) {
			if ($src_graph['ymin_itemid'] != 0) {
				$src_item_graphs[$src_graph['ymin_itemid']][$src_graph['graphid']] = true;
			}

			if ($src_graph['ymax_itemid'] != 0) {
				$src_item_graphs[$src_graph['ymax_itemid']][$src_graph['graphid']] = true;
			}

			foreach ($src_graph['gitems'] as $gitem) {
				$src_item_graphs[$gitem['itemid']][$src_graph['graphid']] = true;
			}
		}

		$src_items = API::ItemPrototype()->get([
			'output' => ['itemid', 'hostid', 'key_'],
			'webitems' => true,
			'itemids' => array_keys($src_item_graphs)
		]);

		$dst_items = API::ItemPrototype()->get([
			'output' => ['itemid', 'hostid', 'key_'],
			'webitems' => true,
			'filter' => ['key_' => array_unique(array_column($src_items, 'key_'))]
		] + $dst_options);

		$_dst_itemids = [];

		foreach ($dst_items as $dst_item) {
			$_dst_itemids[$dst_item['key_']][$dst_item['hostid']] = $dst_item['itemid'];
		}

		$dst_itemids = [];

		foreach ($src_items as $src_item) {
			foreach ($dst_hostids as $dst_hostid) {
				$dst_itemids[$src_item['itemid']][$dst_hostid] = $_dst_itemids[$src_item['key_']][$dst_hostid];
			}
		}

		$dst_itemids += parent::getDestinationItems($src_graphs, $dst_options, ['hostids' => $src_items[0]['hostid']]);

		return $dst_itemids;
	}
}

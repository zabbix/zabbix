<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


class CGraphHelper extends CGraphGeneralHelper {

	/**
	 * @param string $src_templateid
	 * @param string $dst_templateid
	 *
	 * @return bool
	 */
	public static function cloneTemplateGraphs(string $src_templateid, string $dst_templateid): bool {
		$src_options = [
			'hostids' => $src_templateid,
			'inherited' => false
		];

		$dst_options = ['templateids' => [$dst_templateid]];

		return self::clone($src_options, $dst_options);
	}

	/**
	 * @param string $src_hostid
	 * @param string $dst_hostid
	 *
	 * @return bool
	 */
	public static function cloneHostGraphs(string $src_hostid, string $dst_hostid): bool {
		$src_options = [
			'hostids' => $src_hostid,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		];

		$dst_options = ['hostids' => [$dst_hostid]];

		return self::clone($src_options, $dst_options);
	}

	/**
	 * Clone graphs. Graphs with an item from other than cloned host are ignored.
	 *
	 * @param array $src_options
	 * @param array $dst_options
	 *
	 * @return bool
	 */
	private static function clone(array $src_options, array $dst_options): bool {
		$src_graphs = self::getSourceGraphs($src_options);

		if (!$src_graphs) {
			return true;
		}

		try {
			$dst_itemids = self::getDestinationItems($src_graphs, $dst_options, $src_options);
		}
		catch (Throwable $e) {
			return false;
		}

		$dst_hostids = reset($dst_options);
		$dst_graphs = [];

		foreach ($dst_hostids as $dst_hostid) {
			foreach ($src_graphs as $dst_graph) {
				$check_itemids = array_column($dst_graph['gitems'], 'itemid', 'itemid');

				if ($dst_graph['ymin_itemid'] != 0) {
					$check_itemids[$dst_graph['ymin_itemid']] = true;
				}

				if ($dst_graph['ymax_itemid'] != 0) {
					$check_itemids[$dst_graph['ymax_itemid']] = true;
				}

				// Skip graph with items from other than the cloned host.
				if (array_diff_key($check_itemids, $dst_itemids)) {
					continue;
				}

				if ($dst_graph['ymin_itemid'] != 0) {
					$dst_graph['ymin_itemid'] = $dst_itemids[$dst_graph['ymin_itemid']][$dst_hostid];
				}

				if ($dst_graph['ymax_itemid'] != 0) {
					$dst_graph['ymax_itemid'] = $dst_itemids[$dst_graph['ymax_itemid']][$dst_hostid];
				}

				foreach ($dst_graph['gitems'] as &$dst_gitem) {
					$dst_gitem['itemid'] = $dst_itemids[$dst_gitem['itemid']][$dst_hostid];
				}
				unset($dst_gitem);

				$dst_graphs[] = array_diff_key($dst_graph, array_flip(['graphid', 'flags']));
			}
		}

		return $dst_graphs ? (bool) API::Graph()->create($dst_graphs) : true;
	}

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
		elseif (!array_key_exists('hostids', $src_options)) {
			foreach ($src_graphs as $src_graph) {
				if (count($src_graph['hosts']) > 1) {
					error(_s('Cannot copy graph "%1$s", because it has items from the multiple hosts.',
						$src_graph['name']
					));

					return false;
				}
			}
		}

		try {
			$dst_itemids = self::getDestinationItems($src_graphs, $dst_options, $src_options);
		}
		catch (Exception $e) {
			return false;
		}

		$dst_hostids = reset($dst_options);
		$dst_graphs = [];

		foreach ($dst_hostids as $dst_hostid) {
			foreach ($src_graphs as $src_graph) {
				$dst_graph = array_diff_key($src_graph, array_flip(['graphid', 'flags']));

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

		$response = API::Graph()->create($dst_graphs);

		return $response !== false;
	}

	/**
	 * @param array  $src_options
	 *
	 * @return array
	 */
	private static function getSourceGraphs(array $src_options): array {
		if (!array_key_exists('hostids', $src_options)) {
			$src_options['selectHosts'] = ['hostid'];
		}
		elseif ($src_options['hostids'] == 0) {
			unset($src_options['hostids']);
		}

		return API::Graph()->get([
			'output' => ['graphid', 'name', 'width', 'height', 'graphtype', 'show_legend', 'show_work_period',
				'show_3d', 'show_triggers', 'percent_left', 'percent_right', 'ymin_type', 'yaxismin', 'ymin_itemid',
				'ymax_type', 'yaxismax', 'ymax_itemid', 'flags'
			],
			'selectGraphItems' => ['sortorder', 'itemid', 'type', 'calc_fnc', 'drawtype', 'yaxisside', 'color'],
			'preservekeys' => true
		] + $src_options);
	}
}

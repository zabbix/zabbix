<?php declare(strict_types = 0);
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

		return self::copy($src_options, $dst_options);
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

		return self::copy($src_options, $dst_options);
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

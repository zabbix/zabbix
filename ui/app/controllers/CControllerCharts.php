<?php declare(strict_types = 1);
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
 * Common methods for "charts.view" and "charts.view.json" actions.
 */
abstract class CControllerCharts extends CController {
	/**
	 * Fetches all host graphs based on hostid and graph name or item name used in graph.
	 *
	 * @param array  $hostids  Limit returned graphs to these hosts.
	 * @param string $name     Graphs or items in them should contain this string in their name.
	 *
	 * @return array
	 */
	protected function getHostGraphs(array $hostids, string $name): array {
		$graphs = API::Graph()->get([
			'output' => ['graphid', 'name'],
			'hostids' => $hostids,
			'search' => $name !== '' ? ['name' => $name] : null,
			'selectItems' => ['itemid'],
			'preservekeys' => true
		]);

		$graph_items = [];
		foreach ($graphs as $graph) {
			foreach ($graph['items'] as $item) {
				$graph_items[$item['itemid']] = $item;
			}
		}

		if ($name !== '') {
			$graph_items += API::Item()->get([
				'output' => ['itemid'],
				'hostids' => $hostids,
				'search' => ['name' => $name],
				// TODO VM: filter by tags
				'preservekeys' => true
			]);
		}

		$items = API::Item()->get([
			'output' => ['itemid', 'name'],
			'hostids' => $hostids,
			'itemids' => array_keys($graph_items),
			// TODO VM: filter by tags
			'selectTags' => ['key', 'value'], // TODO
			'preservekeys' => true
		]);

		if ($name !== '' /* TODO VM: OR filter by tags */) {
			$graphs = API::Graph()->get([
				'output' => ['graphid', 'name'],
				'hostids' => $hostids,
				'itemids' => array_keys($items),
				'preservekeys' => true
			]);
		}

		return [
			$graphs,
			$items
		];
	}

	/**
	 * Fetches all host graphs based on hostid and graph name or item name used in graph.
	 *
	 * @param array  $hostids  Limit returned graphs to these hosts.
	 * @param string $name     Graphs or items in them should contain this string in their name.
	 *
	 * @return array
	 */
	protected function getSimpleGraphs(array $hostids, string $name): array {
		return API::Item()->get([
			'output' => ['itemid', 'name'],
			'hostids' => $hostids,
			'search' => $name !== '' ? ['name' => $name] : null,
			// TODO VM: filter by tags
			'selectTags' => ['key', 'value'], // TODO
			'preservekeys' => true
		]);
	}

	/**
	 * Prepares graph display details (graph dimensions, URL and sBox-ing flag).
	 *
	 * @param array $graphids  Graph IDs for which details need to be prepared.
	 *
	 * @return array
	 */
	protected function getCharts(array $graphs): array {
		$charts = [];

		foreach ($graphs as $graph) {
			if (array_key_exists('graphid', $graph)) {
				$chart = [
					'chartid' => 'graph_'.$graph['graphid'],
					'graphid' => $graph['graphid'],
					'dimensions' => getGraphDims($graph['graphid'])
				];

				if (in_array($chart['dimensions']['graphtype'], [GRAPH_TYPE_PIE, GRAPH_TYPE_EXPLODED])) {
					$chart['sbox'] = false;
					$chart['src'] = 'chart6.php';
				}
				else {
					$chart['sbox'] = true;
					$chart['src'] = 'chart2.php';
				}
			}
			else {
				$chart = [
					'chartid' => 'item_'.$graph['itemid'],
					'itemid' => $graph['itemid'],
					'dimensions' => getGraphDims(),
					'sbox' => true,
					'src' => 'chart.php'
				];
			}

			$charts[] = $chart;
		}

		return $charts;
	}
}

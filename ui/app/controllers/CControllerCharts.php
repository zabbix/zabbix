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
	 * Fetches all host graph ids or also intersect with given graphids array (second parameter).
	 *
	 * @param array $hostids   If not empty, return the listed host graphids.
	 * @param array $graphids  If not empty, will be used for graphs filter.
	 *
	 * @return array
	 */
	protected function getGraphidsByHostids(array $hostids, array $graphids): array {
		if (!$hostids && $graphids) {
			return $graphids;
		}

		$options = [
			'output' => [],
			'hostids' => $hostids,
			'limit' => ZBX_MAX_GRAPHS_PER_PAGE,
			'preservekeys' => true
		];

		if ($hostids && $graphids) {
			$options['graphids'] = $graphids;
		}

		return array_keys(API::Graph()->get($options));
	}

	/**
	 * Fetches graph IDs by pattern for specific host.
	 *
	 * @param array $patterns  List of graph patterns.
	 * @param array $hostids   Hosts for which search graphs by patterns.
	 *
	 * @return array
	 */
	protected function getGraphidsByPatterns(array $patterns, array $hostids): array {
		$options = [
			'output' => [],
			'hostids' => $hostids,
			'limit' => ZBX_MAX_GRAPHS_PER_PAGE,
			'preservekeys' => true
		];

		if (!in_array('*', $patterns)) {
			$options['search'] = ['name' => $patterns];
			$options['searchWildcardsEnabled'] = true;
			$options['searchByAny'] = true;
		}

		return array_keys(API::Graph()->get($options));
	}

	/**
	 * Prepares graph display details (graph dimensions, URL and sBox-ing flag).
	 *
	 * @param array $graphids  Graph IDs for which details need to be prepared.
	 *
	 * @return array
	 */
	protected function getChartsById(array $graphids): array {
		$charts = [];

		foreach ($graphids as $graphid) {
			$chart = [
				'chartid' => $graphid,
				'dimensions' => getGraphDims($graphid)
			];

			if ($chart['dimensions']['graphtype'] == GRAPH_TYPE_PIE
					|| $chart['dimensions']['graphtype'] == GRAPH_TYPE_EXPLODED) {
				$chart['sbox'] = false;
				$chart['src'] = 'chart6.php';
			}
			else {
				$chart['sbox'] = true;
				$chart['src'] = 'chart2.php';
			}

			$charts[] = $chart;
		}

		return $charts;
	}
}

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


class CGraphHelper extends CGraphGeneralHelper {

	public static function validateNumberRangeWithPrecision(string $value, int $min, int $max, int $scale): bool {
		return !(!is_numeric($value) || $value < $min || $value > $max || round($value, $scale) != $value);
	}

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

	/**
	 * Expands graph item objects data: macros in item name, time units, dependent item
	 */
	public static function calculateMetricsDelay(array &$metrics): void {
		$master_itemids = [];

		foreach ($metrics as &$metric) {
			if ($metric['type'] == ITEM_TYPE_DEPENDENT) {
				$master_itemids[$metric['master_itemid']] = true;
			}

			$metric['throttling_type'] = 0;
			$metric['throttling_delay'] = '';

			foreach ($metric['preprocessing'] as $step) {
				if ($step['type'] == ZBX_PREPROC_THROTTLE_VALUE || $step['type'] == ZBX_PREPROC_THROTTLE_TIMED_VALUE) {
					$metric['throttling_type'] = $step['type'];
					if ($step['type'] == ZBX_PREPROC_THROTTLE_TIMED_VALUE) {
						$metric['throttling_delay'] = $step['params'];
					}

					// Only one throttling step is allowed.
					break;
				}
			}
			unset($metric['preprocessing']);
		}
		unset($metric);

		$master_items = self::getMasterItems(array_keys($master_itemids));

		$master_items = CMacrosResolverHelper::resolveTimeUnitMacros($master_items, ['delay']);
		$metrics = CMacrosResolverHelper::resolveTimeUnitMacros($metrics, ['delay', 'throttling_delay']);

		foreach ($metrics as &$metric) {
			if ($metric['type'] == ITEM_TYPE_DEPENDENT) {
				$master_itemid = $metric['master_itemid'];

				while ($master_items[$master_itemid]['type'] == ITEM_TYPE_DEPENDENT) {
					$master_itemid = $master_items[$master_itemid]['master_itemid'];
				}

				// Throttling of the master item is not taken into account, as this configuration is unlikely.
				$metric['type'] = $master_items[$master_itemid]['type'];
				$metric['delay'] = $master_items[$master_itemid]['delay'];
			}

			$metric['delay'] = self::getItemMaxDelay($metric);

			if (strpos($metric['units'], ',') === false) {
				$metric['units_long'] = '';
			}
			else {
				list($metric['units'], $metric['units_long']) = explode(',', $metric['units'], 2);
			}
		}
		unset($metric);
	}

	/**
	 * Returns an array of master items for the given array of item IDs.
	 *
	 * @param array  $master_itemids
	 *
	 * @return array
	 */
	private static function getMasterItems(array $master_itemids): array {
		$master_items = [];

		do {
			$items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'type', 'master_itemid', 'delay'],
				'itemids' => $master_itemids,
				'filter' => [
					'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
				]
			]);

			$master_itemids = [];

			foreach ($items as $item) {
				if ($item['type'] == ITEM_TYPE_DEPENDENT && !array_key_exists($item['master_itemid'], $master_items)) {
					$master_itemids[$item['master_itemid']] = true;
				}
				$master_items[$item['itemid']] = $item;
			}
			$master_itemids = array_keys($master_itemids);
		} while ($master_itemids);

		return $master_items;
	}

	/**
	 * Returns the maximum item update interval based on the values of the "type" and "delay" fields.
	 * Returns NULL if the item type does not support an update interval or if an error occurred during calculation.
	 *
	 * @param array  $item
	 *
	 * @return int|float|null
	 */
	private static function getItemMaxDelay(array $item): int|float|null {
		if (($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && preg_match('/^(event)?log(rt)?\[/', $item['key_']))
				|| $item['type'] == ITEM_TYPE_TRAPPER) {
			return null;
		}

		$update_interval_parser = new CUpdateIntervalParser();

		if ($update_interval_parser->parse($item['delay']) != CParser::PARSE_SUCCESS) {
			return null;
		}

		$delay = timeUnitToSeconds($update_interval_parser->getDelay());

		foreach ($update_interval_parser->getIntervals(ITEM_DELAY_FLEXIBLE) as $flexible_interval) {
			$flexible_interval_parts = explode('/', $flexible_interval);
			$flexible_delay = timeUnitToSeconds($flexible_interval_parts[0]);

			$delay = max($delay, $flexible_delay);
		}

		if ($delay == 0 && $update_interval_parser->getIntervals(ITEM_DELAY_SCHEDULING)) {
			return null;
		}

		return $delay;
	}

}

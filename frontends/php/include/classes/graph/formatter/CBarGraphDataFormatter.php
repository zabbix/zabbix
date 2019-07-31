<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CBarGraphDataFormatter implements CDataFormatter {

	const MIN_BAR_GAP_WIDTH = 3;

	/**
	 * Get datasets with bar type separated by axis
	 *
	 * @param array $paths
	 * @param array $metrics
	 *
	 * @return array
	 */
	protected function getDatasetByBarType(array $paths, array $metrics) {
		$result = [];

		foreach ($metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_BAR && array_key_exists($index, $paths)) {
				$result[$metric['options']['axisy']][] = $paths[$index][0];
			}
		}

		return $result;
	}

	/**
	 * Get unique timeline values and dataset count
	 *
	 * @param array $paths
	 * @param array $metrics
	 *
	 * @return array
	 */
	protected function getTimelineValues(array $paths, array $metrics) {
		$result = [];

		$datasets = $this->getDatasetByBarType($paths, $metrics);

		foreach ($datasets as $axis => $value) {
			$result[$axis] = [];

			foreach ($value as $dataset) {
				foreach ($dataset as $val) {
					if (!array_key_exists((int)$val[0], $result[$axis])) {
						$result[$axis][$val[0]] = 0;
					}

					$result[$axis][$val[0]]++;
				}
			}
		}

		return $result;
	}

	/**
	 * Get minimal bar width by datasets on same axis
	 *
	 * @param array $paths
	 * @param array $metrics
	 *
	 * @return array
	 */
	protected function getBarWidth(array $paths, array $metrics) {
		$result = [];

		$datasets = $this->getDatasetByBarType($paths, $metrics);

		if (count($datasets) === 0) {
			return 0;
		}

		foreach ($datasets as $axis => $value) {
			$width = [];

			foreach ($value as $dataset) {
				list ($count, $first, $last) = [count($dataset), reset($dataset)[0], end($dataset)[0]];

				$graph_width = ($last - $first) / $count;
				$gap = self::MIN_BAR_GAP_WIDTH >= $graph_width ? 0 : $graph_width * 0.25;

				$graph_width -= $gap;

				if ($graph_width < self::MIN_BAR_GAP_WIDTH) {
					$graph_width = 2;
				}

				$width[] = $graph_width;
			}

			$result[$axis] = min($width);
		}

		return $result;
	}

	/**
	 * Format dataset for bar graph
	 *
	 * @param array $paths
	 * @param array $metrics
	 *
	 * @return array
	 */
	public function format(array $paths, array $metrics) {
		$width = $this->getBarWidth($paths, $metrics);
		$datatimes = $this->getTimelineValues($paths, $metrics);
		$indexes = [];

		foreach ($metrics as $index => $metric) {
			if (!array_key_exists($metric['options']['axisy'], $indexes)) {
				$indexes[$metric['options']['axisy']] = [];
			}

			if ($metric['options']['type'] == SVG_GRAPH_TYPE_BAR && array_key_exists($index, $paths)) {
				foreach ($paths[$index][0] as $key => $value) {
					$count = $datatimes[$metric['options']['axisy']][$value[0]];
					$axis_width = $width[$metric['options']['axisy']];

					if (!array_key_exists((int)$value[0], $indexes[$metric['options']['axisy']])) {
						$indexes[$metric['options']['axisy']][$value[0]] = 0;
					}

					if ($count > 1) {
						$paths[$index][0][$key] = [
							$value[0] + (($axis_width / $count) * $indexes[$metric['options']['axisy']][$value[0]]),
							$value[1],
							$value[2],
							$axis_width / $count,
							$value[0]
						];

						$indexes[$metric['options']['axisy']][$value[0]]++;
					}
					else {
						$paths[$index][0][$key] = array_merge($value, [$axis_width, $value[0]]);
					}
				}
			}
		}

		return $paths;
	}
}

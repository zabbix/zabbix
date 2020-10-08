<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Class for converting template screens to template dashboards.
 */
class CTemplateScreenConverter extends CConverter {

	/**
	 * Reference display width for widget position and size calculations.
	 */
	private const DISPLAY_WIDTH = 1920;

	/**
	 * Widget row height on dashboard.
	 */
	private const WIDGET_ROW_HEIGHT = 70;

	/**
	 * Average height of screen legend.
	 */
	private const SCREEN_LEGEND_HEIGHT = 215;

	/**
	 * Convert template screen definition to template dashboard definition.
	 *
	 * @param array $screen
	 *
	 * @return array
	 */
	public function convert($screen): array {
		$widgets = [];

		$screen_items = array_key_exists('screen_items', $screen)
			? self::getValidScreenItems($screen['screen_items'])
			: [];

		if ($screen_items) {
			$screen_items = self::normalizePositions($screen_items);

			[$dimensions_x, $dimensions_y] = self::getDashboardDimensions($screen_items);

			$grid_x = [0];
			foreach ($dimensions_x as $index => $size) {
				$grid_x[$index + 1] = ($index == 0) ? $size : $grid_x[$index] + $size;
			}

			$grid_y = [0];
			foreach ($dimensions_y as $index => $size) {
				$grid_y[$index + 1] = ($index == 0) ? $size : $grid_y[$index] + $size;
			}

			foreach ($screen_items as $item) {
				$widget_pos = [
					'x' => $grid_x[$item['x']],
					'y' => $grid_y[$item['y']],
					'width' => $grid_x[$item['x'] + $item['colspan']] - $grid_x[$item['x']],
					'height' => $grid_y[$item['y'] + $item['rowspan']] - $grid_y[$item['y']]
				];

				// Skip screen items not fitting on dashboard.
				if (($widget_pos['x'] + $widget_pos['width'] > DASHBOARD_MAX_COLUMNS)
						|| ($widget_pos['y'] + $widget_pos['height'] > DASHBOARD_MAX_ROWS)) {
					continue;
				}

				$widget = self::makeWidget($item);
				$widget += [
					'x' => (string) $widget_pos['x'],
					'y' => (string) $widget_pos['y'],
					'width' => (string) $widget_pos['width'],
					'height' => (string) $widget_pos['height']
				];

				$widgets[] = $widget;
			}
		}

		$dashboard = [
			'name' => $screen['name']
		];

		if ($widgets) {
			$dashboard['widgets'] = $widgets;
		}

		return $dashboard;
	}

	/**
	 * Filter screen items by valid resourcetype.
	 *
	 * @static
	 *
	 * @param array $screen_items
	 *
	 * @return array  valid screen items
	 */
	private static function getValidScreenItems(array $screen_items): array {
		return array_filter($screen_items, function(array $screen_item): bool {
			return in_array($screen_item['resourcetype'], [SCREEN_RESOURCE_CLOCK, SCREEN_RESOURCE_GRAPH,
				SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_LLD_GRAPH, SCREEN_RESOURCE_LLD_SIMPLE_GRAPH,
				SCREEN_RESOURCE_PLAIN_TEXT, SCREEN_RESOURCE_URL
			]);
		});
	}

	/**
	 * Remove empty rows and columns and simplify rowspan and colspan usage in the screen items.
	 *
	 * @static
	 *
	 * @param array $screen_items
	 *
	 * @return array  Optimized screen items.
	 */
	private static function normalizePositions(array $screen_items): array {
		$used_x = [];
		$used_y = [];
		$keep_x = [];
		$keep_y = [];

		foreach ($screen_items as $screen_item) {
			$used_x += array_fill((int) $screen_item['x'], (int) $screen_item['colspan'], true);
			$used_y += array_fill((int) $screen_item['y'], (int) $screen_item['rowspan'], true);
			$keep_x[$screen_item['x']] = true;
			$keep_x[$screen_item['x'] + $screen_item['colspan']] = true;
			$keep_y[$screen_item['y']] = true;
			$keep_y[$screen_item['y'] + $screen_item['rowspan']] = true;
		}

		for ($x = max(array_keys($keep_x)); $x >= 0; $x--) {
			if (array_key_exists($x, $keep_x) && array_key_exists($x, $used_x)) {
				continue;
			}

			foreach ($screen_items as &$screen_item) {
				if ($x < $screen_item['x']) {
					$screen_item['x']--;
				}

				if ($x > $screen_item['x'] && $x < $screen_item['x'] + $screen_item['colspan']) {
					$screen_item['colspan']--;
				}
			}
			unset($screen_item);
		}

		for ($y = max(array_keys($keep_y)); $y >= 0; $y--) {
			if (array_key_exists($y, $keep_y) && array_key_exists($y, $used_y)) {
				continue;
			}

			foreach ($screen_items as &$screen_item) {
				if ($y < $screen_item['y']) {
					$screen_item['y']--;
				}

				if ($y > $screen_item['y'] && $y < $screen_item['y'] + $screen_item['rowspan']) {
					$screen_item['rowspan']--;
				}
			}
			unset($screen_item);
		}

		return $screen_items;
	}

	/**
	 * Get final dashboard dimensions for screen table rows and columns.
	 *
	 * @static
	 *
	 * @param array $screen_items
	 *
	 * @return array  Dashboard dimensions
	 */
	private static function getDashboardDimensions(array $screen_items): array {
		$items_x_preferred = [];
		$items_y_preferred = [];
		$items_x_min = [];
		$items_y_min = [];

		foreach ($screen_items as $key => $screen_item) {
			$preferred_size = self::getPreferredWidgetSize($screen_item);
			$min_size = self::getMinWidgetSize((int) $screen_item['resourcetype']);

			$items_x_preferred[$key] = [
				'position' => (int) $screen_item['x'],
				'span' => (int) $screen_item['colspan'],
				'size' => max($preferred_size['width'], $min_size['width'])
			];
			$items_y_preferred[$key] = [
				'position' => (int) $screen_item['y'],
				'span' => (int) $screen_item['rowspan'],
				'size' => max($preferred_size['height'], $min_size['height'])
			];
			$items_x_min[$key] = [
				'position' => (int) $screen_item['x'],
				'span' => (int) $screen_item['colspan'],
				'size' => $min_size['width']
			];
			$items_y_min[$key] = [
				'position' => (int) $screen_item['y'],
				'span' => (int) $screen_item['rowspan'],
				'size' => $min_size['height']
			];
		}

		$dimensions_x_preferred = self::getAxisDimensions($items_x_preferred);
		$dimensions_x_min = self::getAxisDimensions($items_x_min);
		$dimensions_x = self::adjustAxisDimensions($dimensions_x_preferred, $dimensions_x_min, DASHBOARD_MAX_COLUMNS);

		$dimensions_y_preferred = self::getAxisDimensions($items_y_preferred);
		$dimensions_y_min = self::getAxisDimensions($items_y_min);

		if (array_sum($dimensions_y_preferred) > DASHBOARD_MAX_ROWS) {
			$dimensions_y = self::adjustAxisDimensions($dimensions_y_preferred, $dimensions_y_min, DASHBOARD_MAX_ROWS);
		}
		else {
			$dimensions_y = $dimensions_y_preferred;
		}

		return [$dimensions_x, $dimensions_y];
	}

	/**
	 * Get axis dimensions based on prepared items.
	 *
	 * @static
	 *
	 * @param array $items                Prepared items.
	 * @param int   $items[]['position']  Item starting position.
	 * @param int   $items[]['span']      Item cell span.
	 * @param int   $items[]['size']      Item inner size.
	 *
	 * @return array
	 */
	private static function getAxisDimensions(array $items): array {
		$blocks = [];

		foreach ($items as $key => $item) {
			$blocks[$key] = array_fill($item['position'], $item['span'], true);
		}

		$dimensions = [];

		while ($blocks) {
			uasort($blocks,
				function(array $block_a, array $block_b) use ($dimensions): int {
					$block_a_unsized_count = count(array_diff_key($block_a, $dimensions));
					$block_b_unsized_count = count(array_diff_key($block_b, $dimensions));

					return ($block_a_unsized_count <=> $block_b_unsized_count);
				}
			);

			$key = array_keys($blocks)[0];

			$block = $blocks[$key];

			$block_dimensions = array_intersect_key($dimensions, $block);
			$block_dimensions_sum = array_sum($block_dimensions);

			$block_unsized = array_diff_key($block, $dimensions);
			$block_unsized_count = count($block_unsized);

			$size_overflow = $items[$key]['size'] - $block_dimensions_sum;

			if ($block_unsized_count > 0) {
				foreach (array_keys($block_unsized) as $index) {
					$dimensions[$index] = max(1, round($size_overflow / $block_unsized_count));

					$size_overflow -= $dimensions[$index];
					$block_unsized_count--;
				}
			}
			elseif ($size_overflow > 0) {
				foreach (array_keys($block) as $index) {
					$factor = ($size_overflow + $block_dimensions_sum) / $block_dimensions_sum;

					$new_dimension = round($dimensions[$index] * $factor);

					$block_dimensions_sum -= $dimensions[$index];
					$size_overflow -= $new_dimension - $dimensions[$index];

					$dimensions[$index] = $new_dimension;
				}
			}

			unset($blocks[$key]);
		}

		ksort($dimensions, SORT_NUMERIC);

		return $dimensions;
	}

	/**
	 * Adjust axis dimensions to the target summary size whether possible.
	 *
	 * @static
	 *
	 * @param array $dimensions      Prefered axis dimensions.
	 * @param array $dimensions_min  Minimal axis dimensions.
	 * @param int   $target          Target summary size.
	 *
	 * @return array
	 */
	private static function adjustAxisDimensions(array $dimensions, array $dimensions_min, int $target): array {
		$dimensions_sum = array_sum($dimensions);

		while ($dimensions_sum != $target) {
			$potential_index = null;
			$potential_value = null;

			foreach ($dimensions as $index => $size) {
				$value = $size / $dimensions_min[$index];

				if ($potential_index === null
						|| ($dimensions_sum > $target && $value > $potential_value)
						|| ($dimensions_sum < $target && $value < $potential_value)) {
					$potential_index = $index;
					$potential_value = $value;
				}
			}

			// Further shrinking not possible?
			if ($dimensions_sum > $target && $dimensions[$potential_index] == $dimensions_min[$potential_index]) {
				break;
			}

			if ($dimensions_sum > $target) {
				$dimensions[$potential_index]--;
				$dimensions_sum--;
			}
			else {
				$dimensions[$potential_index]++;
				$dimensions_sum++;
			}
		}

		return $dimensions;
	}

	/**
	 * Get preferred widget size on dashboard for given screen item type and size.
	 *
	 * @static
	 *
	 * @param array $screen_item
	 *
	 * @return array
	 */
	private static function getPreferredWidgetSize(array $screen_item): array {
		$width = $screen_item['width'];
		$height = $screen_item['height'];

		// Convert graph inner height to outer height.
		if (in_array($screen_item['resourcetype'], [SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH,
				SCREEN_RESOURCE_LLD_GRAPH, SCREEN_RESOURCE_LLD_SIMPLE_GRAPH])) {
			// Transform graph screen item with height of at least 100px to occupy minimum 5 grid rows.
			$height += self::SCREEN_LEGEND_HEIGHT;
		}

		return self::limitWidgetSize([
			'width' => round($width / self::DISPLAY_WIDTH * DASHBOARD_MAX_COLUMNS),
			'height' => round($height / self::WIDGET_ROW_HEIGHT)
		]);
	}

	/**
	 * Get minimal widget size on dashboard for given screen item type.
	 *
	 * @static
	 *
	 * @param int $resourcetype
	 *
	 * @return array
	 */
	private static function getMinWidgetSize(int $resourcetype): array {
		switch ($resourcetype) {
			case SCREEN_RESOURCE_CLOCK:
				return [
					'width' => 1,
					'height' => 2
				];

			case SCREEN_RESOURCE_GRAPH:
			case SCREEN_RESOURCE_SIMPLE_GRAPH:
			case SCREEN_RESOURCE_LLD_GRAPH:
			case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
				return [
					'width' => 4,
					'height' => 4
				];

			case SCREEN_RESOURCE_PLAIN_TEXT:
			case SCREEN_RESOURCE_URL:
				return [
					'width' => 4,
					'height' => 2
				];
		}
	}

	/**
	 * Limit widget size not to exceed the size of dashboard.
	 *
	 * @static
	 *
	 * @param array $size
	 * @param int   $size['width']
	 * @param int   $size['height']
	 *
	 * @return array
	 */
	private static function limitWidgetSize(array $size): array {
		return [
			'width' => min(DASHBOARD_MAX_COLUMNS, max(1, $size['width'])),
			'height' => min(DASHBOARD_WIDGET_MAX_ROWS, max(DASHBOARD_WIDGET_MIN_ROWS, $size['height']))
		];
	}

	/**
	 * Make widget definition based on screen item.
	 *
	 * @static
	 *
	 * @param array $screen_item
	 *
	 * @return array  Full widget definition except the positional data.
	 */
	private static function makeWidget(array $screen_item): array {
		$widget = [
			'name' => '',
			'hide_header' => CXmlConstantName::NO
		];

		switch ($screen_item['resourcetype']) {
			case SCREEN_RESOURCE_CLOCK:
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_CLOCK;
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'time_type',
					'value' => $screen_item['style']
				];
				if ($screen_item['style'] == TIME_TYPE_HOST) {
					$widget['fields'][] = [
						'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM,
						'name' => 'itemid',
						'value' => $screen_item['resource']
					];
				}
				break;

			case SCREEN_RESOURCE_GRAPH:
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC;
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_GRAPH
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH,
					'name' => 'graphid',
					'value' => $screen_item['resource']
				];
				break;

			case SCREEN_RESOURCE_SIMPLE_GRAPH:
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC;
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM,
					'name' => 'itemid',
					'value' => $screen_item['resource']
				];
				break;

			case SCREEN_RESOURCE_LLD_GRAPH:
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE;
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
					'name' => 'graphid',
					'value' => $screen_item['resource']
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'columns',
					'value' => '1'
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'rows',
					'value' => '1'
				];
				break;

			case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE;
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
					'name' => 'itemid',
					'value' => $screen_item['resource']
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'columns',
					'value' => '1'
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'rows',
					'value' => '1'
				];
				break;

			case SCREEN_RESOURCE_PLAIN_TEXT:
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT;
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM,
					'name' => 'itemids',
					'value' => $screen_item['resource']
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'show_as_html',
					'value' => $screen_item['style']
				];
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'show_lines',
					'value' => $screen_item['elements']
				];
				break;

			case SCREEN_RESOURCE_URL:
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_URL;
				$widget['fields'][] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_STRING,
					'name' => 'url',
					'value' => $screen_item['url']
				];
				break;
		}

		return $widget;
	}
}

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
class CTemplateScreenConverter {

	/**
	 * Reference display width for widget position and size calculations.
	 */
	const DISPLAY_WIDTH = 1920;

	/**
	 * Widget row height on dashboards.
	 */
	const WIDGET_ROW_HEIGHT = 70;

	/**
	 * Template screen definition.
	 *
	 * @var array
	 */
	private $screen;

	/**
	 * @param array $screen  Template screen definition.
	 */
	public function __construct(array $screen) {
		$this->screen = $screen;
	}

	/**
	 * Convert template screen definition to template dashboard definition.
	 *
	 * @throws Exception whether unknown screen items are provided.
	 *
	 * @return array
	 */
	public function convertToTemplateDashboard() {
		$widgets = [];

		$screen_items = $this->screen['screen_items'];

		if ($screen_items) {
			$screen_items = self::normalizePositioning($screen_items);

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

				$key = 'widget';
				if (count($widgets) > 0) {
					$key .= count($widgets);
				}

				$widgets[$key] = $widget;
			}
		}

		$dashboard = [
			'name' => $this->screen['name']
		];

		if ($widgets) {
			$dashboard['widgets'] = $widgets;
		}

		return $dashboard;
	}

	/**
	 * Remove empty rows and columns and simplify rowspan and colspan usage in the screen items.
	 *
	 * @param array $screen_items
	 *
	 * @return array  Optimized screen items.
	 */
	private static function normalizePositioning(array $screen_items): array {
		$used_x = [];
		$used_y = [];
		$keep_x = [];
		$keep_y = [];

		foreach ($screen_items as $item) {
			$used_x += array_fill((int) $item['x'], (int) $item['colspan'], true);
			$used_y += array_fill((int) $item['y'], (int) $item['rowspan'], true);
			$keep_x[$item['x']] = true;
			$keep_x[$item['x'] + $item['colspan']] = true;
			$keep_y[$item['y']] = true;
			$keep_y[$item['y'] + $item['rowspan']] = true;
		}

		for ($x = max(array_keys($keep_x)); $x >= 0; $x--) {
			if (array_key_exists($x, $keep_x) && array_key_exists($x, $used_x)) {
				continue;
			}

			foreach ($screen_items as &$item) {
				if ($x < $item['x']) {
					$item['x']--;
				}
				if (($x > $item['x']) && ($x < $item['x'] + $item['colspan'])) {
					$item['colspan']--;
				}
			}
			unset($item);
		}

		for ($y = max(array_keys($keep_y)); $y >= 0; $y--) {
			if (array_key_exists($y, $keep_y) && array_key_exists($y, $used_y)) {
				continue;
			}

			foreach ($screen_items as &$item) {
				if ($y < $item['y']) {
					$item['y']--;
				}
				if (($y > $item['y']) && ($y < $item['y'] + $item['rowspan'])) {
					$item['rowspan']--;
				}
			}
			unset($item);
		}

		return $screen_items;
	}

	/**
	 * Get final dashboard dimensions for screen table rows and columns.
	 *
	 * @param array $screen_items
	 *
	 * @return array
	 */
	private static function getDashboardDimensions(array $screen_items): array {
		$items_x_preferred = [];
		$items_y_preferred = [];
		$items_x_min = [];
		$items_y_min = [];

		foreach ($screen_items as $key => $screen_item) {
			$preferred_size = self::getPreferredWidgetSizeOnDashboard($screen_item);
			$min_size = self::getMinWidgetSizeOnDashboard((int) $screen_item['resourcetype']);

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
		$dimensions_x_sum = array_sum($dimensions_x_preferred);

		if ($dimensions_x_sum <= DASHBOARD_MAX_COLUMNS * .75 || $dimensions_x_sum > DASHBOARD_MAX_COLUMNS) {
			$dimensions_x = self::adjustAxisDimensions($dimensions_x_preferred, $dimensions_x_min,
				DASHBOARD_MAX_COLUMNS
			);
		}
		else {
			$dimensions_x = $dimensions_x_preferred;
		}

		$dimensions_y_preferred = self::getAxisDimensions($items_y_preferred);
		$dimensions_y_min = self::getAxisDimensions($items_y_min);
		$dimensions_y_sum = array_sum($dimensions_y_preferred);

		if ($dimensions_y_sum > DASHBOARD_MAX_ROWS) {
			$dimensions_y = self::adjustAxisDimensions($dimensions_y_preferred, $dimensions_y_min,
				DASHBOARD_MAX_ROWS
			);
		}
		else {
			$dimensions_y = $dimensions_y_preferred;
		}

		return [$dimensions_x, $dimensions_y];
	}

	/**
	 * Get axis dimensions based on prepared items.
	 *
	 * @param array $items                Prepared items.
	 * @param array $items[]['position']  Item starting position.
	 * @param array $items[]['span']      Item cell span.
	 * @param array $items[]['size']      Item inner size.
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
	 * @param array $dimensions      Prefered axis dimensions.
	 * @param array $dimensions_min  Minimal axis dimensions.
	 * @param mixed $target          Target summary size.
	 *
	 * @return array
	 */
	private static function adjustAxisDimensions(array $dimensions, array $dimensions_min, int $target): array {
		$dimensions_sum = array_sum($dimensions);

		while ($dimensions_sum != $target) {
			$potential = [];
			foreach ($dimensions as $index => $size) {
				$potential[$index] = $size / $dimensions_min[$index];
			}

			if ($dimensions_sum > $target) {
				arsort($potential);
			}
			else {
				asort($potential);
			}

			$index = array_keys($potential)[0];

			// Further shrinking not possible?
			if (($dimensions_sum > $target) && ($dimensions[$index] == $dimensions_min[$index])) {
				break;
			}

			if ($dimensions_sum > $target) {
				$dimensions[$index]--;
				$dimensions_sum--;
			}
			else {
				$dimensions[$index]++;
				$dimensions_sum++;
			}
		}

		return $dimensions;
	}

	/**
	 * Get prefered widget size on dashboard for given screen item type and size.
	 *
	 * @param array $screen_item
	 *
	 * @return array
	 */
	private static function getPreferredWidgetSizeOnDashboard(array $screen_item): array {
		$width = $screen_item['width'];
		$height = $screen_item['height'];

		// SCREEN_RESOURCE_LLD_GRAPH, SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
		if (in_array($screen_item['resourcetype'], [20, 19])) {
			$width *= $screen_item['max_columns'];
		}

		return self::limitWidgetSize([
			'width' => round($width / self::DISPLAY_WIDTH * DASHBOARD_MAX_COLUMNS),
			'height' => round($height / self::WIDGET_ROW_HEIGHT)
		]);
	}

	/**
	 * Get minimal widget size on dashboard for given screen item type.
	 *
	 * @param array $screen_item
	 *
	 * @return array
	 */
	private static function getMinWidgetSizeOnDashboard(int $resourcetype): array {
		switch ($resourcetype) {
			case 7: // SCREEN_RESOURCE_CLOCK
				[$width, $height] = [1, 2];
				break;

			case 0: // SCREEN_RESOURCE_GRAPH
			case 1: // SCREEN_RESOURCE_SIMPLE_GRAPH
			case 20: // SCREEN_RESOURCE_LLD_GRAPH
			case 19: // SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
				[$width, $height] = [6, 3];
				break;

			case 3: // SCREEN_RESOURCE_PLAIN_TEXT
			case 11: // SCREEN_RESOURCE_URL
				[$width, $height] = [5, 2];
				break;

			default:
				throw new Exception();
		}

		return self::limitWidgetSize([
			'width' => $width,
			'height' => $height
		]);
	}

	/**
	 * Limit widget size not to exceed the size of dashboard.
	 *
	 * @param array $size
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
	 * @param array $screen_item
	 *
	 * @return array  Full widget definition except the positional data.
	 */
	private function makeWidget(array $screen_item): array {
		$widget = [
			'name' => '',
			'hide_header' => CXmlConstantName::NO
		];

		$fields = [];

		switch ($screen_item['resourcetype']) {
			case 7: // SCREEN_RESOURCE_CLOCK
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_CLOCK;
				$fields[] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'time_type',
					'value' => $screen_item['style']
				];
				if ($screen_item['style'] == TIME_TYPE_HOST) {
					$fields[] = [
						'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_HOST,
						'name' => 'value',
						'value' => $screen_item['resource']
					];
				}
				break;

			case 0: // SCREEN_RESOURCE_GRAPH
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC;
				array_push($fields, [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_GRAPH
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH,
					'name' => 'value',
					'value' => $screen_item['resource']
				]);
				break;

			case 1: // SCREEN_RESOURCE_SIMPLE_GRAPH
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC;
				array_push($fields, [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM,
					'name' => 'value',
					'value' => $screen_item['resource']
				]);
				break;

			case 20: // SCREEN_RESOURCE_LLD_GRAPH
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE;
				array_push($fields, [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
					'name' => 'value',
					'value' => $screen_item['resource']
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'columns',
					'value' => (string) self::limitGraphPrototypeColumns((int) $screen_item['max_columns'])
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'rows',
					'value' => '1'
				]);
				break;

			case 19: // SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE;
				array_push($fields, [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'source_type',
					'value' => (string) ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
					'name' => 'value',
					'value' => $screen_item['resource']
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'columns',
					'value' => (string) self::limitGraphPrototypeColumns((int) $screen_item['max_columns'])
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'rows',
					'value' => '1'
				]);
				break;

			case 3: // SCREEN_RESOURCE_PLAIN_TEXT
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT;
				array_push($fields, [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM,
					'name' => 'value',
					'value' => $screen_item['resource']
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'show_as_html',
					'value' => $screen_item['style']
				], [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
					'name' => 'show_lines',
					'value' => $screen_item['elements']
				]);
				break;

			case 11: // SCREEN_RESOURCE_URL
				$widget['type'] = CXmlConstantName::DASHBOARD_WIDGET_TYPE_URL;
				$fields[] = [
					'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_STRING,
					'name' => 'url',
					'value' => $screen_item['url']
				];
				break;

			default:
				throw new Exception();
		}

		if ($fields) {
			$widget['fields'] = [];

			foreach ($fields as $name => $field) {
				$key = 'field';
				if (count($widget['fields']) > 0) {
					$key .= count($widget['fields']);
				}

				$widget['fields'][$key] = $field;
			}
		}

		return $widget;
	}

	/**
	 * Limit number of columns for graph prototype widget.
	 *
	 * @param mixed $columns
	 *
	 * @return int
	 */
	private static function limitGraphPrototypeColumns(int $columns): int {
		return min(DASHBOARD_MAX_COLUMNS, max(1, $columns));
	}
}

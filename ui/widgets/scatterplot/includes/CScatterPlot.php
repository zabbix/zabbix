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

namespace Widgets\ScatterPlot\Includes;

use CMathHelper,
	CSvg,
	CSvgGraphAxis,
	CSvgGraphClipArea,
	CSvgGraphGrid,
	CSvgLine,
	CSvgTag,
	Exception;

class CScatterPlot extends CSvg {

	public const SVG_GRAPH_X_AXIS_HEIGHT = 20;

	public const SVG_GRAPH_Y_AXIS_LABEL_MARGIN_OUTER = 10;

	private ?int $canvas_x = null;
	private ?int $canvas_y = null;
	private ?int $canvas_width = null;
	private ?int $canvas_height = null;

	private array $graph_theme;

	/**
	 * Scatter plot metrics.
	 *
	 * @var array
	 */
	private array $metrics = [];

	/**
	 * Original graph points, calculated from metrics.
	 *
	 * @var array
	 */
	private array $points = [];

	/**
	 * Metric paths for points, unstacked lines and unstacked staircases, calculated from original points.
	 *
	 * @var array
	 */
	private array $paths = [];

	private bool $show_x_axis;
	private ?float $x_min;
	private bool $x_min_calculated = false;
	private ?float $x_max;
	private bool $x_max_calculated = false;
	private ?string $x_units;
	private ?int $x_power = null;
	private float|int|null $x_interval = null;
	private float|int|null $x_min_value = null;
	private float|int|null $x_max_value = null;

	private bool $show_y_axis;
	private ?float $y_min;
	private bool $y_min_calculated = false;
	private ?float $y_max;
	private bool $y_max_calculated = false;
	private ?string $y_units;
	private ?int $y_power = null;
	private float|int|null $y_interval = null;
	private float|int|null $y_min_value = null;
	private float|int|null $y_max_value = null;

	/**
	 * Value for graph left offset. Is used as width for left Y axis container.
	 *
	 * @var int
	 */
	private int $offset_left = 20;

	/**
	 * Value for graph right offset. Is used as width for right Y axis container.
	 *
	 * @var int
	 */
	private int $offset_right = 20;

	/**
	 * Maximum width of container for every Y axis.
	 *
	 * @var int
	 */
	private int $max_yaxis_width = 120;

	private int $cell_height_min = 30;

	/**
	 * Height for X axis container.
	 *
	 * @var int
	 */
	private int $xaxis_height = 20;

	/**
	 * SVG default size.
	 */
	protected $width = 1000;
	protected $height = 1000;

	public function __construct(array $options) {
		parent::__construct();

		$this->graph_theme = getUserGraphTheme();

		$this->show_x_axis = $options['axes']['show_x_axis'];
		$this->x_min = $options['axes']['x_axis_min'];
		$this->x_max = $options['axes']['x_axis_max'];
		$this->x_units = $options['axes']['x_axis_units'] !== null
			? trim(preg_replace('/\s+/', ' ', $options['axes']['x_axis_units']))
			: null;

		$this->show_y_axis = $options['axes']['show_y_axis'];
		$this->y_min = $options['axes']['y_axis_min'];
		$this->y_max = $options['axes']['y_axis_max'];
		$this->y_units = $options['axes']['y_axis_units'] !== null
			? trim(preg_replace('/\s+/', ' ', $options['axes']['y_axis_units']))
			: null;

		$this->addClass(ZBX_STYLE_SVG_GRAPH);
	}

	public function getCanvasX(): int {
		return $this->canvas_x;
	}

	public function getCanvasY(): int {
		return $this->canvas_y;
	}

	public function getCanvasWidth(): int {
		return $this->canvas_width;
	}

	public function getCanvasHeight(): int {
		return $this->canvas_height;
	}

	public function addMetrics(array $metrics): self {
		foreach ($metrics as $index => $metric) {
			$this->metrics[$index] = [
				'data_set' => $metric['data_set'],
				'x_axis_name' => $metric['x_axis_items_name'],
				'y_axis_name' => $metric['y_axis_items_name'],
				'x_axis_items' => $metric['x_axis_items'],
				'y_axis_items' => $metric['y_axis_items'],
				'x_units' => $metric['x_units'],
				'y_units' => $metric['y_units'],
				'options' => ['order' => $index] + $metric['options']
			];

			if (!array_key_exists('points', $metric) || !$metric['points']) {
				continue;
			}

			$this->metrics[$index]['points'] = $metric['points'];
			$this->points[$index] = $metric['points'];
		}

		return $this;
	}

	/**
	 * Add UI helper line that follows mouse.
	 *
	 * @return self
	 */
	public function addHelper(): self {
		$this->addItem((new CSvgLine(0, 0, 0, 0))->addClass(CSvgTag::ZBX_STYLE_GRAPH_HELPER));

		return $this;
	}

	/**
	 * @throws Exception
	 */
	public function draw(): self {
		$this->calculateDimensions();

		if ($this->canvas_width > 0 && $this->canvas_height > 0) {
			$this->calculatePaths();

			$this->drawGrid();
			$this->drawYAxes();
			$this->drawXAxis();

			$this->drawMetricsPoint();

			$this->addClipArea();
		}

		return $this;
	}

	/**
	 * Calculate minimal and maximum values, canvas size, margins and offsets for graph canvas inside SVG element.
	 */
	private function calculateDimensions(): void {
		foreach ($this->metrics as $index => $metric) {
			if (array_key_exists($index, $this->points)) {
				$min_max = [
					'x_axis' => [
						'min' => null,
						'max' => null
					],
					'y_axis' => [
						'min' => null,
						'max' => null
					]
				];

				foreach ($this->points[$index] as $point) {
					foreach (['x_axis', 'y_axis'] as $axis) {
						$point_min = $point[$axis];
						$point_max = $point[$axis];

						if ($min_max[$axis]['min'] === null || $min_max[$axis]['min'] > $point_min) {
							$min_max[$axis]['min'] = (float) $point_min;
						}
						if ($min_max[$axis]['max'] === null || $min_max[$axis]['max'] < $point_max) {
							$min_max[$axis]['max'] = (float) $point_max;
						}
					}
				}
			}
			else {
				continue;
			}

			if ($this->x_min_value === null || $this->x_min_value > $min_max['x_axis']['min']) {
				$this->x_min_value = $min_max['x_axis']['min'];
			}
			if ($this->x_max_value === null || $this->x_max_value < $min_max['x_axis']['max']) {
				$this->x_max_value = $min_max['x_axis']['max'];
			}

			if ($this->y_min_value === null || $this->y_min_value > $min_max['y_axis']['min']) {
				$this->y_min_value = $min_max['y_axis']['min'];
			}
			if ($this->y_max_value === null || $this->y_max_value < $min_max['y_axis']['max']) {
				$this->y_max_value = $min_max['y_axis']['max'];
			}
		}

		// Canvas height must be specified before call self::getValuesGridWithPosition.

		$offset_top = 10;
		$offset_bottom = self::SVG_GRAPH_X_AXIS_HEIGHT;
		$this->canvas_height = max(0, $this->height - $offset_top - $offset_bottom);
		$this->canvas_y = $offset_top;

		// Determine units for x axis.

		if ($this->x_units === null) {
			$this->x_units = $this->metrics ? reset($this->metrics)['x_units'] : '';
		}

		// Determine units for y axis.

		if ($this->y_units === null) {
			$this->y_units = $this->metrics ? reset($this->metrics)['y_units'] : '';
		}

		$this->x_min_calculated = $this->x_min === null;
		$this->x_max_calculated = $this->x_max === null;

		$this->y_min_calculated = $this->y_min === null;
		$this->y_max_calculated = $this->y_max === null;

		if ($this->x_min_calculated) {
			$this->x_min = $this->x_min_value ?: 0;
		}

		if ($this->x_max_calculated) {
			$this->x_max = $this->x_max_value ?: 1;
		}

		if ($this->y_min_calculated) {
			$this->y_min = $this->y_min_value ?: 0;
		}

		if ($this->y_max_calculated) {
			$this->y_max = $this->y_max_value ?: 1;
		}

		// Calculate scale extremes for Y axis
		$calc_power = $this->y_units === '' || $this->y_units[0] !== '!';

		$rows_min = (int) max(1, floor($this->canvas_height / $this->cell_height_min / 1.5));
		$rows_max = (int) max(1, floor($this->canvas_height / $this->cell_height_min));

		$result = calculateGraphScaleExtremes($this->y_min, $this->y_max, $this->y_units, $calc_power,
			$this->y_min_calculated, $this->y_max_calculated, $rows_min, $rows_max
		);

		[
			'min' => $this->y_min,
			'max' => $this->y_max,
			'interval' => $this->y_interval,
			'power' => $this->y_power
		] = $result;

		// Define canvas dimensions and offsets, except canvas height and bottom offset.
		if ($this->show_y_axis) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, !$this->points);

			if ($values) {
				$approx_width = 0;

				foreach ($values as $value) {
					$approx_width = max($approx_width, imageTextSize(11, 0, $value)['width']);
				}

				$this->offset_left = min($this->max_yaxis_width,
					max($this->offset_left, self::SVG_GRAPH_Y_AXIS_LABEL_MARGIN_OUTER + $approx_width)
				);
			}
		}

		// Calculate scale extremes for X axis
		$calc_power = $this->x_units === '' || $this->x_units[0] !== '!';

		$this->canvas_width = max(0, $this->width - $this->offset_left - $this->offset_right);
		$this->canvas_x = $this->offset_left;

		$rows_min = (int) max(1, floor($this->canvas_width / 150));
		$rows_max = (int) max(1, floor($this->canvas_width / 100));

		$result = calculateGraphScaleExtremes($this->x_min, $this->x_max, $this->x_units, $calc_power,
			$this->x_min_calculated, $this->x_max_calculated, $rows_min, $rows_max
		);

		[
			'min' => $this->x_min,
			'max' => $this->x_max,
			'interval' => $this->x_interval,
			'power' => $this->x_power
		] = $result;
	}

	/**
	 * Calculate paths for metric elements.
	 */
	private function calculatePaths(): void {
		foreach ($this->metrics as $index => $metric) {
			if (!array_key_exists($index, $this->points)) {
				continue;
			}

			$min_max = [
				'x_axis' => [
					'min' => $this->x_min,
					'max' => $this->x_max
				],
				'y_axis' => [
					'min' => $this->y_min,
					'max' => $this->y_max
				]
			];

			$params = [
				'x_axis' => [
					'start_position' => $this->canvas_x + $this->canvas_width,
					'size' => -1 * $this->canvas_width
				],
				'y_axis' => [
					'start_position' => $this->canvas_y,
					'size' => $this->canvas_height
				]
			];

			$path_points = [];

			foreach ($this->points[$index] as $time => $point) {
				$coordinates = [];

				foreach ($params as $axis => $options) {
					$value = $point[$axis];

					if ($value < $min_max[$axis]['min'] || $value > $min_max[$axis]['max']) {
						continue 2;
					}

					if ($min_max[$axis]['max'] - $min_max[$axis]['min'] == INF) {
						$coordinates[$axis] = $options['start_position'] + CMathHelper::safeMul([
							$options['size'],
							$min_max[$axis]['max'] / 10 - $value / 10,
							1 / ($min_max[$axis]['max'] / 10 - $min_max[$axis]['min'] / 10)
						]);
					}
					else {
						$coordinates[$axis] = $options['start_position'] + CMathHelper::safeMul([
							$options['size'],
							$min_max[$axis]['max'] - $value,
							1 / ($min_max[$axis]['max'] - $min_max[$axis]['min'])
						]);
					}
				}

				$path_points[] = [
					(int) ceil($coordinates['x_axis']),
					(int) ceil($coordinates['y_axis']),
					convertUnits([
						'value' => $point['x_axis'],
						'units' => $this->x_units
					]),convertUnits([
						'value' => $point['y_axis'],
						'units' => $this->y_units
					]),
					$point['color'],
					$time,
					$time + $metric['options']['aggregate_interval']
				];
			}

			$this->paths[$index] = $path_points;
		}
	}

	/**
	 * @throws Exception
	 */
	private function drawGrid(): void {
		$x_points = $this->show_x_axis ? $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_BOTTOM, !$this->points) : [];

		$y_points = [];

		if ($this->show_y_axis) {
			$y_points = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, !$this->points);
		}

		$this->addItem(
			(new CSvgGraphGrid($y_points, $x_points))
				->setPosition($this->canvas_x, $this->canvas_y)
				->setSize($this->canvas_width, $this->canvas_height)
				->setColor('#'.$this->graph_theme['gridcolor'])
		);
	}

	private function drawYAxes(): void {
		if (!$this->show_y_axis) {
			return;
		}

		$grid_values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, !$this->points);

		$this->addItem(
			(new CSvgGraphAxis($grid_values, GRAPH_YAXIS_SIDE_LEFT))
				->setPosition($this->canvas_x - $this->offset_left, $this->canvas_y)
				->setSize($this->offset_left, $this->canvas_height)
				->setLineColor('#'.$this->graph_theme['gridcolor'])
				->setTextColor('#'.$this->graph_theme['textcolor'])
		);
	}

	/**
	 * @throws Exception
	 */
	private function drawXAxis(): void {
		if (!$this->show_x_axis) {
			return;
		}

		$grid_values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_BOTTOM, !$this->points);

		$this->addItem(
			(new CSvgGraphAxis($grid_values,GRAPH_YAXIS_SIDE_BOTTOM))
				->setPosition($this->canvas_x, $this->canvas_y + $this->canvas_height)
				->setSize($this->canvas_width, $this->xaxis_height)
				->setLineColor('#'.$this->graph_theme['gridcolor'])
				->setTextColor('#'.$this->graph_theme['textcolor'])
		);
	}

	private function drawMetricsPoint(): void {
		foreach ($this->metrics as $index => $metric) {
			if (array_key_exists($index, $this->paths)) {
				foreach ($this->paths[$index] as $key => $path) {
					$this->addItem(new CScatterPlotMetricPoint($path, $metric + ['key' => $key]));
				}
			}
		}
	}

	/**
	 * Add dynamic clip path to hide metric lines and area outside graph canvas.
	 */
	private function addClipArea(): void {
		$this->addItem(
			(new CSvgGraphClipArea(uniqid('metric_clip_', true)))
				->setPosition($this->canvas_x, $this->canvas_y)
				->setSize($this->canvas_width, $this->canvas_height)
		);
	}

	/**
	 * Get array of X/Y points with labels, for grid and X/Y axes. Array key is Y coordinate for SVG, value is label
	 * with axis units.
	 *
	 * @param int  $axis       Axis for which to get the values.
	 * @param bool $empty_set  Return defaults for empty side.
	 *
	 * @return array
	 */
	private function getValuesGridWithPosition(int $axis, bool $empty_set = false): array {
		$min = 0;
		$max = 1;
		$min_calculated = true;
		$max_calculated = true;
		$interval = 1;
		$units = '';
		$power = 0;

		if (!$empty_set) {
			if ($axis == GRAPH_YAXIS_SIDE_LEFT) {
				$min = $this->y_min;
				$max = $this->y_max;
				$min_calculated = $this->y_min_calculated;
				$max_calculated = $this->y_max_calculated;
				$interval = $this->y_interval;
				$units = $this->y_units;
				$power = $this->y_power;
			}
			else {
				$min = $this->x_min;
				$max = $this->x_max;
				$min_calculated = $this->x_min_calculated;
				$max_calculated = $this->x_max_calculated;
				$interval = $this->x_interval;
				$units = $this->x_units;
				$power = $this->x_power;
			}
		}

		$relative_values = calculateGraphScaleValues($min, $max, $min_calculated, $max_calculated, $interval,
			$units, $power, 14
		);

		$absolute_values = [];

		$size = $axis == GRAPH_YAXIS_SIDE_LEFT
			? $this->canvas_height
			: $this->canvas_width;

		foreach ($relative_values as ['relative_pos' => $relative_pos, 'value' => $value]) {
			$absolute_values[(int) round($size * $relative_pos)] = $value;
		}

		return $absolute_values;
	}
}

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
 * General class for SVG Graph usage.
 */
class CSvgGraph extends CSvg {

	public const SVG_GRAPH_X_AXIS_HEIGHT = 20;
	public const SVG_GRAPH_DEFAULT_COLOR = '#b0af07';
	public const SVG_GRAPH_DEFAULT_TRANSPARENCY = 5;
	public const SVG_GRAPH_DEFAULT_POINTSIZE = 1;
	public const SVG_GRAPH_DEFAULT_LINE_WIDTH = 1;

	public const SVG_GRAPH_X_AXIS_LABEL_MARGIN = 5;
	public const SVG_GRAPH_Y_AXIS_LEFT_LABEL_MARGIN = 5;
	public const SVG_GRAPH_Y_AXIS_RIGHT_LABEL_MARGIN = 12;

	private $canvas_height;
	private $canvas_width;
	private $canvas_x;
	private $canvas_y;

	private $graph_theme;

	/**
	 * Array of graph metrics data.
	 *
	 * @var array
	 */
	private $metrics = [];

	/**
	 * Array of graph points data. Calculated from metrics data.
	 *
	 * @var array
	 */
	private $points = [];

	/**
	 * Array of metric paths. Where key is metric index from $metrics array.
	 *
	 * @var array
	 */
	private $paths = [];

	/**
	 * Array of graph problems to display.
	 *
	 * @var array
	 */
	private $problems = [];

	private $working_time;

	private $max_value_left;
	private $max_value_right;
	private $min_value_left;
	private $min_value_right;

	private $left_y_show = false;
	private $left_y_min;
	private $left_y_min_calculated;
	private $left_y_max;
	private $left_y_max_calculated;
	private $left_y_interval;
	private $left_y_units;
	private $left_y_is_binary;
	private $left_y_power;
	private $left_y_empty = true;

	private $right_y_show = false;
	private $right_y_min;
	private $right_y_min_calculated;
	private $right_y_max;
	private $right_y_max_calculated;
	private $right_y_interval;
	private $right_y_units;
	private $right_y_is_binary;
	private $right_y_power;
	private $right_y_empty;

	private $right_y_zero;
	private $left_y_zero;

	private $x_show;

	/**
	 * Value for graph left offset. Is used as width for left Y axis container.
	 *
	 * @var int
	 */
	private $offset_left = 20;

	/**
	 * Value for graph right offset. Is used as width for right Y axis container.
	 *
	 * @var int
	 */
	private $offset_right = 20;

	/**
	 * Maximum width of container for every Y axis.
	 *
	 * @var int
	 */
	private $max_yaxis_width = 120;

	private $cell_height_min = 30;

	private $time_from;
	private $time_till;

	/**
	 * Height for X axis container.
	 *
	 * @var int
	 */
	private $xaxis_height = 20;

	/**
	 * SVG default size.
	 */
	protected $width = 1000;
	protected $height = 1000;

	public function __construct(array $options) {
		parent::__construct();

		$this->graph_theme = getUserGraphTheme();

		$this
			->addClass(ZBX_STYLE_SVG_GRAPH)
			->setTimePeriod($options['time_period']['time_from'], $options['time_period']['time_to'])
			->setXAxis($options['x_axis'])
			->setYAxisLeft($options['left_y_axis'])
			->setYAxisRight($options['right_y_axis']);

		$this->working_time = true;
	}

	/**
	 * Get graph canvas X offset.
	 *
	 * @return int
	 */
	public function getCanvasX(): int {
		return $this->canvas_x;
	}

	/**
	 * Get graph canvas Y offset.
	 *
	 * @return int
	 */
	public function getCanvasY(): int {
		return $this->canvas_y;
	}

	/**
	 * Get graph canvas width.
	 *
	 * @return int
	 */
	public function getCanvasWidth(): int {
		return $this->canvas_width;
	}

	/**
	 * Get graph canvas height.
	 *
	 * @return int
	 */
	public function getCanvasHeight(): int {
		return $this->canvas_height;
	}

	/**
	 * Set problems data for graph.
	 *
	 * @param array $problems  Array of problems data.
	 */
	public function addProblems(array $problems): void {
		$this->problems = $problems;
	}

	/**
	 * Set metrics data for graph.
	 *
	 * @param array $metrics  Array of metrics data.
	 *
	 * @return CSvgGraph
	 */
	public function addMetrics(array $metrics = []): self {
		$metrics_for_each_axes = [
			GRAPH_YAXIS_SIDE_LEFT => 0,
			GRAPH_YAXIS_SIDE_RIGHT => 0
		];

		foreach ($metrics as $i => $metric) {
			$min_value = null;
			$max_value = null;

			if (array_key_exists('points', $metric)) {
				$metrics_for_each_axes[$metric['options']['axisy']]++;

				foreach ($metric['points'] as $point) {
					if ($min_value === null || $min_value > $point['value']) {
						$min_value = (float) $point['value'];
					}
					if ($max_value === null || $max_value < $point['value']) {
						$max_value = (float) $point['value'];
					}

					$this->points[$i][$point['clock']] = $point['value'];
				}

				if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
					if ($this->min_value_left === null || $this->min_value_left > $min_value) {
						$this->min_value_left = $min_value;
					}
					if ($this->max_value_left === null || $this->max_value_left < $max_value) {
						$this->max_value_left = $max_value;
					}
				}
				else {
					if ($this->min_value_right === null || $this->min_value_right > $min_value) {
						$this->min_value_right = $min_value;
					}
					if ($this->max_value_right === null || $this->max_value_right < $max_value) {
						$this->max_value_right = $max_value;
					}
				}
			}

			$this->metrics[$i] = [
				'name' => $metric['name'],
				'itemid' => $metric['itemid'],
				'units' => $metric['units'],
				'host' => $metric['hosts'][0],
				'options' => ['order' => $i] + $metric['options']
			];
		}

		$this->left_y_empty = ($metrics_for_each_axes[GRAPH_YAXIS_SIDE_LEFT] == 0);
		$this->right_y_empty = ($metrics_for_each_axes[GRAPH_YAXIS_SIDE_RIGHT] == 0);

		return $this;
	}

	/**
	 * Add UI selection box element to graph.
	 *
	 * @return CSvgGraph
	 */
	public function addSBox(): self {
		$this->addItem([
			(new CSvgRect(0, 0, 0, 0))->addClass('svg-graph-selection'),
			(new CSvgText(0, 0, ''))->addClass('svg-graph-selection-text')
		]);

		return $this;
	}

	/**
	 * Add UI helper line that follows mouse.
	 *
	 * @return CSvgGraph
	 */
	public function addHelper(): self {
		$this->addItem((new CSvgLine(0, 0, 0, 0))->addClass(CSvgTag::ZBX_STYLE_GRAPH_HELPER));

		return $this;
	}

	/**
	 * Set graph time period.
	 *
	 * @param int $time_from  Timestamp.
	 * @param int @time_till  Timestamp.
	 *
	 * @return CSvgGraph
	 */
	private function setTimePeriod(int $time_from, int $time_till): self {
		$this->time_from = $time_from;
		$this->time_till = $time_till;

		return $this;
	}

	/**
	 * Show or hide X axis.
	 *
	 * @param array $options
	 *
	 * @return CSvgGraph
	 */
	private function setXAxis(array $options): self {
		$this->x_show = ($options['show'] == SVG_GRAPH_AXIS_SHOW);

		return $this;
	}

	/**
	 * Set left side Y axis display options.
	 *
	 * @param array  $options
	 * @param int    $options['show']
	 * @param string $options['min']
	 * @param string $options['max']
	 * @param string $options['units']
	 *
	 * @return CSvgGraph
	 */
	private function setYAxisLeft(array $options): self {
		$this->left_y_show = ($options['show'] == SVG_GRAPH_AXIS_SHOW);

		if ($options['min'] !== '') {
			$this->left_y_min = $options['min'];
		}
		if ($options['max'] !== '') {
			$this->left_y_max = $options['max'];
		}
		if ($options['units'] !== null) {
			$units = trim(preg_replace('/\s+/', ' ', $options['units']));
			$this->left_y_units = htmlspecialchars($units);
		}

		return $this;
	}

	/**
	 * Set right side Y axis display options.
	 *
	 * @param array  $options
	 * @param int    $options['show']
	 * @param string $options['min']
	 * @param string $options['max']
	 * @param string $options['units']
	 *
	 * @return void
	 */
	private function setYAxisRight(array $options): void {
		$this->right_y_show = ($options['show'] == SVG_GRAPH_AXIS_SHOW);

		if ($options['min'] !== '') {
			$this->right_y_min = $options['min'];
		}
		if ($options['max'] !== '') {
			$this->right_y_max = $options['max'];
		}
		if ($options['units'] !== null) {
			$units = trim(preg_replace('/\s+/', ' ', $options['units']));
			$this->right_y_units = htmlspecialchars($units);
		}
	}

	/**
	 * Render graph.
	 *
	 * @throws Exception
	 * @return CSvgGraph
	 */
	public function draw(): self {
		$this->applyMissingDataFunc();
		$this->calculateDimensions();

		if ($this->canvas_width > 0 && $this->canvas_height > 0) {
			$this->calculatePaths();

			if ($this->working_time) {
				$this->drawWorkingTime();
			}

			$this->drawGrid();

			if ($this->left_y_show) {
				$this->drawLeftYaxis();
			}
			if ($this->right_y_show) {
				$this->drawRightYAxis();
			}
			if ($this->x_show) {
				$this->drawXAxis();
			}

			$this->drawMetricsLine();
			$this->drawMetricsPoint();
			$this->drawMetricsBar();

			$this->drawProblems();

			$this->addClipArea();
		}

		return $this;
	}

	/**
	 * Modifies metric data and Y value range according specified missing data function.
	 */
	private function applyMissingDataFunc(): void {
		foreach ($this->metrics as $index => $metric) {
			/**
			 * - Missing data points are calculated only between existing data points;
			 * - Missing data points are not calculated for SVG_GRAPH_TYPE_POINTS && SVG_GRAPH_TYPE_BAR metrics;
			 * - SVG_GRAPH_MISSING_DATA_CONNECTED is default behavior of SVG graphs, so no need to calculate anything
			 *   here.
			 */
			if (array_key_exists($index, $this->points)
					&& !in_array($metric['options']['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR])
					&& $metric['options']['missingdatafunc'] != SVG_GRAPH_MISSING_DATA_CONNECTED) {
				$points = &$this->points[$index];
				$missing_data_points = $this->getMissingData($points, $metric['options']['missingdatafunc']);

				// Sort according new clock times (array keys).
				$points += $missing_data_points;
				ksort($points);

				// Missing data function can change min value of Y axis.
				if ($missing_data_points
						&& $metric['options']['missingdatafunc'] == SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO) {
					if ($this->min_value_left > 0 && $metric['options']['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
						$this->min_value_left = 0;
					}
					elseif ($this->min_value_right > 0 && $metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
						$this->min_value_right = 0;
					}
				}
			}
		}
	}

	/**
	 * Calculate canvas size, margins and offsets for graph canvas inside SVG element.
	 */
	private function calculateDimensions(): void {
		// Canvas height must be specified before call self::getValuesGridWithPosition.

		$offset_top = 10;
		$offset_bottom = self::SVG_GRAPH_X_AXIS_HEIGHT;
		$this->canvas_height = $this->height - $offset_top - $offset_bottom;
		$this->canvas_y = $offset_top;

		// Determine units for left side.

		if ($this->left_y_units === null) {
			$this->left_y_units = '';
			foreach ($this->metrics as $metric) {
				if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
					$this->left_y_units = $metric['units'];
					break;
				}
			}
		}

		// Determine units for right side.

		if ($this->right_y_units === null) {
			$this->right_y_units = '';
			foreach ($this->metrics as $metric) {
				if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
					$this->right_y_units = $metric['units'];
					break;
				}
			}
		}

		// Calculate vertical scale parameters for left side.

		$rows_min = (int) max(1, floor($this->canvas_height / $this->cell_height_min / 1.5));
		$rows_max = (int) max(1, floor($this->canvas_height / $this->cell_height_min));

		$this->left_y_min_calculated = $this->left_y_min === null;
		$this->left_y_max_calculated = $this->left_y_max === null;

		if ($this->left_y_min_calculated) {
			$this->left_y_min = $this->min_value_left ?: 0;
		}
		if ($this->left_y_max_calculated) {
			$this->left_y_max = $this->max_value_left ?: 1;
		}

		$this->left_y_is_binary = $this->left_y_units === 'B' || $this->left_y_units === 'Bps';

		$calc_power = $this->left_y_units === '' || $this->left_y_units[0] !== '!';

		$result = calculateGraphScaleExtremes($this->left_y_min, $this->left_y_max, $this->left_y_is_binary,
			$calc_power, $this->left_y_min_calculated, $this->left_y_max_calculated, $rows_min, $rows_max
		);

		[
			'min' => $this->left_y_min,
			'max' => $this->left_y_max,
			'interval' => $this->left_y_interval,
			'power' => $this->left_y_power
		] = $result;

		// Calculate vertical scale parameters for right side.

		if ($this->left_y_min_calculated && $this->left_y_max_calculated) {
			$rows_min = $rows_max = $result['rows'];
		}

		$this->right_y_min_calculated = $this->right_y_min === null;
		$this->right_y_max_calculated = $this->right_y_max === null;

		if ($this->right_y_min_calculated) {
			$this->right_y_min = $this->min_value_right ?: 0;
		}
		if ($this->right_y_max_calculated) {
			$this->right_y_max = $this->max_value_right ?: 1;
		}

		$this->right_y_is_binary = $this->right_y_units === 'B' || $this->right_y_units === 'Bps';

		$calc_power = $this->right_y_units === '' || $this->right_y_units[0] !== '!';

		$result = calculateGraphScaleExtremes($this->right_y_min, $this->right_y_max, $this->right_y_is_binary,
			$calc_power, $this->right_y_min_calculated, $this->right_y_max_calculated, $rows_min, $rows_max
		);

		[
			'min' => $this->right_y_min,
			'max' => $this->right_y_max,
			'interval' => $this->right_y_interval,
			'power' => $this->right_y_power
		] = $result;

		// Define canvas dimensions and offsets, except canvas height and bottom offset.

		$approx_width = 10;

		if ($this->left_y_show) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, $this->left_y_empty);

			if ($values) {
				$offset_left = max($this->offset_left, max(array_map('strlen', $values)) * $approx_width);
				$this->offset_left = (int) min($offset_left, $this->max_yaxis_width);
			}
		}

		if ($this->right_y_show) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT, $this->right_y_empty);

			if ($values) {
				$offset_right = max($this->offset_right, max(array_map('strlen', $values)) * $approx_width);
				$offset_right += self::SVG_GRAPH_Y_AXIS_RIGHT_LABEL_MARGIN;
				$this->offset_right = (int) min($offset_right, $this->max_yaxis_width);
			}
		}

		$this->canvas_width = $this->width - $this->offset_left - $this->offset_right;
		$this->canvas_x = $this->offset_left;

		// Calculate vertical zero position.

		if ($this->left_y_max - $this->left_y_min == INF) {
			$this->left_y_zero = $this->canvas_y + $this->canvas_height
				* max(0, min(1, $this->left_y_max / 10 / ($this->left_y_max / 10 - $this->left_y_min / 10)));
		}
		else {
			$this->left_y_zero = $this->canvas_y + $this->canvas_height
				* max(0, min(1, $this->left_y_max / ($this->left_y_max - $this->left_y_min)));
		}

		if ($this->right_y_max - $this->right_y_min == INF) {
			$this->right_y_zero = $this->canvas_y + $this->canvas_height
				* max(0, min(1, $this->right_y_max / 10 / ($this->right_y_max / 10 - $this->right_y_min / 10)));
		}
		else {
			$this->right_y_zero = $this->canvas_y + $this->canvas_height
				* max(0, min(1, $this->right_y_max / ($this->right_y_max - $this->right_y_min)));
		}
	}

	/**
	 * Calculate paths for metric elements.
	 */
	private function calculatePaths(): void {
		// Metric having very big values of y outside visible area will fail to render.
		$y_max = 2 ** 16;
		$y_min = -$y_max;

		foreach ($this->metrics as $index => $metric) {
			if (!array_key_exists($index, $this->points)) {
				continue;
			}

			if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
				$min_value = $this->right_y_min;
				$max_value = $this->right_y_max;
			}
			else {
				$min_value = $this->left_y_min;
				$max_value = $this->left_y_max;
			}

			$time_range = ($this->time_till - $this->time_from) ?: 1;
			$timeshift = $metric['options']['timeshift'];
			$paths = [];

			$path_num = 0;
			foreach ($this->points[$index] as $clock => $point) {
				// If missing data function is SVG_GRAPH_MISSING_DATA_NONE, path should be split in multiple svg shapes.
				if ($point === null) {
					$path_num++;
					continue;
				}

				/**
				 * Avoid invisible data point drawing. Data sets of type != SVG_GRAPH_TYPE_POINTS cannot be skipped to
				 * keep shape unchanged.
				 */
				$in_range = ($max_value >= $point && $min_value <= $point);
				if ($in_range || $metric['options']['type'] != SVG_GRAPH_TYPE_POINTS) {
					$x = $this->canvas_x + $this->canvas_width
						- $this->canvas_width * ($this->time_till - $clock + $timeshift) / $time_range;

					if ($max_value - $min_value == INF) {
						$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
							$max_value / 10 - $point / 10, 1 / ($max_value / 10 - $min_value / 10)
						]);
					}
					else {
						$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
							$max_value - $point, 1 / ($max_value - $min_value)
						]);
					}

					if (!$in_range) {
						$y = ($point > $max_value) ? max($y_min, $y) : min($y_max, $y);
					}

					$paths[$path_num][] = [$x, ceil($y), convertUnits([
						'value' => $point,
						'units' => $metric['units']
					])];
				}
			}

			if ($paths) {
				$this->paths[$index] = $paths;
			}
		}
	}

	private function drawWorkingTime(): void {
		if (($this->time_till - $this->time_from) > SEC_PER_MONTH * 3) {
			return;
		}

		$config = [CSettingsHelper::WORK_PERIOD => CSettingsHelper::get(CSettingsHelper::WORK_PERIOD)];
		$config = CMacrosResolverHelper::resolveTimeUnitMacros([$config], [CSettingsHelper::WORK_PERIOD])[0];

		$periods = parse_period($config[CSettingsHelper::WORK_PERIOD]);
		if ($periods === null) {
			return;
		}

		$time_range = $this->time_till - $this->time_from;
		$points = [0];
		$start = find_period_start($periods, $this->time_from);
		while ($start < $this->time_till && $start > 0) {
			$end = find_period_end($periods, $start, $this->time_till);

			$points[] = floor(($start - $this->time_from) * $this->canvas_width / $time_range);
			$points[] = ceil(($end - $this->time_from) * $this->canvas_width / $time_range);

			$start = find_period_start($periods, $end);
		}

		$points[] = $this->canvas_width;

		$this->addItem(
			(new CSvgGraphWorkingTime($points))
				->setPosition($this->canvas_x, $this->canvas_y)
				->setSize($this->canvas_width, $this->canvas_height)
				->setColor('#'.$this->graph_theme['nonworktimecolor'])
		);
	}

	/**
	 * @throws Exception
	 */
	private function drawGrid(): void {
		$time_points = $this->x_show ? $this->getTimeGridWithPosition() : [];
		$value_points = [];

		if ($this->left_y_show) {
			$value_points = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, $this->left_y_empty);

			unset($time_points[0]);
		}
		elseif ($this->right_y_show) {
			$value_points = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT, $this->right_y_empty);

			unset($time_points[$this->canvas_width]);
		}

		if ($this->x_show) {
			unset($value_points[0]);
		}

		$this->addItem(
			(new CSvgGraphGrid($value_points, $time_points))
				->setPosition($this->canvas_x, $this->canvas_y)
				->setSize($this->canvas_width, $this->canvas_height)
				->setColor('#'.$this->graph_theme['gridcolor'])
		);
	}

	private function drawLeftYaxis(): void {
		$grid_values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, $this->left_y_empty);
		$this->addItem(
			(new CSvgGraphAxis($grid_values, GRAPH_YAXIS_SIDE_LEFT))
				->setPosition($this->canvas_x - $this->offset_left, $this->canvas_y)
				->setSize($this->offset_left, $this->canvas_height)
				->setLineColor('#'.$this->graph_theme['gridcolor'])
				->setTextColor('#'.$this->graph_theme['textcolor'])
		);
	}

	private function drawRightYAxis(): void {
		$grid_values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT, $this->right_y_empty);

		// Do not draw label at the bottom of right Y axis to avoid label averlapping with horizontal axis arrow.
		if (array_key_exists(0, $grid_values)) {
			unset($grid_values[0]);
		}

		$this->addItem(
			(new CSvgGraphAxis($grid_values, GRAPH_YAXIS_SIDE_RIGHT))
				->setPosition($this->canvas_x + $this->canvas_width, $this->canvas_y)
				->setSize($this->offset_right, $this->canvas_height)
				->setLineColor('#'.$this->graph_theme['gridcolor'])
				->setTextColor('#'.$this->graph_theme['textcolor'])
		);
	}

	/**
	 * @throws Exception
	 */
	private function drawXAxis(): void {
		$this->addItem(
			(new CSvgGraphAxis($this->getTimeGridWithPosition(), GRAPH_YAXIS_SIDE_BOTTOM))
				->setPosition($this->canvas_x, $this->canvas_y + $this->canvas_height)
				->setSize($this->canvas_width, $this->xaxis_height)
				->setLineColor('#'.$this->graph_theme['gridcolor'])
				->setTextColor('#'.$this->graph_theme['textcolor'])
		);
	}

	private function drawMetricsLine(): void {
		foreach ($this->metrics as $index => $metric) {
			if (array_key_exists($index, $this->paths)
					&& in_array($metric['options']['type'], [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_STAIRCASE])) {

				if ($metric['options']['fill'] > 0) {
					$y_zero = $metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT
						? $this->right_y_zero
						: $this->left_y_zero;

					foreach ($this->paths[$index] as $path) {
						if (count($path) > 1) {
							$this->addItem(new CSvgGraphArea($path, $metric, $y_zero));
						}
					}
				}

				$this->addItem(new CSvgGraphLineGroup($this->paths[$index], $metric));
			}
		}
	}

	private function drawMetricsPoint(): void {
		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_POINTS && array_key_exists($index, $this->paths)) {
				$this->addItem(new CSvgGraphPoints(reset($this->paths[$index]), $metric));
			}
		}
	}

	private function drawMetricsBar(): void {
		$bar_min_width = [
			GRAPH_YAXIS_SIDE_LEFT => $this->canvas_width * .25,
			GRAPH_YAXIS_SIDE_RIGHT => $this->canvas_width * .25
		];
		$bar_groups_indexes = [];
		$bar_groups_position = [];

		foreach ($this->paths as $index => $path) {
			if ($this->metrics[$index]['options']['type'] == SVG_GRAPH_TYPE_BAR) {
				// If one second in displayed over multiple pixels, this shows number of px in second.
				$sec_per_px = ceil(($this->time_till - $this->time_from) / $this->canvas_width);
				$px_per_sec = ceil($this->canvas_width / ($this->time_till - $this->time_from));

				$y_axis_side = $this->metrics[$index]['options']['axisy'];
				$time_points = array_keys($this->points[$index]);
				$last_point = 0;
				$path = reset($path);

				foreach ($path as $point_index => $point) {
					$time_point = ($sec_per_px > $px_per_sec)
						? floor($time_points[$point_index] / $sec_per_px) * $sec_per_px
						: $time_points[$point_index];
					$bar_groups_indexes[$y_axis_side][$time_point][$index] = $point_index;
					$bar_groups_position[$y_axis_side][$time_point][$point_index] = $point[0];

					if ($last_point > 0) {
						$bar_min_width[$y_axis_side] = min($point[0] - $last_point, $bar_min_width[$y_axis_side]);
					}
					$last_point = $point[0];
				}
			}
		}

		foreach ($bar_groups_indexes as $y_axis => $points) {
			foreach ($points as $time_point => $paths) {
				$group_count = count($paths);
				$group_width = $bar_min_width[$y_axis];
				$bar_width = ceil($group_width / $group_count * .75);
				$group_index = 0;
				foreach ($paths as $path_index => $point_index) {
					$group_x = $bar_groups_position[$y_axis][$time_point][$point_index];
					if ($group_count > 1) {
						$this->paths[$path_index][0][$point_index][0] = $group_x
							// Calculate the leftmost X-coordinate including gap size.
							- $group_width * .375
							// Calculate the X-offset for each bar in the group.
							+ ceil($bar_width * ($group_index + .5));
						$group_index++;
					}
					$this->paths[$path_index][0][$point_index][3] = max(1, $bar_width);
					// X position for bars group.
					$this->paths[$path_index][0][$point_index][4] = $group_x - $group_width * .375;
				}
			}
		}

		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_BAR && array_key_exists($index, $this->paths)) {
				$metric['options']['y_zero'] = ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT)
					? $this->right_y_zero
					: $this->left_y_zero;
				$metric['options']['bar_width'] = $bar_min_width[$metric['options']['axisy']];

				$this->addItem(new CSvgGraphBar(reset($this->paths[$index]), $metric));
			}
		}
	}

	/**
	 * @throws Exception
	 */
	private function drawProblems(): void {
		$today = strtotime('today');
		$annotations = [];

		foreach ($this->problems as $problem) {
			// If problem is never recovered, it will be down till the end of graph or till current time.
			$time_to = $problem['r_clock'] == 0
				? min($this->time_till, time())
				: min($this->time_till, $problem['r_clock']);

			$time_range = $this->time_till - $this->time_from;

			$x1 = ceil($this->canvas_x + $this->canvas_width
				- $this->canvas_width * ($this->time_till - $problem['clock']) / $time_range);

			$x2 = floor($this->canvas_x + $this->canvas_width
				- $this->canvas_width * ($this->time_till - $time_to) / $time_range);

			// Make problem info.
			if ($problem['r_clock'] != 0) {
				$status_str = _('RESOLVED');
				$status_color = ZBX_STYLE_OK_UNACK_FG;
			}
			else {
				$status_str = _('PROBLEM');
				$status_color = ZBX_STYLE_PROBLEM_UNACK_FG;

				foreach ($problem['acknowledges'] as $acknowledge) {
					if ($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) {
						$status_str = _('CLOSING');
						$status_color = ZBX_STYLE_OK_UNACK_FG;
						break;
					}
				}
			}

			$clock_fmt = $problem['clock'] >= $today
				? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
				: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);

			if ($problem['r_clock'] != 0) {
				$r_clock_fmt = $problem['r_clock'] >= $today
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
			}
			else {
				$r_clock_fmt = '';
			}

			// At least 3 pixels expected to be occupied to show the range. Show simple annotation otherwise.
			$draw_type = ($x2 - $x1) > 2
				? CSvgGraphProblems::ANNOTATION_TYPE_RANGE
				: CSvgGraphProblems::ANNOTATION_TYPE_SIMPLE;

			// Draw borderlines. Make them dashed if beginning or ending of highlighted zone is visible in graph.
			if ($problem['clock'] > $this->time_from) {
				$draw_type |= CSvgGraphProblems::DASH_LINE_START;
			}

			if ($this->time_till > $time_to) {
				$draw_type |= CSvgGraphProblems::DASH_LINE_END;
			}

			$annotations[] = [
				'x' => max($x1, $this->canvas_x),
				'y' => $this->canvas_y,
				'width' => min($x2 - $x1, $this->canvas_width),
				'height' => $this->canvas_height,
				'draw_type' => $draw_type,
				'data_info' => json_encode([
					'name' => $problem['name'],
					'clock' => $clock_fmt,
					'r_clock' => $r_clock_fmt,
					'url' => (new CUrl('tr_events.php'))
						->setArgument('triggerid', $problem['objectid'])
						->setArgument('eventid', $problem['eventid'])
						->getUrl(),
					'r_eventid' => $problem['r_eventid'],
					'severity' => CSeverityHelper::getStyle((int) $problem['severity'], $problem['r_clock'] == 0),
					'status' => $status_str,
					'status_color' => $status_color
				])
			];
		}

		$this->addItem(new CSvgGraphProblems($annotations));
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
	 * Calculate missing data for given set of $points according given $missingdatafunc.
	 *
	 * @param array $points           Array of metric points to modify, where key is metric timestamp.
	 * @param int   $missingdatafunc  Type of function, allowed value:
	 *                                SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO, SVG_GRAPH_MISSING_DATA_NONE,
	 *                                SVG_GRAPH_MISSING_DATA_CONNECTED
	 *
	 * @return array  Array of calculated missing data points.
	 */
	private function getMissingData(array $points, int $missingdatafunc): array {
		// Get average distance between points to detect gaps of missing data.
		$prev_clock = null;
		$points_distance = [];
		foreach ($points as $clock => $point) {
			if ($prev_clock !== null) {
				$points_distance[] = $clock - $prev_clock;
			}
			$prev_clock = $clock;
		}

		/**
		 * $threshold          is a minimal period of time at what we assume that data point is missed;
		 * $average_distance   is an average distance between existing data points;
		 * $gap_interval       is a time distance between missing points used to fulfill gaps of missing data.
		 *                     It's unique for each gap.
		 */
		$average_distance = $points_distance ? array_sum($points_distance) / count($points_distance) : 0;
		$threshold = $points_distance ? $average_distance * 3 : 0;

		// Add missing values.
		$prev_clock = null;
		$missing_points = [];
		foreach ($points as $clock => $point) {
			if ($prev_clock !== null && ($clock - $prev_clock) > $threshold) {
				$gap_interval = floor(($clock - $prev_clock) / $threshold);

				if ($missingdatafunc == SVG_GRAPH_MISSING_DATA_NONE) {
					$missing_points[$prev_clock + $gap_interval] = null;
				}
				elseif ($missingdatafunc == SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO) {
					$missing_points[$prev_clock + $gap_interval] = 0;
					$missing_points[$clock - $gap_interval] = 0;
				}
			}

			$prev_clock = $clock;
		}

		return $missing_points;
	}

	/**
	 * Get array of X points with labels, for grid and X/Y axes. Array key is Y coordinate for SVG, value is label with
	 * axis units.
	 *
	 * @param int  $side       Type of Y axis: GRAPH_YAXIS_SIDE_RIGHT, GRAPH_YAXIS_SIDE_LEFT
	 * @param bool $empty_set  Return defaults for empty side.
	 *
	 * @return array
	 */
	private function getValuesGridWithPosition(int $side, bool $empty_set = false): array {
		$min = 0;
		$max = 1;
		$min_calculated = true;
		$max_calculated = true;
		$interval = 1;
		$units = '';
		$is_binary = false;
		$power = 0;

		if (!$empty_set) {
			if ($side === GRAPH_YAXIS_SIDE_LEFT) {
				$min = $this->left_y_min;
				$max = $this->left_y_max;
				$min_calculated = $this->left_y_min_calculated;
				$max_calculated = $this->left_y_max_calculated;
				$interval = $this->left_y_interval;
				$units = $this->left_y_units;
				$is_binary = $this->left_y_is_binary;
				$power = $this->left_y_power;
			}
			elseif ($side === GRAPH_YAXIS_SIDE_RIGHT) {
				$min = $this->right_y_min;
				$max = $this->right_y_max;
				$min_calculated = $this->right_y_min_calculated;
				$max_calculated = $this->right_y_max_calculated;
				$interval = $this->right_y_interval;
				$units = $this->right_y_units;
				$is_binary = $this->right_y_is_binary;
				$power = $this->right_y_power;
			}
		}

		$relative_values = calculateGraphScaleValues($min, $max, $min_calculated, $max_calculated, $interval, $units,
			$is_binary, $power, 14
		);

		$absolute_values = [];

		foreach ($relative_values as ['relative_pos' => $relative_pos, 'value' => $value]) {
			$absolute_values[(int) round($this->canvas_height * $relative_pos)] = $value;
		}

		return $absolute_values;
	}

	/**
	 * Return array of horizontal labels with positions. Array key will be position, value will be labeled.
	 *
	 * @throws Exception
	 * @return array
	 */
	private function getTimeGridWithPosition(): array {
		$period = $this->time_till - $this->time_from;
		$step = round($period / $this->canvas_width * 100); // Grid cell (100px) in seconds.

		/*
		 * In case if requested time period is so small that it is rounded to zero, we are displaying only two
		 * milestones on X axis - the start and the end of period.
		 */
		if ($step == 0) {
			return [
				0 => zbx_date2str(TIME_FORMAT_SECONDS, $this->time_from),
				$this->canvas_width => zbx_date2str(TIME_FORMAT_SECONDS, $this->time_till)
			];
		}

		$start = $this->time_from + $step - $this->time_from % $step;
		$time_formats = [
			SVG_GRAPH_DATE_FORMAT,
			SVG_GRAPH_DATE_FORMAT_SHORT,
			SVG_GRAPH_DATE_TIME_FORMAT_SHORT,
			TIME_FORMAT,
			TIME_FORMAT_SECONDS
		];

		// Search for most appropriate time format.
		foreach ($time_formats as $fmt) {
			$grid_values = [];

			for ($clock = $start; $this->time_till >= $clock; $clock += $step) {
				$relative_pos = round($this->canvas_width - $this->canvas_width * ($this->time_till - $clock) / $period);
				$grid_values[$relative_pos] = zbx_date2str($fmt, $clock);
			}

			/**
			 * If at least two calculated time-strings are equal, proceed with next format. Do that as long as each date
			 * is different or there is no more time formats to test.
			 */
			if ($fmt === end($time_formats) || count(array_flip($grid_values)) == count($grid_values)) {
				break;
			}
		}

		return $grid_values;
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

	const SVG_GRAPH_X_AXIS_HEIGHT = 20;

	protected $canvas_height;
	protected $canvas_width;
	protected $canvas_x;
	protected $canvas_y;

	/**
	 * Problems annotation labels color.
	 *
	 * @var string
	 */
	protected $color_annotation = '#AA4455';

	/**
	 * Array of graph metrics data.
	 *
	 * @var array
	 */
	protected $metrics = [];

	/**
	 * Array of graph points data. Calculated from metrics data.
	 *
	 * @var array
	 */
	protected $points = [];

	/**
	 * Array of metric paths. Where key is metric index from $metrics array.
	 *
	 * @var array
	 */
	protected $paths = [];

	/**
	 * Array of graph problems to display.
	 *
	 * @var array
	 */
	protected $problems = [];

	protected $max_value_left = null;
	protected $max_value_right = null;
	protected $min_value_left = null;
	protected $min_value_right = null;

	protected $left_y_max = null;
	protected $left_y_min = null;
	protected $left_y_show = null;
	protected $left_y_units = null;

	protected $right_y_max = null;
	protected $right_y_min = null;
	protected $right_y_show = null;
	protected $right_y_units = null;

	protected $offset_bottom;

	/**
	 * Value for graph left offset. Is used as width for left Y axis container.
	 *
	 * @var int
	 */
	protected $offset_left = 20;

	/**
	 * Value for graph right offset. Is used as width for right Y axis container.
	 *
	 * @var int
	 */
	protected $offset_right = 20;

	/**
	 * Maximum width of container for every Y axis.
	 *
	 * @var int
	 */
	protected $max_yaxis_width = 120;

	protected $offset_top;
	protected $time_from;
	protected $time_till;

	protected $x_axis;

	/**
	 * Height for X axis container.
	 *
	 * @var int
	 */
	protected $xaxis_height = 20;

	/**
	 * SVG default size.
	 */
	protected $width = 1000;
	protected $height = 1000;

	public function __construct(array $options) {
		parent::__construct();

		$this
			->setTimePeriod($options['time_period']['time_from'], $options['time_period']['time_to'])
			->setXAxis(array_key_exists('x_axis', $options) && $options['x_axis'] > 0)
			->setYAxisRight(array_key_exists('right_y_axis', $options) ? $options['right_y_axis'] : false)
			->setYAxisLeft(array_key_exists('left_y_axis', $options) ? $options['left_y_axis'] : false);
	}

	/**
	 * Get graph canvas X offset.
	 *
	 * @return int
	 */
	public function getCanvasX() {
		return $this->canvas_x;
	}

	/**
	 * Get graph canvas Y offset.
	 *
	 * @return int
	 */
	public function getCanvasY() {
		return $this->canvas_y;
	}

	/**
	 * Get graph canvas width.
	 *
	 * @return int
	 */
	public function getCanvasWidth() {
		return $this->canvas_width;
	}

	/**
	 * Get graph canvas height.
	 *
	 * @return int
	 */
	public function getCanvasHeight() {
		return $this->canvas_height;
	}

	/**
	 * Set problems data for graph.
	 *
	 * @param array $problems  Array of problems data.
	 *
	 * @return CSvgGraph
	 */
	public function addProblems(array $problems = []) {
		$this->problems = $problems;

		return $this;
	}

	/**
	 * Set metrics data for graph.
	 *
	 * @param array $metrics  Array of metrics data.
	 *
	 * @return CSvgGraph
	 */
	public function addMetrics(array $metrics = []) {
		foreach ($metrics as $i => $metric) {
			$min_value = null;
			$max_value = null;

			if ($metric['points']) {
				foreach ($metric['points'] as $point) {
					if ($min_value === null || $min_value > $point['value']) {
						$min_value = $point['value'];
					}
					if ($max_value === null || $max_value < $point['value']) {
						$max_value = $point['value'];
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

				$this->metrics[$i] = [
					'name' => $metric['name'],
					'itemid' => $metric['itemid'],
					'units' => $metric['units'],
					'host' => $metric['hosts'][0],
					'options' => $metric['options']
				];
			}
		}

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
	public function setTimePeriod($time_from, $time_till) {
		$this->time_from = $time_from;
		$this->time_till = $time_till;

		return $this;
	}

	/**
	 * Set left side Y axis display options.
	 *
	 * @param array $options  Options array.
	 *
	 * @return CSvgGraph
	 */
	public function setYAxisLeft($options) {
		if ($options !== false) {
			$this->left_y_show = true;

			if (array_key_exists('min', $options)) {
				$this->left_y_min = $options['min'];
			}
			if (array_key_exists('max', $options)) {
				$this->left_y_max = $options['max'];
			}
			if (array_key_exists('units', $options)) {
				$this->left_y_units = $options['units'];
			}
		}

		return $this;
	}

	/**
	 * Set right side Y axis display options.
	 *
	 * @param array $options    Options array.
	 * @return CSvgGraph
	 */
	public function setYAxisRight($options) {
		if ($options !== false) {
			$this->right_y_show = true;

			if (array_key_exists('min', $options)) {
				$this->right_y_min = $options['min'];
			}
			if (array_key_exists('max', $options)) {
				$this->right_y_max = $options['max'];
			}
			if (array_key_exists('units', $options)) {
				$this->right_y_units = $options['units'];
			}
		}

		return $this;
	}

	/**
	 * Show or hide X axis.
	 *
	 * @param bool $state
	 *
	 * @return CSvgGraph
	 */
	public function setXAxis($state) {
		$this->x_axis = $state;

		return $this;
	}

	/**
	 * Return array of horizontal labels with positions. Array key will be position, value will be label.
	 *
	 * @return array
	 */
	public function getTimeGridWithPosition() {
		$period = $this->time_till - $this->time_from;
		// 100px is grid cell desired size.
		$time_interval = (100 * $period) / $this->canvas_width;
		$intervals = [
			1 => 'H:i:s',					// 1 second
			5 => 'H:i:s',					// 5 seconds
			10 => 'H:i:s',					// 10 seconds
			30 => 'H:i:s',					// 30 seconds
			SEC_PER_MIN => 'H:i',			// 1 minute
			SEC_PER_MIN * 2 => 'H:i',		// 2 minutes
			SEC_PER_MIN * 5 => 'H:i',		// 5 minutes
			SEC_PER_MIN * 15 => 'H:i',		// 15 minutes
			SEC_PER_MIN * 30 => 'H:i',		// 30 minutes
			SEC_PER_HOUR => 'H:i',			// 1 hours
			SEC_PER_HOUR * 3 => 'H:i',		// 3 hours
			SEC_PER_HOUR * 6 => 'H:i',		// 6 hours
			SEC_PER_HOUR * 12 => 'H:i',		// 12 hours
			SEC_PER_DAY => 'n-d',			// 1 day
			SEC_PER_WEEK => 'n-d',			// 1 week
			SEC_PER_WEEK * 2 => 'n-d',		// 2 weeks
			SEC_PER_MONTH => 'n-d',			// 30 days
			SEC_PER_MONTH * 3 => 'Y-n-d',	// 90 days
			SEC_PER_MONTH * 4 => 'Y-n-d',	// 120 days
			SEC_PER_MONTH * 6 => 'Y-n-d',	// 180 days
			SEC_PER_YEAR => 'Y-n-d',		// 1 year
			SEC_PER_YEAR * 2 => 'Y-n-d'		// 2 years
		];

		// Default inteval values.
		$distance = SEC_PER_YEAR * 5;
		$time_fmt = 'Y-n-d';
		$step = 0;

		foreach ($intervals as $interval => $format) {
			$time = abs($interval - $time_interval);

			if ($time < $distance) {
				$distance = $time;
				$step = $interval;
				$time_fmt = $format;
			}
		}

		$grid_values = [];
		$start = $this->time_from + $step - $this->time_from % $step;

		for ($clock = $start; $this->time_till >= $clock; $clock += $step) {
			$relative_pos = round($this->canvas_width - $this->canvas_width * ($this->time_till - $clock) / $period);
			$grid_values[$relative_pos] = date($time_fmt, $clock);
		}

		return $grid_values;
	}

	/**
	 * Add UI selection box element to graph.
	 *
	 * @return CSvgGraph
	 */
	public function addSBox() {
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
	public function addHelper() {
		$this->addItem((new CSvgLine(0, 0, 0, 0))->addClass(CSvgTag::ZBX_STYLE_GRAPH_HELPER));

		return $this;
	}

	/**
	 * Render graph.
	 *
	 * @return CSvgGraph
	 */
	public function draw() {
		$this->calculateDimensions();
		$this->calculatePaths();

		$this->drawGrid();

		if ($this->left_y_show) {
			$this->drawCanvasLeftYAxis();
		}
		if ($this->right_y_show && (!$this->left_y_show || $this->max_value_right !== null)) {
			$this->drawCanvasRightYAxis();
		}
		if ($this->x_axis) {
			$this->drawCanvasXAxis();
		}

		$this->drawMetricsArea();
		$this->drawMetricsLine();
		$this->drawMetricsPoint();

		$this->drawProblems();

		$this->addClipArea();

		return $this;
	}

	/**
	 * Add dynamic clip path to hide metric lines and area outside graph canvas.
	 */
	protected function addClipArea() {
		$areaid = uniqid('metric_clip_');

		// CSS styles.
		$this->styles['.'.CSvgTag::ZBX_STYLE_SVG_GRAPH_AREA]['clip-path'] = 'url(#'.$areaid.')';
		$this->styles['[data-metric]']['clip-path'] = 'url(#'.$areaid.')';

		$this->addItem(
			(new CsvgTag('clipPath'))
				->addItem(
					(new CSvgPath(implode(' ', [
						'M'.$this->canvas_x.','.($this->canvas_y - 10),
						'H'.($this->canvas_width + $this->canvas_x),
						'V'.($this->canvas_height + $this->canvas_y),
						'H'.($this->canvas_x)
					])))
				)
				->setAttribute('id', $areaid)
		);
	}

	/**
	 * Calculate canvas size, margins and offsets for graph canvas inside SVG element.
	 */
	protected function calculateDimensions() {
		// Set missing properties for left Y axis.
		if ($this->left_y_min === null) {
			$this->left_y_min = $this->min_value_left ? : 0;
		}
		if ($this->left_y_max === null) {
			$this->left_y_max = $this->max_value_left ? : 1;
		}
		if ($this->left_y_units === null) {
			$this->left_y_units = '';
			foreach ($this->metrics as $metric) {
				if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
					$this->left_y_units = $metric['units'];
					break;
				}
			}
		}

		// Set missing properties for right Y axis.
		if ($this->right_y_min === null) {
			$this->right_y_min = $this->min_value_right ? : 0;
		}
		if ($this->right_y_max === null) {
			$this->right_y_max = $this->max_value_right ? : 1;
		}

		if ($this->right_y_units === null) {
			$this->right_y_units = '';
			foreach ($this->metrics as $metric) {
				if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
					$this->right_y_units = $metric['units'];
					break;
				}
			}
		}

		// Define canvas dimensions and offsets, except canvas height and bottom offset.
		$approx_width = 10;

		if ($this->left_y_show) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT);

			if ($values) {
				$offset_left = max($this->offset_left, max(array_map('strlen', $values)) * $approx_width);
				$this->offset_left = (int) min($offset_left, $this->max_yaxis_width);
			}
		}

		if ($this->right_y_show) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT);

			if ($values) {
				$offset_right = max($this->offset_right, max(array_map('strlen', $values)) * $approx_width);
				$this->offset_right = (int) min($offset_right, $this->max_yaxis_width);
			}
		}

		$this->canvas_width = $this->width - $this->offset_left - $this->offset_right;
		$this->offset_top = 10;
		$this->canvas_x = $this->offset_left;
		$this->canvas_y = $this->offset_top;
		$this->offset_bottom = self::SVG_GRAPH_X_AXIS_HEIGHT;
		$this->canvas_height = $this->height - $this->offset_top - $this->offset_bottom;
	}

	/**
	 * Get array of X points with labels, for grid and X/Y axes. Array key is Y coordinate for SVG, value is label with
	 * axis units.
	 *
	 * @param int $side  Type of X axis: GRAPH_YAXIS_SIDE_RIGHT, GRAPH_YAXIS_SIDE_LEFT
	 *
	 * @return array
	 */
	protected function getValuesGridWithPosition($side) {
		if ($side === GRAPH_YAXIS_SIDE_LEFT) {
			$min_value = $this->left_y_min;
			$max_value = $this->left_y_max;
			$units = $this->left_y_units;
		}
		else {
			$min_value = $this->right_y_min;
			$max_value = $this->right_y_max;
			$units = $this->right_y_units;
		}

		$grid = $this->getValueGrid($min_value, $max_value);
		$delta = (($max_value - $min_value) ? : 1);
		$grid_values = [];

		foreach ($grid as $value) {
			$relative_pos = $this->canvas_height - $this->canvas_height * ($max_value - $value) / $delta;

			if ($relative_pos >= 0 && $relative_pos <= $this->canvas_height) {
				$grid_values[$relative_pos] = convert_units([
					'value' => $value,
					'units' => $units
				]);
			}
		}

		return $grid_values;
	}

	/**
	 * Add Y axis with labels to left side of graph.
	 */
	protected function drawCanvasLeftYaxis() {
		$this->addItem(
			(new CSvgGraphAxis($this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT), GRAPH_YAXIS_SIDE_LEFT))
				->setSize($this->offset_left, $this->canvas_height)
				->setPosition($this->canvas_x - $this->offset_left, $this->canvas_y)
		);
	}

	/**
	 * Add Y axis with labels to right side of graph.
	 */
	protected function drawCanvasRightYAxis() {
		$this->addItem(
			(new CSvgGraphAxis($this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT), GRAPH_YAXIS_SIDE_RIGHT))
				->setSize($this->offset_right, $this->canvas_height)
				->setPosition($this->canvas_x + $this->canvas_width, $this->canvas_y)
		);
	}

	/**
	 * Add X axis with labels to graph.
	 */
	protected function drawCanvasXAxis() {
		$this->addItem((new CSvgGraphAxis($this->getTimeGridWithPosition(), GRAPH_YAXIS_SIDE_BOTTOM))
			->setSize($this->canvas_width, $this->xaxis_height)
			->setPosition($this->canvas_x, $this->canvas_y + $this->canvas_height)
		);
	}

	/**
	 * Calculate array of points between $min and $max value.
	 *
	 * @param int $min  Minimum value.
	 * @param int $max  Maximum value.
	 *
	 * @return $array
	 */
	protected function getValueGrid($min, $max) {
		$res = [];

		for ($base = 10; $base > .01; $base /= 10) {
			$mul = $max ? 1 / pow($base, floor(log10($max))) : 1;
			$max10 = ceil($mul * $max) / $mul;
			$min10 = floor($mul * $min) / $mul;
			$delta = $max10 - $min10;
			$delta = ceil($mul * $delta) / $mul;

			if ($mul >= 1) {
				if ($delta) {
					for($i = 0; $delta >= $i; $i += $delta / 5) {
						$res[] = $i + $min10;
					}
				}
				else {
					$res[] = $min10;
				}
				break;
			}
		}

		return $res;
	}

	/**
	 * Add grid to graph.
	 */
	protected function drawGrid() {
		$time_points = $this->getTimeGridWithPosition();
		$value_points = [];

		if ($this->left_y_show) {
			$value_points = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT);

			unset($time_points[0]);
		}
		elseif ($this->right_y_show) {
			$value_points = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT);

			unset($time_points[$this->canvas_width]);
		}

		if ($this->x_axis) {
			unset($value_points[0]);
		}

		$this->addItem((new CSvgGraphGrid($value_points, $time_points))
			->setPosition($this->canvas_x, $this->canvas_y)
			->setSize($this->canvas_width, $this->canvas_height)
		);
	}


	/**
	 * Calculate paths for metric elements.
	 */
	protected function calculatePaths() {
		foreach ($this->metrics as $index => $metric) {
			if (($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT)) {
				$min_value = $this->right_y_min;
				$max_value = $this->max_value_right;
				$units = $this->right_y_units;
			}
			else {
				$min_value = $this->left_y_min;
				$max_value = $this->left_y_max;
				$units = $this->left_y_units;
			}

			$time_range = $this->time_till - $this->time_from ? : 1;
			$value_diff = $max_value - $min_value ? : 1;
			$timeshift = $metric['options']['timeshift'];
			$paths = [];

			if ($metric['options']['type'] != SVG_GRAPH_TYPE_POINTS) {
				$this->applyMissingDataFunc($this->points[$index], (int) $metric['options']['missingdatafunc']);
			}

			$path_num = 0;
			foreach ($this->points[$index] as $clock => $point) {
				// If missing data function is SVG_GRAPH_MISSING_DATA_NONE, path should be skipped in multiple svg shapes.
				if ($point === null) {
					$path_num++;
					continue;
				}

				$x = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $clock + $timeshift) / $time_range;
				$y = $this->canvas_y + $this->canvas_height * ($max_value - $point) / $value_diff;
				$paths[$path_num][] = [$x, $y, convert_units([
					'value' => $point,
					'units' => $units
				])];
			}

			$this->paths[$index] = $paths;
		}
	}

	/**
	 * Modifies metric data according $missingdatafunc value.
	 *
	 * @param array $points           Array of metric points to modify, where key is metric timestamp.
	 * @param int   $missingdatafunc  Type of function, allowed value:
	 *                                SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO, SVG_GRAPH_MISSING_DATA_NONE,
	 *                                SVG_GRAPH_MISSING_DATA_CONNECTED
	 */
	protected function applyMissingDataFunc(array &$points = [], $missingdatafunc) {
		if (!$points || $missingdatafunc == SVG_GRAPH_MISSING_DATA_CONNECTED) {
			/**
			 * SVG_GRAPH_MISSING_DATA_CONNECTED is default behavior of SVG graphs, so no need to calculate anything
			 * here. Points will be connected anyway.
			 */
			return;
		}

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
		 * $added_value        is a value that will be applied in time gaps that are longer than $threshold;
		 * $gap_interval       is a time distance between missing points used to fulfill gaps of missing data.
		 *                     It's unique for each gap.
		 */
		$average_distance = $points_distance ? array_sum($points_distance) / count($points_distance) : 0;
		$threshold = $points_distance ? $average_distance * 3 : 0;
		$missing_data_values = [
			SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO => 0,
			SVG_GRAPH_MISSING_DATA_NONE => null
		];

		// Add missing values.
		$prev_clock = null;
		$missing_points = [];
		foreach ($points as $clock => $point) {
			if ($prev_clock !== null && ($clock - $prev_clock) > $threshold) {
				$gap_interval = floor(($clock - $prev_clock) / $threshold);

				do {
					$prev_clock += $gap_interval;
					$missing_points[$prev_clock] = $missing_data_values[$missingdatafunc];
				}
				while ($clock > $prev_clock);
			}

			$prev_clock = $clock;
		}

		// Sort according new clock times.
		$points += $missing_points;
		ksort($points);
	}

	/**
	 * Add fill area to graph for metric of type SVG_GRAPH_TYPE_LINE or SVG_GRAPH_TYPE_STAIRCASE.
	 */
	protected function drawMetricsArea() {
		foreach ($this->metrics as $index => $metric) {
			if (($metric['options']['type'] == SVG_GRAPH_TYPE_LINE
					|| $metric['options']['type'] == SVG_GRAPH_TYPE_STAIRCASE) && $metric['options']['fill'] > 0) {
				foreach ($this->paths[$index] as $path) {
					$this->addItem((new CSvgGraphArea($path, $metric))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
					);
				}
			}
		}
	}

	/**
	 * Add line paths to graph for metric of type SVG_GRAPH_TYPE_LINE or SVG_GRAPH_TYPE_STAIRCASE.
	 */
	protected function drawMetricsLine() {
		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_LINE
					|| $metric['options']['type'] == SVG_GRAPH_TYPE_STAIRCASE) {
				$group = (new CSvgGroup())
					->setAttribute('data-set', $metric['options']['type'] == SVG_GRAPH_TYPE_LINE ? 'line' : 'staircase')
					->setAttribute('data-metric', $metric['name'])
					->setAttribute('data-color', $metric['options']['color']);

				foreach ($this->paths[$index] as $path) {
					$group->addItem((new CSvgGraphLine($path, $metric))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
					);
				}

				$this->addItem($group
					->addItem((new CSvgCircle(-6, -6, 12))->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
				));
			}
		}
	}

	/**
	 * Add metric of type points to graph.
	 */
	protected function drawMetricsPoint() {
		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_POINTS) {
				$this->addItem((new CSvgGraphPoints(reset($this->paths[$index]), $metric))
					->setPosition($this->canvas_x, $this->canvas_y)
					->setSize($this->canvas_width, $this->canvas_height)
				);
			}
		}
	}

	/**
	 * Add problems tooltip data to graph.
	 */
	protected function drawProblems() {
		$today = strtotime('today');
		$container = (new CSvgGroup())->addClass(CSvgTag::ZBX_STYLE_GRAPH_PROBLEMS);

		foreach ($this->problems as $problem) {
			// If problem is never recovered, it will be drown till the end of graph or till current time.
			$time_to =  ($problem['r_clock'] == 0) ? min($this->time_till, time()) : $problem['r_clock'];
			$time_range = $this->time_till - $this->time_from;
			$x1 = $this->canvas_width - $this->canvas_width * ($this->time_till - $problem['clock']) / $time_range;
			$x2 = $this->canvas_width - $this->canvas_width * ($this->time_till - $time_to) / $time_range;

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

			$info = [
				'name' => $problem['name'],
				'clock' => ($problem['clock'] >= $today)
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']),
				'r_clock' => ($problem['r_clock'] > 0)
					? ($problem['r_clock'] >= $today)
						? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
						: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock'])
					: '',
				'url' => (new CUrl('tr_events.php'))
					->setArgument('triggerid', $problem['objectid'])
					->setArgument('eventid', $problem['eventid'])
					->getUrl(),
				'r_eventid' => $problem['r_eventid'],
				'severity' => ($problem['r_clock'] == 0) ? getSeverityStyle($problem['severity']) : '',
				'status' => $status_str,
				'status_color' => $status_color
			];

			// At least 3 pixels expected to be occupied to show the range. Show simple anotation otherwise.
			$draw_type = ($x2 - $x1) > 2 ? CSvgGraphAnnotation::TYPE_RANGE : CSvgGraphAnnotation::TYPE_SIMPLE;

			// Draw border lines. Make them dashed if beginning or ending of highligted zone is visible in graph.
			if ($problem['clock'] >= $this->time_from) {
				$draw_type |= CSvgGraphAnnotation::DASH_LINE_START;
			}

			if ($this->time_till >= $time_to) {
				$draw_type |= CSvgGraphAnnotation::DASH_LINE_END;
			}

			$container->addItem(
				(new CSvgGraphAnnotation($draw_type))
					->setInformation(CJs::encodeJson($info))
					->setSize(min($x2 - $x1, $this->canvas_width), $this->canvas_height)
					->setPosition(max($x1, $this->canvas_x), $this->canvas_y)
					->setColor($this->color_annotation)
			);
		}

		$this->addItem($container);
	}
}

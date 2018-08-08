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

//	protected $annotations_range;
//	protected $annotations_simple;
	protected $canvas_height;
	protected $canvas_width;
	protected $canvas_x;
	protected $canvas_y;
	protected $color_annotation;
	protected $color_axis;
	protected $color_background;
	protected $color_grid;
	protected $color_metric;
	protected $color_legend;
	//protected $draw_color_palette;
	protected $draw_fill;
	protected $draw_line_width;
	protected $height;
	//protected $max_clock;
	protected $make_sbox;
	protected $max_value_left;
	protected $max_value_right;
	protected $metrics;
	protected $points;

	/**
	 * Array of metric paths. Where key is metric index from $metrics array.
	 *
	 * @var array
	 */
	protected $paths = [];

	//protected $min_clock;
	protected $min_value_left;
	protected $min_value_right;

	protected $legend_type;

	/**
	 * Count of lines used to show legend
	 *
	 * @var int
	 */
	protected $legend_lines = 2;

	/**
	 * Height of one line.
	 *
	 * @var int
	 */
	protected $legend_line_height = 20;

	protected $left_y_max;
	protected $left_y_min;
	protected $left_y_show;
	protected $left_y_units;

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
	 * Maximum width of container for every X axis.
	 *
	 * @var int
	 */
	protected $max_yaxis_width = 120;

	protected $offset_top;
	protected $problems;
	protected $time_from;
	protected $time_to;
	protected $width;

	protected $right_y_max;
	protected $right_y_min;
	protected $right_y_show;
	protected $right_y_units;

	protected $x_axis;

	/**
	 * Height for X axis container.
	 *
	 * @var int
	 */
	protected $xaxis_height = 20;

	public function __construct($width, $height, $options) {
		parent::__construct();

		$this->metrics = [];
		$this->points = [];
		$this->problems = [];

		$this->width = $width;
		$this->height = $height;

		$this->draw_line_width = 1;
		$this->draw_fill = 0.1;

		$this->legend_type = SVG_GRAPH_LEGEND_TYPE_NONE;
		$this->x_axis = false;

		$this->min_value_left = null;
		$this->max_value_left = null;
		$this->min_value_right = null;
		$this->max_value_right = null;

		$this->left_y_show = false;
		$this->left_y_min = null;
		$this->left_y_max = null;
		$this->left_y_units = null;

		$this->right_y_show = false;
		$this->right_y_min = null;
		$this->right_y_max = null;
		$this->right_y_units = null;

		$this->color_background = '#FFFFFF';
		$this->color_metric = '#00AA00';
		$this->color_grid = '#777777';
		$this->color_axis = '#888888';
		$this->color_legend = '#BBBBBB';
		$this->color_annotation = '#AA4455';

		// SBox available only for graphs without overriten relative time.
		$this->make_sbox = (array_key_exists('dashboard_time', $options) && $options['dashboard_time']);

		$this->setAttribute('width', $this->width.'px');
		$this->setAttribute('height', $this->height.'px');
	}

	public function getCanvasX() {
		return $this->canvas_x;
	}

	public function getCanvasY() {
		return $this->canvas_y;
	}

	public function getCanvasWidth() {
		return $this->canvas_width;
	}

	public function getCanvasHeight() {
		return $this->canvas_height;
	}

	public function addProblems(array $problems = []) {
		$this->problems = $problems;

		return $this;
	}

	public function addMetrics(array $metrics = []) {
		foreach ($metrics as $i => $metric) {
			$this->addMetric($i, $metric);
		}

		return $this;
	}

	private function addMetric($i, array $metric = []) {
		$min_value = null;
		$max_value = null;

		foreach ($metric['points'] as $point) {
			if ($min_value === null || $min_value > $point['value']) {
				$min_value = $point['value'];
			}
			if ($max_value === null || $point['value'] > $max_value) {
				$max_value = $point['value'];
			}

			$this->points[$i][$point['clock']] = $point['value'];
		}

		if ($min_value !== null) {
			if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
				if ($this->min_value_left === null || $this->min_value_left > $min_value) {
					$this->min_value_left = $min_value;
				}
				if ($this->max_value_left === null || $this->max_value_left < $max_value) {
					$this->max_value_left = $max_value;
				}
			}
			elseif ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
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

	public function setTimePeriod($time_from, $time_till) {
		$this->time_from = $time_from;
		$this->time_till = $time_till;

		return $this;
	}

	public function setLegendType($type) {
		$this->legend_type = $type;

		return $this;
	}

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

	public function setXAxis($options) {
		$this->x_axis = $options;

		return $this;
	}

	private function calculateDimensions() {
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

		if ($this->left_y_show && $this->left_y_min) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT);
			$offset_left = max($this->offset_left, max(array_map('strlen', $values)) * $approx_width);
			$this->offset_left = (int) min($offset_left, $this->max_yaxis_width);
		}

		if ($this->right_y_show && $this->right_y_min) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT);
			$offset_right = max($this->offset_right, max(array_map('strlen', $values)) * $approx_width);
			$this->offset_right = (int) min($offset_right, $this->max_yaxis_width);
		}

		$this->canvas_width = $this->width - $this->offset_left - $this->offset_right;
		$this->offset_top = 10;
		$this->canvas_x = $this->offset_left;
		$this->canvas_y = $this->offset_top;

		// Calculate dimensions of legend.
		$legend_line = 1;

		if ($this->metrics) {
			// $legend_offset_left = 0;
			// $total_width = 0;
			// $allowed_lines = 3;
			foreach ($this->metrics as &$metric) {
				$metric['legend']['text'] = sprintf('%s: %s', $metric['host']['name'], $metric['name']);
				// $metric['legend']['width'] = imageTextSize(13, 0, $metric['legend']['text'], 'arial')['width'];
				// $total_width += $metric['legend']['width'] + 10;
			}

			// $shortening_rate = $allowed_lines / ($total_width / $this->canvas_width);

			// foreach ($this->metrics as &$metric) {
			// 	if ($shortening_rate < 1) {
			// 		$metric['legend']['width'] *= $shortening_rate;
			// 	}

			// 	if ($legend_offset_left + $metric['legend']['width'] > $this->canvas_width) {
			// 		$legend_offset_left = 0;
			// 		$legend_line++;
			// 	}

			// 	$metric['legend']['offset_left'] = floor($legend_offset_left);
			// 	$metric['legend']['offset_top'] = $legend_line * 20;
			// 	$legend_offset_left = $legend_offset_left + $metric['legend']['width'];
			// }
		}

		// Now, once the number of lines in legend is know, calculate also the bottom offsets and canvas height.
		//$this->offset_bottom = ($this->legend_type == SVG_GRAPH_LEGEND_TYPE_SHORT) ? 40 * $legend_line : 20;
		$this->offset_bottom = $this->legend_line_height * $this->legend_lines + 20 /* xaxis height */;
		$this->canvas_height = $this->height - $this->offset_top - $this->offset_bottom;

		unset($metric, $legend_left_offset, $legend_line);
	}

	private function drawLegend() {
		$legend = (new CSvgGraphLegend())
			->setSize($this->canvas_width, 40)
			->setPosition($this->canvas_x, $this->canvas_y + $this->canvas_height + 20/* X axis container height */);

		foreach ($this->metrics as $metric) {
			$legend->addLabel($metric['legend']['text'], $metric['options']['color']);
		}

		$this->addItem($legend);
		// if ($this->legend_type == SVG_GRAPH_LEGEND_TYPE_SHORT) {
		// 	foreach ($this->metrics as $i => $metric) {
		// 		$this->drawMetricLegend($i);
		// 	}
		// }
	}

	// private function drawMetricLegend($metric_num) {
	// 	$metric = $this->metrics[$metric_num];
	// 	$options = $metric['options'];

	// 	$x1 = $metric['legend']['offset_left'] + $this->canvas_x;
	// 	$y1 = $metric['legend']['offset_top'] + $this->canvas_y + $this->canvas_height + 10;
	// 	$x2 = $x1 + 10;
	// 	$y2 = $y1;

	// 	$this->addItem(
	// 		(new CSvgGroup())
	// 			->addItem([
	// 				(new CSvgLine($x1, $y1 + 1, $x2, $y2 + 1, $options['color']))
	// 					->setStrokeWidth(4),
	// 				(new CSvgTag('foreignObject'))
	// 					->setAttribute('x', $x2 + 6)
	// 					//->setAttribute('y', $y2 + 6)
	// 					->setAttribute('y', $y2 - 6)
	// 					->setAttribute('width', $metric['legend']['width'] - 20)
	// 					->setAttribute('height', 20)
	// 					->addItem(
	// 						(new CDiv($metric['legend']['text']))
	// 							->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml')
	// 							->addClass('graph-legend')
	// 					)
	// 			])
	// 	);
	// }

	/**
	 * Render Y axis with labels for left side of graph.
	 */
	protected function drawCanvasLeftYaxis() {
		if ($this->left_y_show && $this->min_value_left !== null) {
			$this->addItem(
				(new CSvgGraphAxis($this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT), GRAPH_YAXIS_SIDE_LEFT))
					->setSize($this->offset_left, $this->canvas_height)
					->setPosition($this->canvas_x - $this->offset_left, $this->canvas_y)
			);
		}
	}

	/**
	 * Render Y axis with labels for right side of graph.
	 */
	protected function drawCanvasRightYAxis() {
		if ($this->right_y_show && $this->min_value_right !== null) {
			$this->addItem(
				(new CSvgGraphAxis($this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT), GRAPH_YAXIS_SIDE_RIGHT))
					->setSize($this->offset_right, $this->canvas_height)
					->setPosition($this->canvas_x + $this->canvas_width, $this->canvas_y)
			);
		}
	}

	/**
	 * Render X axis with labels of graph.
	 */
	protected function drawCanvasXAxis() {
		if ($this->x_axis) {
			$this->addItem((new CSvgGraphAxis($this->getTimeGridWithPosition(), GRAPH_YAXIS_SIDE_BOTTOM))
				->setSize($this->canvas_width, $this->xaxis_height)
				->setPosition($this->canvas_x, $this->canvas_y + $this->canvas_height)
			);
		}
	}

	private function getValueGrid($min, $max) {
		$mul = 1 / pow(10, floor(log10($max)));
		$max10 = ceil($mul * $max) / $mul;
		$min10 = floor($mul * $min) / $mul;
		$delta = $max10 - $min10;
		$delta = ceil($mul * $delta) / $mul;

		$res = [];
		if ($delta) {
			for($i = 0; $delta >= $i; $i += $delta / 5) {
				$res[] = $i + $min10;
			}
		}
		else {
			$res[] = $min10;
		}

		return $res;
	}

	public function getValuesGridWithPosition($side = null) {
		if ($side === GRAPH_YAXIS_SIDE_RIGHT) {
			$min_value = $this->right_y_min;
			$max_value = $this->right_y_max;
			$units = $this->right_y_units;
		}
		elseif ($side === GRAPH_YAXIS_SIDE_LEFT) {
			$min_value = $this->left_y_min;
			$max_value = $this->left_y_max;
			$units = $this->left_y_units;
		}
		else {
			return [];
		}

		$grid = $this->getValueGrid($min_value, $max_value);
		$grid_min = reset($grid);
		$grid_max = end($grid);
		$delta = ($grid_max - $grid_min ? : 1);
		$grid_values = [];

		foreach ($grid as $value) {
			$relative_pos = $this->canvas_height - $this->canvas_height * ($grid_max - $value) / $delta;
			$grid_values[$relative_pos] = convert_units([
				'value' => $value,
				'units' => $units
			]);
		}

		return $grid_values;
	}

	private function drawGrid() {
		if ($this->left_y_show && $this->left_y_min) {
			$points_value = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT);
		}
		elseif ($this->right_y_show && $this->right_y_min) {
			$points_value = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT);
		}
		else {
			$points_value = [];
		}

		$this->addItem((new CSvgGraphGrid($points_value, $this->getTimeGridWithPosition()))
			->setPosition($this->canvas_x, $this->canvas_y)
			->setSize($this->canvas_width, $this->canvas_height)
		);
	}

	/**
	 * Return array of horizontal labels with positions. Array key will be position, value will be label.
	 *
	 * @return array
	 */
	public function getTimeGridWithPosition() {
		// TODO: copy/extend logic from CLineGraphDraw.
		$formats = [
			['sec' => 10,	'step' => 5,	'time_fmt' => 'H:i:s'],
			['sec' => 60,	'step' => 30,	'time_fmt' => 'H:i:s'],
			['sec' => 180,	'step' => 120,	'time_fmt' => 'H:i'],
			['sec' => 600,	'step' => 300,	'time_fmt' => 'H:i'],
			['sec' => 1800,	'step' => 600,	'time_fmt' => 'H:i'],
			['sec' => 3600,	'step' => 1200,	'time_fmt' => 'H:i'],
			['sec' => 7200,	'step' => 3600,	'time_fmt' => 'H:i'],
			['sec' => 14400,'step' => 7200,	'time_fmt' => 'H:i'],
			['sec' => 86400,'step' => 43200,'time_fmt' => 'H:i']
		];

		$step = 6 * 30 * 24 * 3600;
		$time_fmt = 'Y-n';

		$sec_per_100pix = (int) (($this->time_till - $this->time_from) / $this->canvas_width * 100);

		foreach ($formats as $f) {
			if ($sec_per_100pix < $f['sec']) {
				$step = $f['step'];
				$time_fmt = $f['time_fmt'];
				break;
			}
		}

		$start = $this->time_from + $step - $this->time_from % $step;
		$grid_values = [];

		for ($clock = $start; $this->time_till >= $clock; $clock += $step) {
			$relative_pos = round($this->canvas_width - $this->canvas_width * ($this->time_till - $clock)
				/ ($this->time_till - $this->time_from));
			$grid_values[$relative_pos] = date($time_fmt, $clock);
		}

		return $grid_values;
	}

	/**
	 * Calculate paths for metric elements.
	 */
	protected function calculatePaths() {
		foreach ($this->metrics as $index => $metric) {
			$max_value = ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT)
				? $this->max_value_right
				: $this->max_value_left;
			$min_value = ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT)
				? $this->min_value_right
				: $this->min_value_left;

			$time_range = $this->time_till - $this->time_from ? : 1;
			$value_diff = $max_value - $min_value ? : 1;
			$timeshift = $metric['options']['timeshift'];
			$paths = [];

			/**
			 * SVG_GRAPH_MISSING_DATA_CONNECTED is default behavior of SVG graphs, so no need to calculate anything here.
			 * Points will be connected anyway.
			 */
			if ($metric['options']['missingdatafunc'] != SVG_GRAPH_MISSING_DATA_CONNECTED) {
				$this->applyMissingDataFunc($this->points[$index], $metric['options']['missingdatafunc']);
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
				$paths[$path_num][] = [$x, $y];
			}

			$this->paths[$index] = $paths;
		}
	}

	private function applyMissingDataFunc(array &$points = [], $missingdatafunc) {
		if (!$points) {
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
		 * $threshold is a minimal period of time at what we assume that data point is missed;
		 * $average_distance is an average distance between existing data points;
		 * $added_value is a value that will be applied in time gaps that are longer than $threshold;
		 * $gap_interval is a time distance between missing points used to fulfill gaps of missing data. It's unique
		 * for each gap.
		 */
		$average_distance = $points_distance ? array_sum($points_distance) / count($points_distance) : 0;
		$threshold = $points_distance ? $average_distance * 3 : 0;
		$added_value = [
			SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO => 0,
			SVG_GRAPH_MISSING_DATA_NONE => null
		][$missingdatafunc];

		// Add missing values.
		$prev_clock = null;
		foreach ($points as $clock => $point) {
			if ($prev_clock !== null && ($clock - $prev_clock) > $threshold) {
				$gap_interval = floor(($clock - $prev_clock) / $threshold);

				$new_point_clock = $prev_clock;
				do {
					$new_point_clock = $new_point_clock + $gap_interval;
					$points[$new_point_clock] = $added_value;
				}
				while ($clock > $new_point_clock);
			}

			$prev_clock = $clock;
		}

		// Sort according new clock times.
		ksort($points);
	}

	private function drawMetricsArea() {
		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['fill'] > 0 && ($metric['options']['type'] == SVG_GRAPH_TYPE_LINE
					|| $metric['options']['type'] == SVG_GRAPH_TYPE_STAIRCASE)) {
				foreach ($this->paths[$index] as $path) {
					$this->addItem((new CSvgGraphArea($path, $metric))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
					);
				}
			}
		}
	}

	private function drawMetricsLine() {
		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_LINE
					|| $metric['options']['type'] == SVG_GRAPH_TYPE_STAIRCASE) {
				$group = (new CSvgGroup())
					->setAttribute('data-set', $metric['options']['type'] == SVG_GRAPH_TYPE_LINE ? 'line' : 'staircase')
					->setAttribute('data-metric', $metric['legend']['text'])
					->setAttribute('data-color', $metric['options']['color'])
					->setAttribute('data-tolerance', $metric['options']['width']);

				foreach ($this->paths[$index] as $path) {
					$group->addItem((new CSvgGraphLine($path, $metric))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
					);
				}

				$this->addItem($group);
			}
		}
	}

	private function drawMetricsPoint() {
		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_POINTS) {
				$group = (new CSvgGroup())
					->setAttribute('data-set', 'points')
					->setAttribute('data-metric', $metric['legend']['text'])
					->setAttribute('data-color', $metric['options']['color'])
					->setAttribute('data-tolerance', $metric['options']['pointsize']);

				foreach ($this->paths[$index] as $path) {
					$group->addItem((new CSvgGraphPoints($path, $metric))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
					);
				}

				$this->addItem($group);
			}
		}
	}

	private function drawProblems() {
		// TODO: move calculation related logic out of graph class. Only time presentation logic should be left.
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
				'r_clock' => ($problem['r_clock'] >= $today)
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']),
				'severity' => getSeverityStyle($problem['severity']),
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

	public function addSBox() {
		$this->addItem([
			(new CSvgRect(0, 0, 0, 0))->addClass('svg-graph-selection'),
			(new CSvgText(0, 0, '', 'black'))->addClass('svg-graph-selection-text')
		]);

		return $this;
	}

	private function addValueBox() {
		$this->addItem([
			(new CSvgRect(-5, $this->canvas_y, 3, $this->canvas_height))
				->addClass('svg-value-box')
				->setFillColor($this->color_annotation)
				->setStrokeColor($this->color_annotation)
				->setFillOpacity('0.2')
				->setStrokeOpacity('0.2')
		]);
	}

	public function draw() {
		$this->calculateDimensions();
		$this->calculatePaths();

		$this->drawGrid();
		//$this->drawMetrics();

		$this->drawMetricsArea();
		$this->drawMetricsLine();

		$this->drawCanvasLeftYAxis();
		$this->drawCanvasRightYAxis();

		$this->drawMetricsPoint();

		$this->drawCanvasXAxis();

		$this->drawProblems();
		$this->drawLegend();

		// Add UI components.
		if ($this->make_sbox) {
			$this->addSBox();
		}
		//$this->addToolTip();

		return $this;
	}
}

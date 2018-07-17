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
	protected $draw_color_palette;
	protected $draw_fill;
	protected $draw_line_width;
	protected $height;
	//protected $max_clock;
	protected $max_value_left;
	protected $max_value_right;
	protected $metrics;
	protected $points;
	//protected $min_clock;
	protected $min_value_left;
	protected $min_value_right;
	protected $legend_type;
	protected $offset_bottom;
	protected $offset_left;
	protected $offset_right;
	protected $offset_top;
	protected $problems;
	protected $time_from;
	protected $time_to;
	protected $width;
	protected $left_y_axis;
	protected $right_y_axis;
	protected $x_axis;

	public function __construct($width, $height, $options) {
		parent::__construct();

		$this->metrics = [];
		$this->points = [];
		$this->problems = [];
//		$this->annotations_simple = [];
//		$this->annotations_range = [];

		$this->width = $width;
		$this->height = $height;

		$this->draw_line_width = 1;
		$this->draw_fill = 0.1;

		$this->legend_type = SVG_GRAPH_LEGEND_TYPE_NONE;

		$this->left_y_axis = false;
		$this->right_y_axis = false;
		$this->x_axis = false;

		$this->min_value_left = null;
		$this->max_value_left = null;
		$this->min_value_right = null;
		$this->max_value_right = null;
		//$this->min_clock = null;
		//$this->max_clock = null;

		$this->color_background = '#FFFFFF';
		$this->color_metric = '#00AA00';
		$this->color_grid = '#777777';
		$this->color_axis = '#888888';
		$this->color_legend = '#BBBBBB';
		$this->color_annotation = '#AA4455';

		$this->setAttribute('width', $this->width.'px');
		$this->setAttribute('height', $this->height.'px');
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

	private function calculateDimensions() {
		// Get values for left Y axis if not specified by user.
		if ($this->left_y_axis !== false) {
			if (!array_key_exists('min', $this->left_y_axis)) {
				$this->left_y_axis['min'] = $this->min_value_left;
			}
			if (!array_key_exists('max', $this->left_y_axis)) {
				$this->left_y_axis['max'] = $this->max_value_left;
			}
			if (!array_key_exists('units', $this->left_y_axis)) {
				$this->left_y_axis['units'] = reset($this->metrics)['units'];
			}

			// Disable left Y axis if it is not used for metrics.
			if ($this->left_y_axis['min'] === null) {
				$this->left_y_axis = false;
			}
		}

		// Get values for right Y axis if not specified by user.
		if ($this->right_y_axis !== false) {
			if (!array_key_exists('min', $this->right_y_axis)) {
				$this->right_y_axis['min'] = $this->min_value_right;
			}
			if (!array_key_exists('max', $this->right_y_axis)) {
				$this->right_y_axis['max'] = $this->max_value_right;
			}
			if (!array_key_exists('units', $this->right_y_axis)) {
				$this->right_y_axis['units'] = reset($this->metrics)['units'];
			}

			// Disable right Y axis if it is not used for metrics.
			if ($this->right_y_axis['min'] === null) {
				$this->right_y_axis = false;
			}
		}

		// Define offsets.
		$this->offset_left = ($this->left_y_axis !== false) ? 50 : 20;
		$this->offset_right = ($this->right_y_axis !== false) ? 50 : 20;
		$this->offset_top = 10;

		if ($this->legend_type == SVG_GRAPH_LEGEND_TYPE_NONE) {
			$this->offset_bottom = 20;
		} elseif ($this->legend_type == SVG_GRAPH_LEGEND_TYPE_SHORT) {
			$this->offset_bottom = 40;
		}

		$this->canvas_width = $this->width - $this->offset_left - $this->offset_right;
		$this->canvas_height = $this->height - $this->offset_top - $this->offset_bottom;
		$this->canvas_x = $this->offset_left;
		$this->canvas_y = $this->offset_top;
	}

	public function setLegendType($type) {
		$this->legend_type = $type;

		return $this;
	}

//	public function setDrawLineWidth($width) {
//		$this->draw_line_width = $width;
//	}

//	public function setDrawFill($fill) {
//		$this->draw_fill = $fill;
//	}

//	public function setDrawColor($color) {
//		$this->color_metric = '#'.$color;
//	}

//	public function setDrawColorPalette($palette) {
//		$this->draw_color_palette = $palette;
//	}

//	public function setBackgroundColor($color) {
//		$this->color_background = '#'.$color;
//	}

//	public function setGridColor($color) {
//		$this->color_grid = '#'.$color;
//	}

	private function addCanvasLeftYAxis() {
		if ($this->left_y_axis !== false) {
			$this->addItem([
				new CSvgLine(
					$this->canvas_x,
					$this->canvas_y - 3,
					$this->canvas_x,
					$this->canvas_y + $this->canvas_height + 3,
					$this->color_axis
				),
				(new CSvgPolygon([
						[$this->canvas_x, $this->canvas_y - 7],
						[$this->canvas_x - 3, $this->canvas_y - 3],
						[$this->canvas_x + 3, $this->canvas_y - 3],
				]))
					->setStrokeWidth(1)
					->setStrokeColor($this->color_axis)
					->setFillColor($this->color_background)
			]);
		}
	}

	private function addCanvasRightYAxis() {
		if ($this->right_y_axis !== false) {
			// Draw arrow.
			$this->addItem([
				new CSvgLine(
					$this->canvas_x + $this->canvas_width,
					$this->canvas_y - 3,
					$this->canvas_x + $this->canvas_width,
					$this->canvas_y + $this->canvas_height + 3,
					$this->color_axis
				),
				(new CSvgPolygon([
						[$this->canvas_x + $this->canvas_width, $this->canvas_y - 7],
						[$this->canvas_x + $this->canvas_width - 3, $this->canvas_y - 3],
						[$this->canvas_x + $this->canvas_width + 3, $this->canvas_y - 3],
				]))
					->setStrokeWidth(1)
					->setStrokeColor($this->color_axis)
					->setFillColor($this->color_background)
			]);
		}
	}

	private function addCanvasXAxis() {
		$this->addItem([
			new CSvgLine(
				$this->canvas_x - 3,
				$this->canvas_y + $this->canvas_height,
				$this->canvas_x + $this->canvas_width + 3,
				$this->canvas_y + $this->canvas_height,
				$this->color_axis
			),
			(new CSvgPolygon([
					[$this->canvas_x + $this->canvas_width + 7, $this->canvas_y + $this->canvas_height],
					[$this->canvas_x + $this->canvas_width + 3, $this->canvas_y + $this->canvas_height - 3],
					[$this->canvas_x + $this->canvas_width + 3, $this->canvas_y + $this->canvas_height + 3],
				]))
				->setStrokeWidth(1)
				->setStrokeColor($this->color_axis)
				->setFillColor('white')]
			);
	}

	private function addCanvas() {
		$this->addItem(
			(new CSvgRect($this->canvas_x, $this->canvas_y, $this->canvas_width, $this->canvas_height))
				->setFillColor($this->color_background)
				->setStrokeColor($this->color_background)
		);
	}

	private function getValueGrid($min, $max) {
		$mul = 1 / pow(10, floor(log10($max)));
//		$max10 = 0.1*ceil(10*$mul*$max)/$mul;
//		$min10 = 0.1*floor(10*$mul*$min)/$mul;
		$max10 = ceil($mul * $max) / $mul;
		$min10 = floor($mul * $min) / $mul;
		$delta = $max10 - $min10;
		$delta = ceil($mul * $delta) / $mul;

/*		$this->addItem((new CSvgText(20, $this->height - 20,
			sprintf("Min %.2f", $min).
			sprintf(" Max %.2f", $max).
			sprintf(" Mul %.2f", $mul).
			sprintf(" Min10 %.2f", $min10).
			sprintf(" Max10 %.2f", $max10).
			sprintf(" max-min %.2f", $max10-$min10).
			sprintf(" Delta %.2f", $delta).
			"", "white")));*/

		$res = [];
		if ($delta != 0) {
			for($i = 0; $i <= $delta; $i += $delta / 5) {
				$res[] = $i + $min10;
			}
		}
		else {
			$res[] = $min10;
		}

		return $res;
	}

	private function drawValueGrid($side = null) {
		if ($side !== 'right' && $side !== 'left') {
			return;
		}

		$min_value = ($side === 'right') ? $this->min_value_right : $this->min_value_left;
		$max_value = ($side === 'right') ? $this->max_value_right : $this->max_value_left;

		if ($min_value === null || $max_value === null) {
			return;
		}

		$grid = $this->getValueGrid($min_value, $max_value);

		if ($side === 'right') {
			$this->min_value_right = $grid[0];
			$this->max_value_right = end($grid);
		}
		else {
			$this->min_value_left = $grid[0];
			$this->max_value_left = end($grid);
		}

		$text_x_offset = ($side === 'right') ? $this->canvas_width + 25 :  - 6;

		foreach ($grid as $value) {
			$x1 = $this->canvas_x;
			$y1 = $this->canvas_y + $this->canvas_height * ($max_value - $value) / ($max_value - $min_value);
			$x2 = $this->canvas_x + $this->canvas_width;
			$y2 = $y1;
			$suffix = '';

			// TODO
//			$suffix = '';
//			if ($value > 1000) {
//				$value = $value/1000;
//				$suffix = 'K';
//			}
//			if ($value > 1000) {
//				$value = $value/1000;
//				$suffix = 'M';
//			}

			$this->addItem([
				(new CSvgLine($x1, $y1, $x2, $y2, $this->color_grid))
					->setDashed(),
				(new CSvgText($x1 + $text_x_offset, $y2 + 4, sprintf('%g%s', floatval($value), $suffix), $this->color_legend))
					->setAttribute('text-anchor', 'end'),
//				(new CSvgText($x1 - 6, $y2 + 4, convert_units(['value'=>$value, 'units'=>'%']), $this->color_legend))
//					->setAttribute('text-anchor', 'end'),
			]);
		}
	}

	private function drawTimeGrid() {
		$formats = [
			['sec' => 10,	'step' => 5,	'time_fmt' => 'H:i:s'],
			['sec' => 60,	'step' => 30,	'time_fmt' => 'H:i:s'],
			['sec' => 180,	'step' => 120,	'time_fmt' => 'H:i'],
			['sec' => 600,	'step' => 300,	'time_fmt' => 'H:i'],
			['sec' => 1800,	'step' => 600,	'time_fmt' => 'H:i'],
			['sec' => 3600,	'step' => 1200,	'time_fmt' => 'H:i'],
			['sec' => 7200,	'step' => 3600,	'time_fmt' => 'H:i'],
			['sec' => 14400,'step' => 7200,	'time_fmt' => 'H:i'],
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

		//$this->addItem((new CSvgText(20, $this->height - 5, "Per 100 pix: $sec_per_100pix Step:$step Format:$time_fmt", "black")));

		$start = $this->time_from + $step - $this->time_from % $step;
		for ($clock = $start; $this->time_till >= $clock; $clock += $step) {
			$x1 = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $clock) / ($this->time_till - $this->time_from);
			$y1 = $this->canvas_y;
			$x2 = $x1;
			$y2 = $this->canvas_y + $this->canvas_height;
			$this->addItem([
				(new CSvgLine($x1, $y1, $x2, $y2, $this->color_grid))
					->setDashed(),
				(new CSvgText($x1, $y2 + 16, date($time_fmt, $clock), $this->color_legend))
					->setAttribute('text-anchor', 'middle'),
			]);
		}
	}

	private function drawMetricLegend($metric_num) {
		$metric = $this->metrics[$metric_num];
		$options = $metric['options'];

		$text = sprintf('%s: %s', $metric['host']['name'], $metric['name']);

		$x1 = $this->canvas_x + 300 * $metric_num;
		$y1 = $this->canvas_y + $this->canvas_height + 30;
		$x2 = $x1 + 10;
		$y2 = $y1;
		$this->addItem([
			(new CSvgLine($x1, $y1, $x2, $y2, $options['color']))
				->setStrokeWidth(4),
			(new CSvgText($x2 + 5, $y2 + 5, $text, $this->color_legend))
				->setAttribute('text-anchor', 'start'),
		]);
	}

	private function drawPoints($points, $options) {
		foreach ($points as $point) {
			$this->addItem(
				(new CSvgCircle($point[0], $point[1], $options['radius']))
					->setStrokeOpacity($options['transparency'] * 0.1)
					->setFillColor($options['color'])
			);
		}

		if ($options['fill'] != 0) {
			$points[] = [end($points)[0], $this->canvas_y + $this->canvas_height];
			$points[] = [$points[0][0], $this->canvas_y + $this->canvas_height];

			$this->addItem(
				(new CSvgPolyline($points))
					->setFillColor($options['color'])
					->setFillOpacity($options['fill'] * 0.1)
					->setStrokeWidth(0)
			);
		}
	}

	private function drawLines($points, $options) {
		$this->addItem(
			(new CSvgPolyline($points))
				->setStrokeColor($options['color'])
				->setStrokeOpacity($options['transparency'] * 0.1)
				->setStrokeWidth($options['width'])
				->setFillColor('none')
		);

		if ($options['fill'] != 0) {
			$points[] = [end($points)[0], $this->canvas_y + $this->canvas_height];
			$points[] = [$points[0][0], $this->canvas_y + $this->canvas_height];

			$this->addItem(
				(new CSvgPolyline($points))
					->setFillColor($options['color'])
					->setFillOpacity($options['fill'] * 0.1)
					->setStrokeWidth(0)
			);
		}
	}

	private function drawLinesStaircase($points, $options) {
		$path = [];
		$num = count($points);
		for ($i = 0; $i < $num; $i++) {
			$path[] = $points[$i];
			if ($i +1 != $num) {
				$path[] = [$points[$i + 1][0], $points[$i][1]];
			}
		}

		$this->addItem(
			(new CSvgPolyline($path))
				->setStrokeColor($options['color'])
				->setStrokeOpacity($options['transparency'] * 0.1)
				->setStrokeWidth($options['width'])
				->setFillColor('none')
		);

		if ($options['fill'] != 0) {
			$path[] = [end($path)[0], $this->canvas_y + $this->canvas_height];
			$path[] = [$path[0][0], $this->canvas_y + $this->canvas_height];

			$this->addItem(
				(new CSvgPolyline($path))
					->setFillColor($options['color'])
					->setFillOpacity($options['fill'] * 0.1)
					->setStrokeWidth(0)
			);
		}
	}

	private function drawMetricData($metric_num) {
		$metric = $this->metrics[$metric_num];
		$points = $this->points[$metric_num];
		$draw_type = $metric['options']['type'];
		$max_value = ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT)
			? $this->max_value_right
			: $this->max_value_left;
		$min_value = ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT)
			? $this->min_value_right
			: $this->min_value_left;
		$time_range = $this->time_till - $this->time_from;
		$value_diff = $max_value - $min_value;
		$timeshift = $metric['options']['timeshift'];
		$path = [];

		foreach ($points as $clock => $point) {
			$x = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $clock + $timeshift) / $time_range;
			$y = $this->canvas_y + $this->canvas_height * ($max_value - $point) / $value_diff;
			$path[] = [$x, $y];
		}

		// Make options.
		$def_options = [
			'color' => $this->color_metric,
			'width' => $this->draw_line_width,
			'transparency' => 10,
			'fill' => 0,
			'radius' => 3
		];

		$options = zbx_array_merge($def_options, $metric['options']);

		// Draw path.
		switch ($draw_type) {
			case SVG_GRAPH_TYPE_LINE:
				$this->drawLines($path, $options);
				break;

			case SVG_GRAPH_TYPE_POINTS:
				$this->drawPoints($path, $options);
				break;

			case SVG_GRAPH_TYPE_STAIRCASE:
				$this->drawLinesStaircase($path, $options);
				break;
		}
	}

	private function drawMetrics() {
		foreach ($this->metrics as $i => $metric) {
			$this->drawMetricData($i);
		}
	}

	private function applyMissingDataFunc() {
		/*
		foreach ($this->metrics as $metric) {
			if ($metric['options']['missingdatafunc'] == SVG_GRAPH_MISSING_DATA_NONE) {
				continue;
			}

			// Make array of all points on X axis.
			$x_points = array_keys($this->points[$metric['itemid']]);
			sort($x_points, SORT_NUMERIC); // Also reseting keys.

			// Find the shortest gap between 2 non-empty X points.
			$shortest_gap = null;
			foreach ($x_points as $i => $point) {
				if ($shortest_gap === null) {
					$shortest_gap = 0;
				}
				elseif ($shortest_gap == 0 || $shortest_gap > ($point - $x_points[$i - 1])) {
					$shortest_gap = $point - $x_points[$i - 1];

					// The shortest expected gap possible.
					if ($shortest_gap == 1) {
						break;
					}
				}
			}

			if (!$shortest_gap) {
				return;
			}
		}

		// Fill missing values.
		$range_x_points = range($this->min_clock, $this->max_clock, $shortest_gap);

		$x_missing = array_diff($x_points, $this->points[$metric_id]);
		*/
	}

	private function drawLegend() {
		if ($this->legend_type == SVG_GRAPH_LEGEND_TYPE_SHORT) {
			foreach ($this->metrics as $i => $metric) {
				$this->drawMetricLegend($i);
			}
		}
	}

	private function drawAnnotationSimple($clock, $text) {
		$time_range = $this->time_till - $this->time_from;
		$x1 = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $clock) / $time_range;
		$y1 = $this->canvas_y;
		$x2 = $x1;
		$y2 = $this->canvas_y + $this->canvas_height;
		//$tooltip = new CTag('title', false, $text);

		$this->addItem([
			(new CSvgLine($x1, $y1, $x2, $y2, $this->color_annotation))->setDashed(),
			(new CSvgPolygon([
					[$x2, $y2 + 1],
					[$x2 - 3, $y2 + 5],
					[$x2 + 3, $y2 + 5],
				]))
				->setStrokeWidth(3)
				->setStrokeColor($this->color_annotation)
				->setFillColor($this->color_annotation)
				//->addItem($tooltip)
		]);
	}

	private function drawAnnotationRange($time_from, $time_to, $text) {
		$time_range = $this->time_till - $this->time_from;
		$x1 = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $time_from) / $time_range;
		$y1_1 = $this->canvas_y;
		$y1_2 = $this->canvas_y + $this->canvas_height;
		$this->addItem([
			(new CSvgLine($x1, $y1_1, $x1, $y1_2, $this->color_annotation))
			->setDashed()
		]);

		$x2 = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $time_to) / $time_range;
		$y2_1 = $this->canvas_y;
		$y2_2 = $this->canvas_y + $this->canvas_height;
		$this->addItem([
			(new CSvgLine($x2, $y2_1, $x2, $y2_2, $this->color_annotation))
			->setDashed()
		]);

		$this->addItem([
			(new CSvgRect($x1, $y1_1, $x2 - $x1, $y1_2  - $y1_1))
			->setFillColor($this->color_annotation)
			->setStrokeColor($this->color_annotation)
			->setFillOpacity('0.1')
			->setStrokeOpacity('0.1')
		]);

		$tooltip = new CTag('title', false, $text);
		$this->addItem([
			(new CSvgRect($x1, $y1_2 + 1, $x2 - $x1, 4))
			->setFillColor($this->color_annotation)
			->setStrokeColor($this->color_annotation)
			->addItem($tooltip)
		]);
	}

	/*
	public function addElement($metric_id, $points) {
		$this->metrics[$metric_id]['points'] = $points;

		$this->min_clock = $this->time_from;
		$this->max_clock = $this->time_to;

		if (empty($points)) {
			return;
		}

		if ($this->min_clock === null) {
			$this->min_clock = min(array_column($points, 'clock'));
		}
		else {
			$this->min_clock = min($this->min_clock, min(array_column($points, 'clock')));
		}

		if ($this->max_clock === null) {
			$this->max_clock = max(array_column($points, 'clock'));
		}
		else {
			$this->max_clock = max($this->max_clock, max(array_column($points, 'clock')));
		}

		if ($this->min_value === null) {
			$this->min_value = min(array_column($points, 'value'));
		}
		else {
			$this->min_value = min($this->min_value, min(array_column($points, 'value')));
		}

		if ($this->max_value === null) {
			$this->max_value = max(array_column($points, 'value'));
		}
		else {
			$this->max_value = max($this->max_value, max(array_column($points, 'value')));
		}
	}

	public function addElementLegend($metric_id, $text) {
		$this->metrics[$metric_id]['legend'] = $text;
	}

	public function addAnnotationSimple($time, $text) {
		$this->annotations_simple[] = ['clock' => $time, 'text' => $text];
	}

	public function addAnnotationRange($time_from, $time_to, $text) {
		$this->annotations_range[] = ['time_from' => $time_from, 'time_to' => $time_to, 'text' => $text];
	}
	*/

	public function setYAxisLeft($options) {
		$this->left_y_axis = $options;

		return $this;
	}

	public function setYAxisRight($options) {
		$this->right_y_axis = $options;

		return $this;
	}

	public function setXAxis($options) {
		$this->x_axis = $options;

		return $this;
	}

//	private function addAnnotationsSimple() {
//		foreach ($this->annotations_simple as $annotation) {
//			$this->drawAnnotationSimple($annotation['clock'], $annotation['text']);
//		}
//	}

//	private function addAnnotationsRange() {
//		foreach ($this->annotations_range as $annotation) {
//			$this->drawAnnotationRange($annotation['time_from'], $annotation['time_to'], $annotation['text']);
//		}
//	}

	private function drawProblems() {
		foreach ($this->problems as $problem) {
			// If problem is never recovered, it will be drown till the end of graph.
			if ($problem['r_clock'] == 0) {
				$problem['r_clock'] = $this->time_till;
			}

			$time_range = $this->time_till - $this->time_from;
			$x1 = (int) ($this->canvas_width - $this->canvas_width * ($this->time_till - $problem['clock']) / $time_range);
			$x2 = (int) ($this->canvas_width - $this->canvas_width * ($this->time_till - $problem['r_clock']) / $time_range);

			// At least 2 pixels should be occupied to show range.
			if ($x2 - $x1 > 2) {
				$this->drawAnnotationRange($problem['clock'], $problem['r_clock'], $problem['name']);
			}
			else {
				$this->drawAnnotationSimple($problem['clock'], $problem['name']);
			}
		}
	}

	public function draw() {
		$this->calculateDimensions();
		$this->addCanvas();

		// Add Y axes.
		$this->addCanvasLeftYAxis();
		$this->addCanvasRightYAxis();

		// Add grid lines.
		if ($this->left_y_axis !== false) {
			$this->drawValueGrid('left');
		}
		if ($this->right_y_axis !== false) {
			$this->drawValueGrid('right');
		}

		// Add X axis.
		$this->drawTimeGrid();
		if ($this->x_axis !== false) {
			$this->addCanvasXAxis();
		}

		// Draw problem zones.
		$this->drawProblems();

		//$this->applyMissingDataFunc();
		$this->drawMetrics();
		$this->drawLegend();

		return $this;
	}
}

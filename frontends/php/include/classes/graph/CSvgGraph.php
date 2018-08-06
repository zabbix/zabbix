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
	//protected $min_clock;
	protected $min_value_left;
	protected $min_value_right;

	protected $legend_type;

	protected $left_y_max;
	protected $left_y_min;
	protected $left_y_show;
	protected $left_y_units;

	protected $offset_bottom;
	protected $offset_left;
	protected $offset_right;
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
		$this->offset_left = ($this->left_y_show && $this->left_y_min) ? 50 : 20;
		$this->offset_right = ($this->right_y_show && $this->right_y_min) ? 50 : 20;
		$this->canvas_width = $this->width - $this->offset_left - $this->offset_right;
		$this->offset_top = 10;
		$this->canvas_x = $this->offset_left;
		$this->canvas_y = $this->offset_top;

		// Calculate dimensions of legend.
		$legend_line = 1;

		if ($this->metrics) {
			$legend_offset_left = 0;
			$total_width = 0;
			$allowed_lines = 3;
			foreach ($this->metrics as &$metric) {
				$metric['legend']['text'] = sprintf('%s: %s', $metric['host']['name'], $metric['name']);
				$metric['legend']['width'] = imageTextSize(13, 0, $metric['legend']['text'], 'arial')['width'];
				$total_width += $metric['legend']['width'] + 10;
			}

			$shortening_rate = $allowed_lines / ($total_width / $this->canvas_width);

			foreach ($this->metrics as &$metric) {
				if ($shortening_rate < 1) {
					$metric['legend']['width'] *= $shortening_rate;
				}

				if ($legend_offset_left + $metric['legend']['width'] > $this->canvas_width) {
					$legend_offset_left = 0;
					$legend_line++;
				}

				$metric['legend']['offset_left'] = floor($legend_offset_left);
				$metric['legend']['offset_top'] = $legend_line * 20;
				$legend_offset_left = $legend_offset_left + $metric['legend']['width'];
			}
		}

		// Now, once the number of lines in legend is know, calculate also the bottom offsets and canvas height.
		$this->offset_bottom = ($this->legend_type == SVG_GRAPH_LEGEND_TYPE_SHORT) ? 40 * $legend_line : 20;
		$this->canvas_height = $this->height - $this->offset_top - $this->offset_bottom;

		unset($metric, $legend_left_offset, $legend_line);
	}

	private function drawLegend() {
		if ($this->legend_type == SVG_GRAPH_LEGEND_TYPE_SHORT) {
			foreach ($this->metrics as $i => $metric) {
				$this->drawMetricLegend($i);
			}
		}
	}

	private function drawMetricLegend($metric_num) {
		$metric = $this->metrics[$metric_num];
		$options = $metric['options'];

		$x1 = $metric['legend']['offset_left'] + $this->canvas_x;
		$y1 = $metric['legend']['offset_top'] + $this->canvas_y + $this->canvas_height + 10;
		$x2 = $x1 + 10;
		$y2 = $y1;

		$this->addItem(
			(new CSvgGroup())
				->addItem([
					(new CSvgLine($x1, $y1 + 1, $x2, $y2 + 1, $options['color']))
						->setStrokeWidth(4),
					(new CSvgTag('foreignObject'))
						->setAttribute('x', $x2 + 6)
						//->setAttribute('y', $y2 + 6)
						->setAttribute('y', $y2 - 6)
						->setAttribute('width', $metric['legend']['width'] - 20)
						->setAttribute('height', 20)
						->addItem(
							(new CDiv($metric['legend']['text']))
								->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml')
								->addClass('graph-legend')
						)

//					(new CSvgText($x2 + 6, $y2 + 6, $metric['legend']['text'], $this->color_legend))
//						->setAttribute('text-anchor', 'start')
//						->setAttribute('width', $metric['legend']['width'])
//						->setFontSize(13)
				])
		);
	}

	/**
	 * Render Y axis with labels for left side of graph.
	 */
	protected function drawCanvasLeftYaxis() {
		if ($this->left_y_show && $this->min_value_left !== null) {
			$this->addItem(
				(new CSvgGraphAxis($this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT), GRAPH_YAXIS_SIDE_LEFT))
					->setSize($this->offset_left, $this->canvas_height)
					->setPosition($this->canvas_x, $this->canvas_y)
			);
		}
	}

	/**
	 * Render Y axis with labels for right side of graph.
	 */
	protected function drawCanvasRightYAxis() {
		if ($this->right_y_show && $this->min_value_right !== null) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT);
			// Do not render axis value for bottom-right position.
			unset($values[0]);

			$this->addItem(
				(new CSvgGraphAxis($values, GRAPH_YAXIS_SIDE_RIGHT))
					->setSize($this->offset_right, $this->canvas_height)
					->setPosition($this->canvas_width, $this->canvas_y)
			);
		}
	}

	/**
	 * Render X axis with labels of graph.
	 */
	protected function drawCanvasXAxis() {
		if ($this->x_axis) {
			// Horizontal axis container height. TODO: should be calculated by calculateDimensions.
			$container_height = 20;
			$this->addItem((new CSvgGraphAxis($this->getTimeGridWithPosition(), GRAPH_YAXIS_SIDE_BOTTOM))
				->setSize($this->canvas_width, $container_height)
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
/*
	private function drawValueGrid($side = null, $add_desc = true) {
		if ($side !== 'right' && $side !== 'left') {
			return;
		}

		if ($side === 'right') {
			$min_value = $this->right_y_min;
			$max_value = $this->right_y_max;
			$units = $this->right_y_units;
			$text_x_offset = $this->canvas_width + 8;
		}
		else {
			$min_value = $this->left_y_min;
			$max_value = $this->left_y_max;
			$units = $this->left_y_units;
			$text_x_offset = - 6;
		}

		$grid = $this->getValueGrid($min_value, $max_value);

		foreach ($grid as $value) {
			$x1 = $this->canvas_x;
			$y1 = $this->canvas_y + $this->canvas_height * ($max_value - $value) / ($max_value - $min_value ? : 1);
			$x2 = $this->canvas_x + $this->canvas_width;
			$y2 = $y1;

			// Do not draw a grid line:
			//  - if value overlaps X axis;
			//  - if value is lower than X axis;
			//  - if value should be drawn above canvas top border.
			if ($y1 >= $this->canvas_y + $this->canvas_height || $y1 < $this->canvas_y) {
				continue;
			}

			$grid_line = [];
			$grid_line[] = (new CSvgLine($x1, $y1, $x2, $y2, $this->color_grid))->setDashed();

			if ($add_desc) {
				$descr = convert_units([
					'value' => $value,
					'units' => $units
				]);

				$grid_line[] = (new CSvgText($x1 + $text_x_offset, $y2 + 4, $descr, $this->color_legend))
					->setAttribute('text-anchor', $side === GRAPH_YAXIS_SIDE_RIGHT ? 'start' : 'end');
			}

			$this->addItem($grid_line);
		}
	}
*/
	/**
	 * Return array of horizontal labels with positions. Array key will be position, value will be label.
	 *
	 * @return array
	 */
	public function getTimeGridWithPosition() {
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

		$start = $this->time_from + $step - $this->time_from % $step;
		$grid_values = [];

		for ($clock = $start; $this->time_till >= $clock; $clock += $step) {
			$relative_pos = round($this->canvas_width - $this->canvas_width * ($this->time_till - $clock)
				/ ($this->time_till - $this->time_from));
			$grid_values[$relative_pos] = date($time_fmt, $clock);
		}

		return $grid_values;
	}
/*
	private function drawTimeGrid($add_desc = true) {
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

			$grid_line = [];
			$grid_line[] = (new CSvgLine($x1, $y1, $x2, $y2, $this->color_grid))->setDashed();

			if ($add_desc) {
				$desc = date($time_fmt, $clock);
				$grid_line[] = (new CSvgText($x1, $y2 + 16, $desc, $this->color_legend))->setAttribute('text-anchor', 'middle');
			}

			$this->addItem($grid_line);
		}
	}

	private function drawPoints($points, $metric) {
		$group = (new CSvgGroup())
			->setAttribute('data-set', 'points')
			->setAttribute('data-metric', $metric['host']['name'] . ': ' . $metric['name'])
			->setAttribute('data-color', $metric['options']['color'])
			->setFillColor($metric['options']['color'])
			->setFillOpacity($metric['options']['transparency'] * 0.1)
			->setAttribute('data-tolerance', $metric['options']['pointsize']);

		foreach ($points as $point) {
			$group->addItem((new CSvgCircle($point[0], $point[1], $metric['options']['pointsize'])));
		}
		$this->addItem($group);

		if ($metric['options']['fill'] != 0) {
			$points[] = [end($points)[0], $this->canvas_y + $this->canvas_height];
			$points[] = [$points[0][0], $this->canvas_y + $this->canvas_height];

			$this->addItem(
				(new CSvgPolyline($points))
					->setFillColor($metric['options']['color'])
					->setFillOpacity($metric['options']['fill'] * 0.1)
					->setStrokeWidth(0)
			);
		}
	}

	private function drawLines($points, $metric) {
		// Item grouping is used to increase client side performance.
		$this->addItem(
			(new CSvgGroup())
				->addItem(
					(new CSvgPolyline($points))
						->setStrokeColor($metric['options']['color'])
						->setStrokeOpacity($metric['options']['transparency'] * 0.1)
						->setStrokeWidth($metric['options']['width'])
						->setFillColor('none')
				)
				->setAttribute('data-set', 'line')
				->setAttribute('data-metric', $metric['host']['name'] . ': ' . $metric['name'])
				->setAttribute('data-color', $metric['options']['color'])
				->setAttribute('data-tolerance', $metric['options']['width'])
		);

		if ($metric['options']['fill'] != 0) {
			if (count($points) > 1) {
				$points[] = [end($points)[0], $this->canvas_y + $this->canvas_height];
				$points[] = [$points[0][0], $this->canvas_y + $this->canvas_height];
			}
			else {
				$points[] = [$points[0][0]-10, $this->canvas_y + $this->canvas_height];
				$points[] = [$points[0][0]+10, $this->canvas_y + $this->canvas_height];
			}

			$this->addItem(
				(new CSvgPolyline($points))
					->setFillColor($metric['options']['color'])
					->setFillOpacity($metric['options']['fill'] * 0.1)
					->setStrokeWidth(0)
			);
		}
	}

	private function drawLinesStaircase($points, $metric) {
		$path = [];
		$num = count($points);
		for ($i = 0; $i < $num; $i++) {
			$path[] = $points[$i];
			if ($i +1 != $num) {
				$path[] = [$points[$i + 1][0], $points[$i][1]];
			}
		}

		// Item grouping is used to increase client side performance.
		$this->addItem(
			(new CSvgGroup())
				->addItem(
					(new CSvgPolyline($path))
						->setStrokeColor($metric['options']['color'])
						->setStrokeOpacity($metric['options']['transparency'] * 0.1)
						->setStrokeWidth($metric['options']['width'])
						->setFillColor('none')
				)
				->setAttribute('data-set', 'staircase')
				->setAttribute('data-metric', $metric['host']['name'] . ': ' . $metric['name'])
				->setAttribute('data-color', $metric['options']['color'])
				->setAttribute('data-tolerance', $metric['options']['width'])
		);

		if ($metric['options']['fill'] != 0) {
			$path[] = [end($path)[0], $this->canvas_y + $this->canvas_height];
			$path[] = [$path[0][0], $this->canvas_y + $this->canvas_height];

			$this->addItem(
				(new CSvgPolyline($path))
					->setFillColor($metric['options']['color'])
					->setFillOpacity($metric['options']['fill'] * 0.1)
					->setStrokeWidth(0)
			);
		}
	}
*/
	private function drawMetricData($metric_num) {
		$metric = $this->metrics[$metric_num];
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
			$this->applyMissingDataFunc($this->points[$metric_num], $metric['options']['missingdatafunc']);
		}

		$path_num = 0;
		foreach ($this->points[$metric_num] as $clock => $point) {
			// If missing data function is SVG_GRAPH_MISSING_DATA_NONE, path should be skipped in multiple svg shapes.
			if ($point === null) {
				$path_num++;
				continue;
			}

			$x = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $clock + $timeshift) / $time_range;
			$y = $this->canvas_y + $this->canvas_height * ($max_value - $point) / $value_diff;
			$paths[$path_num][] = [$x, $y];
		}

		// Draw path.
		foreach ($paths as $path) {
			switch ($metric['options']['type']) {
				case SVG_GRAPH_TYPE_LINE:
				case SVG_GRAPH_TYPE_STAIRCASE:
					if ($metric['options']['fill'] > 0) {
						$this->addItem((new CSvgGraphArea($path, $metric))
							->setPosition($this->canvas_x, $this->canvas_y)
							->setSize($this->canvas_width, $this->canvas_height)
						);
					}
					$this->addItem((new CSvgGraphLine($path, $metric))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
					);
//					$this->drawLines($path, $metric);
					break;

				case SVG_GRAPH_TYPE_POINTS:
					$this->addItem((new CSvgGraphPoints($path, $metric))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
					);
//					$this->drawPoints($path, $metric);
					break;

//				case SVG_GRAPH_TYPE_STAIRCASE:
//					$this->drawLinesStaircase($path, $metric);
//					break;
			}
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

	/**
	 * Generate metric elements.
	 *
	 * @param array $metric_types    Array with metric types to render.
	 */
	private function drawMetrics(array $metric_types) {
		foreach ($this->metrics as $i => $metric) {
			if (in_array($metric['options']['type'], $metric_types)) {
				$this->drawMetricData($i);
			}
		}
	}


/*
	private function drawAnnotationSimple($clock, $info) {
		$time_range = $this->time_till - $this->time_from;
		$x1 = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $clock) / $time_range;
		$y1 = $this->canvas_y;
		$x2 = $x1;
		$y2 = $this->canvas_y + $this->canvas_height;

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
				->setAttribute('data-info', CJs::encodeJson($info))
		]);
	}

	private function drawAnnotationRange($time_from, $time_to, $info) {
		$time_range = $this->time_till - $this->time_from ? : 1;

		// If highligted zone has started before $this->time_from, use the most left point of canvas.
		$x1 = ($time_from > $this->time_from)
			? $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $time_from) / $time_range
			: $this->canvas_x;
		$x2 = $this->canvas_x + $this->canvas_width - $this->canvas_width * ($this->time_till - $time_to) / $time_range;
		$y1_1 = $this->canvas_y;
		$y1_2 = $this->canvas_y + $this->canvas_height;
		$y2_1 = $this->canvas_y;
		$y2_2 = $this->canvas_y + $this->canvas_height;

		// Draw border lines. Make them dashed if beginning or ending of highligted zone is visible in graph.
		$start_line = new CSvgLine($x1, $y1_1, $x1, $y1_2, $this->color_annotation);
		if ($time_from >= $this->time_from) {
			$start_line->setDashed();
		}
		$end_line = new CSvgLine($x2, $y2_1, $x2, $y2_2, $this->color_annotation);
		if ($this->time_till >= $time_to) {
			$end_line->setDashed();
		}

		// Add to the canvas.
		$this->addItem([
			$start_line,
			(new CSvgRect($x1, $y1_1, $x2 - $x1, $y1_2  - $y1_1))
				->setFillColor($this->color_annotation)
				->setFillOpacity('0.1'),
			$end_line,
			(new CSvgRect($x1, $y1_2 + 1, $x2 - $x1, 4))
				->setFillColor($this->color_annotation)
				->setStrokeColor($this->color_annotation)
				->setAttribute('data-info', CJs::encodeJson($info))
		]);
	}
*/
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

			// if ($problem['r_clock']) {
			// 	$info['r_clock'] = ($problem['r_clock'] >= $today)
			// 		? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
			// 		: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
			// }

			// if ($x2 - $x1 > 2) {
			// 	$this->drawAnnotationRange($problem['clock'], $time_to, $info);
			// }
			// else {
			// 	$this->drawAnnotationSimple($problem['clock'], $info);
			// }
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

		$this->drawGrid();

		$this->drawMetrics([SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_STAIRCASE]);

		$this->drawCanvasLeftYAxis();
		$this->drawCanvasRightYAxis();

		$this->drawMetrics([SVG_GRAPH_TYPE_POINTS]);

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

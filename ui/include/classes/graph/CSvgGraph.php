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


class CSvgGraph extends CSvg {

	public const SVG_GRAPH_X_AXIS_HEIGHT = 20;
	public const SVG_GRAPH_DEFAULT_COLOR = '#b0af07';
	public const SVG_GRAPH_DEFAULT_TRANSPARENCY = 5;
	public const SVG_GRAPH_DEFAULT_POINTSIZE = 1;
	public const SVG_GRAPH_DEFAULT_LINE_WIDTH = 1;

	public const SVG_GRAPH_X_AXIS_LABEL_MARGIN = 5;
	public const SVG_GRAPH_Y_AXIS_LABEL_MARGIN_OUTER = 10;
	public const SVG_GRAPH_Y_AXIS_LABEL_MARGIN_INNER = 5;

	private $canvas_x;
	private $canvas_y;
	private $canvas_width;
	private $canvas_height;

	private $graph_theme;

	/**
	 * Graph metrics.
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
	 * Graph points for stacked lines and stacked staircases, calculated from metrics and original graph points.
	 *
	 * @var array
	 */
	private array $stacked_points = [];

	/**
	 * Bar points for stacked and unstacked bars, calculated from metrics and original graph points.
	 *
	 * @var array
	 */
	private array $bar_points = [];

	/**
	 * Metric paths for points, unstacked lines and unstacked staircases, calculated from original points.
	 *
	 * @var array
	 */
	private array $paths = [];

	/**
	 * Metric paths for stacked lines and stacked staircases, calculated from stacked points.
	 *
	 * @var array
	 */
	private array $stacked_paths = [];

	/**
	 * Metric paths for stacked and unstacked bars, calculated from bar points.
	 *
	 * @var array
	 */
	private array $bar_paths = [];

	private $show_working_time;
	private $show_percentile_left;
	private $percentile_left_value;
	private $show_percentile_right;
	private $percentile_right_value;

	private $time_from;
	private $time_till;

	private $show_left_y_axis;
	private $left_y_scale;
	private $left_y_min;
	private $left_y_min_calculated;
	private $left_y_max;
	private $left_y_max_calculated;

	// Logarithmic scale variables
	private $left_y_has_zero = false;
	private $left_y_max_negative_power;
	private $left_y_min_negative_power;
	private $left_y_min_positive_power;
	private $left_y_max_positive_power;
	private $left_y_min_positive;
	private $left_y_max_negative;
	private $left_y_lower_power_shift;
	private $left_y_upper_power_shift;

	private $left_y_interval;
	private $left_y_units;
	private $left_y_power;
	private $left_y_empty = true;
	private $left_y_zero;

	private $show_right_y_axis;
	private $right_y_scale;
	private $right_y_min;
	private $right_y_min_calculated;
	private $right_y_max;
	private $right_y_max_calculated;

	// Logarithmic scale variables
	private $right_y_has_zero = false;
	private $right_y_min_negative_power;
	private $right_y_max_negative_power;
	private $right_y_min_positive_power;
	private $right_y_max_positive_power;
	private $right_y_min_positive;
	private $right_y_max_negative;
	private $right_y_lower_power_shift;
	private $right_y_upper_power_shift;

	private $right_y_interval;
	private $right_y_units;
	private $right_y_power;
	private $right_y_empty = true;
	private $right_y_zero;

	private $show_x_axis;

	private $simple_triggers = [];
	private $problems = [];

	private $max_value_left;
	private $max_value_right;
	private $min_value_left;
	private $min_value_right;

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

		$this->show_working_time = $options['displaying']['show_working_time'];
		$this->show_percentile_left = $options['displaying']['show_percentile_left'];
		$this->percentile_left_value = $options['displaying']['percentile_left_value'];
		$this->show_percentile_right = $options['displaying']['show_percentile_right'];
		$this->percentile_right_value = $options['displaying']['percentile_right_value'];

		$this->time_from = $options['time_period']['time_from'];
		$this->time_till =  $options['time_period']['time_to'];

		$this->show_left_y_axis = $options['axes']['show_left_y_axis'];
		$this->left_y_scale = $options['axes']['left_y_scale'];
		$this->left_y_min = $options['axes']['left_y_min'];
		$this->left_y_max = $options['axes']['left_y_max'];
		$this->left_y_units = $options['axes']['left_y_units'] !== null
			? trim(preg_replace('/\s+/', ' ', $options['axes']['left_y_units']))
			: null;

		$this->show_right_y_axis = $options['axes']['show_right_y_axis'];
		$this->right_y_scale = $options['axes']['right_y_scale'];
		$this->right_y_min = $options['axes']['right_y_min'];
		$this->right_y_max = $options['axes']['right_y_max'];
		$this->right_y_units = $options['axes']['right_y_units'] !== null
			? trim(preg_replace('/\s+/', ' ', $options['axes']['right_y_units']))
			: null;

		$this->show_x_axis = $options['axes']['show_x_axis'];

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

	public function addMetrics(array $metrics): CSvgGraph {
		$metrics_for_each_axes = [
			GRAPH_YAXIS_SIDE_LEFT => 0,
			GRAPH_YAXIS_SIDE_RIGHT => 0
		];

		foreach ($metrics as $index => $metric) {
			$this->metrics[$index] = [
				'data_set' => $metric['data_set'],
				'name' => $metric['name'],
				'itemid' => $metric['itemid'],
				'units' => $metric['units'],
				'host' => $metric['hosts'][0],
				'options' => ['order' => $index] + $metric['options']
			];

			if (!$metric['points']) {
				continue;
			}

			$this->metrics[$index]['points'] = $metric['points'];
			$this->points[$index] = $metric['points'];

			$metrics_for_each_axes[$metric['options']['axisy']]++;
		}

		$this->left_y_empty = ($metrics_for_each_axes[GRAPH_YAXIS_SIDE_LEFT] == 0);
		$this->right_y_empty = ($metrics_for_each_axes[GRAPH_YAXIS_SIDE_RIGHT] == 0);

		return $this;
	}

	public function addSimpleTriggers(array $simple_triggers): CSvgGraph {
		$this->simple_triggers = $simple_triggers;

		return $this;
	}

	public function addProblems(array $problems): CSvgGraph {
		$this->problems = $problems;

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
			(new CSvgText(''))->addClass('svg-graph-selection-text')
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
	 * @throws Exception
	 */
	public function draw(): self {
		$this->applyMissingDataFunc();
		$this->recalculatePoints();
		$this->calculateStackedPoints();
		$this->calculateBarPoints();

		$this->calculateDimensions();

		if ($this->canvas_width > 0 && $this->canvas_height > 0) {
			$this->calculatePaths();
			$this->calculateStackedPaths();
			$this->calculateBarPaths();

			$this->drawWorkingTime();

			$this->drawGrid();
			$this->drawYAxes();
			$this->drawXAxis();

			$this->drawMetricsLine();
			$this->drawMetricsStackedArea();
			$this->drawMetricsPoint();
			$this->drawMetricsBar();

			$this->drawPercentiles();
			$this->drawSimpleTriggers();

			$this->drawProblems();

			$this->addClipArea();
		}

		return $this;
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
	public static function getMissingData(array $points, int $missingdatafunc): array {
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
		$prev_point = null;
		$prev_clock = null;
		$missing_points = [];
		foreach ($points as $clock => $point) {
			if ($prev_clock !== null && ($clock - $prev_clock) > $threshold) {
				$gap_interval = floor(($clock - $prev_clock) / $threshold);

				if ($missingdatafunc == SVG_GRAPH_MISSING_DATA_NONE) {
					$missing_points[$prev_clock + $gap_interval] = null;
				}
				elseif ($missingdatafunc == SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO) {
					$value = ['min' => 0, 'avg' => 0, 'max' => 0];

					$missing_points[$prev_clock + $gap_interval] = $value;
					$missing_points[$clock - $gap_interval] = $value;
				}
				elseif ($missingdatafunc == SVG_GRAPH_MISSING_DATA_LAST_KNOWN) {
					$missing_points[$clock - $gap_interval] = $prev_point;
				}
			}

			$prev_clock = $clock;
			$prev_point = $point;
		}

		return $missing_points;
	}

	/**
	 * Modifies metric data and Y value range according specified missing data function.
	 */
	private function applyMissingDataFunc(): void {
		foreach ($this->metrics as $index => $metric) {
			$missing_data_function = $metric['options']['missingdatafunc'];

			/**
			 * - Missing data points are calculated only between existing data points;
			 * - Missing data points are not calculated for SVG_GRAPH_TYPE_POINTS && SVG_GRAPH_TYPE_BAR metrics;
			 * - SVG_GRAPH_MISSING_DATA_CONNECTED is default behavior of SVG graphs, so no need to calculate anything
			 *   here.
			 */
			if (!array_key_exists($index, $this->points)
					|| in_array($metric['options']['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR])
					|| $missing_data_function == SVG_GRAPH_MISSING_DATA_CONNECTED) {
				continue;
			}

			$points = &$this->points[$index];
			$missing_data_points = self::getMissingData($points, $missing_data_function);

			// Sort according new clock times (array keys).
			$points += $missing_data_points;
			ksort($points);

			// Missing data function can change min value of Y axis.
			if ($missing_data_points && $missing_data_function == SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO) {
				if ($this->min_value_left > 0 && $metric['options']['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
					$this->min_value_left = 0;
				}
				elseif ($this->min_value_right > 0 && $metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
					$this->min_value_right = 0;
				}
			}
		}
	}

	private function recalculatePoints(): void {
		foreach ($this->metrics as $index => $metric) {
			if (!array_key_exists($index, $this->points)) {
				continue;
			}

			$points = [];
			$part_index = 0;

			foreach ($this->points[$index] as $clock => $point) {
				// If missing data function is SVG_GRAPH_MISSING_DATA_NONE, path should be split in multiple svg shapes.
				if ($point === null) {
					$part_index++;
					continue;
				}

				$points[$part_index][$clock] = $point;
			}

			$this->points[$index] = $points;
		}
	}

	private function calculateStackedPoints(): void {
		$surface = [];

		foreach ($this->metrics as $index => $metric) {
			if (!in_array($metric['options']['type'], [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_STAIRCASE])
					|| $metric['options']['stacked'] != SVG_GRAPH_STACKED_ON
					|| !array_key_exists($index, $this->points)) {
				continue;
			}

			if (!array_key_exists($metric['data_set'], $surface)) {
				$surface[$metric['data_set']] = [
					'positive' => [[$this->time_from, 0], [$this->time_till, 0]],
					'negative' => [[$this->time_from, 0], [$this->time_till, 0]]
				];
			}

			switch ($metric['options']['approximation']) {
				case APPROXIMATION_MIN:
					$approximation = 'min';
					break;
				case APPROXIMATION_MAX:
					$approximation = 'max';
					break;
				default:
					$approximation = 'avg';
			}

			foreach ($this->points[$index] as $points) {
				$side_fragments = ['positive' => [], 'negative' => []];

				$side_fragment_points = [];
				$is_positive = reset($points)[$approximation] >= 0;
				$line_break = false;
				$prev_point = null;

				foreach ($points as $clock => $point) {
					$clock -= $metric['options']['timeshift'];

					$point_value = $point[$approximation];

					if ($is_positive != $point_value >= 0 && $point_value != 0) {
						$break_point = [
							$prev_point[0]
								+ ($clock - $prev_point[0]) * $prev_point[1] / ($prev_point[1] - $point_value),
							0, false
						];

						if ($break_point != $prev_point) {
							if ($metric['options']['type'] == SVG_GRAPH_TYPE_STAIRCASE) {
								$side_fragment_points[] = [$break_point[0], $prev_point[1], false];
							}

							$side_fragment_points[] = $break_point;
						}

						$side_fragments[$is_positive ? 'positive' : 'negative'][] = [
							'points' => $side_fragment_points,
							'line_cont_start' => $line_break,
							'line_cont_end' => true
						];

						$line_break = true;

						$side_fragment_points = [$break_point];
						$is_positive = !$is_positive;
					}

					if ($metric['options']['type'] == SVG_GRAPH_TYPE_STAIRCASE && $prev_point !== null) {
						$side_fragment_points[] = [$clock, $prev_point[1], false];
					}

					$prev_point = [$clock, $point_value, true];
					$side_fragment_points[] = $prev_point;
				}

				$side_fragments[$is_positive ? 'positive' : 'negative'][] = [
					'points' => $side_fragment_points,
					'line_cont_start' => $line_break,
					'line_cont_end' => false
				];

				foreach (['positive', 'negative'] as $side) {
					foreach ($side_fragments[$side] as $side_fragment) {
						$this->stacked_points[$index][] = self::calculateStackedFragment($side_fragment,
							$surface[$metric['data_set']][$side]
						);
					}
				}

				if (array_key_exists($index, $this->stacked_points)) {
					usort($this->stacked_points[$index],
						static function (array $data_1, array $data_2): int {
							return $data_1['area'][0][0] <=> $data_2['area'][0][0];
						}
					);
				}
			}
		}
	}

	/**
	 * @param array $fragment
	 * @param array $surface
	 *
	 * @return array
	 */
	private static function calculateStackedFragment(array $fragment, array &$surface): array {
		[
			'points' => $points,
			'line_cont_start' => $line_cont_start,
			'line_cont_end' => $line_cont_end
		] = $fragment;

		$si = 0;

		while ($si < count($surface) - 1 && $surface[$si + 1][0] <= $points[0][0]) {
			$si++;
		}

		$si_start = $si;

		$area = [];

		for ($pi = 0, $count = count($points); $pi < $count; $pi++) {
			while ($si < count($surface) - 1 && $surface[$si + 1][0] < $points[$pi][0]) {
				$si++;

				if ($pi > 0) {
					$area[] = array_merge(self::calculatePointOnLine($points[$pi - 1], $points[$pi], $surface[$si]),
						[null]
					);
				}
			}

			$point = self::calculatePointOnLine($surface[$si], $surface[$si + 1], $points[$pi]);

			if ($pi == 0 && $points[0][1] != 0) {
				$area[] = [$point[0], $point[1] - $points[$pi][1], null];
			}

			$area[] = array_merge($point, $points[$pi][2] !== false ? [$points[$pi][1]] : [null]);

			if ($pi == count($points) - 1 && $points[$pi][1] != 0) {
				$area[] = [$point[0], $point[1] - $points[$pi][1], null];
			}
		}

		$line_from = $line_cont_start || $points[0][1] == 0 ? 0 : 1;
		$line_to = $line_cont_end || $points[count($points) - 1][1] == 0 ? count($area) - 1 : count($area) - 2;

		$area = array_merge($area, array_reverse(array_splice($surface, $si_start + 1, $si - $si_start, $area)));

		return [
			'area' => $area,
			'line_from' => $line_from,
			'line_to' => $line_to
		];
	}

	/**
	 * @param array $start
	 * @param array $end
	 * @param array $at
	 *
	 * @return array
	 */
	private static function calculatePointOnLine(array $start, array $end, array $at): array {
		return [$at[0], $start[1] + $at[1] + ($at[0] - $start[0]) / ($end[0] - $start[0]) * ($end[1] - $start[1])];
	}

	private function calculateBarPoints(): void {
		$stacked_dataset_groups = [];

		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] != SVG_GRAPH_TYPE_BAR || !array_key_exists($index, $this->points)) {
				continue;
			}

			if (!array_key_exists($metric['options']['axisy'], $this->bar_points)) {
				$this->bar_points[$metric['options']['axisy']] = [];
			}

			switch ($metric['options']['approximation']) {
				case APPROXIMATION_MIN:
					$approximation = 'min';
					break;
				case APPROXIMATION_MAX:
					$approximation = 'max';
					break;
				default:
					$approximation = 'avg';
			}

			foreach ($this->points[$index] as $points) {
				foreach ($points as $clock => $point) {
					$clock -= $metric['options']['timeshift'];

					if (!array_key_exists($clock, $this->bar_points[$metric['options']['axisy']])) {
						$this->bar_points[$metric['options']['axisy']][$clock] = [];
					}

					if ($metric['options']['stacked'] == SVG_GRAPH_STACKED_ON) {
						if (!array_key_exists($clock, $stacked_dataset_groups)) {
							$stacked_dataset_groups[$clock] = [];
						}

						$group_index = array_key_exists($metric['data_set'], $stacked_dataset_groups[$clock])
							? $stacked_dataset_groups[$clock][$metric['data_set']]
							: count($this->bar_points[$metric['options']['axisy']][$clock]);

						$stacked_dataset_groups[$clock][$metric['data_set']] = $group_index;
					}
					else {
						$group_index = count($this->bar_points[$metric['options']['axisy']][$clock]);
					}

					$this->bar_points[$metric['options']['axisy']][$clock][$group_index][] = [
						$index,
						$point[$approximation]
					];
				}
			}
		}

		foreach ($this->bar_points as &$side_bar_data) {
			ksort($side_bar_data, SORT_NUMERIC);
		}
		unset($side_bar_data);
	}

	/**
	 * Calculate minimal and maximum values, canvas size, margins and offsets for graph canvas inside SVG element.
	 */
	private function calculateDimensions(): void {
		$has_logarithmic_scale = $this->left_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC
			|| $this->right_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC;

		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['stacked'] == SVG_GRAPH_STACKED_ON) {
				if (!in_array($metric['options']['type'], [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_STAIRCASE])
						|| !array_key_exists($index, $this->stacked_points)) {
					continue;
				}

				if ($has_logarithmic_scale) {
					$min_positive = null;
					$max_negative = null;
				}

				$min_value = null;
				$max_value = null;

				foreach ($this->stacked_points[$index] as $fragment) {
					for ($fr_index = $fragment['line_from']; $fr_index <= $fragment['line_to']; $fr_index++) {
						$point_value = $fragment['area'][$fr_index][1];

						if ($has_logarithmic_scale) {
							if ($point_value < 0) {
								if ($max_negative === null || $max_negative < $point_value) {
									$max_negative = (float) $point_value;
								}
							}
							elseif ($point_value > 0) {
								if ($min_positive === null || $min_positive > $point_value) {
									$min_positive = (float) $point_value;
								}
							}
						}

						if ($max_value === null || $max_value < $point_value) {
							$max_value = (float) $point_value;
						}
						if ($min_value === null || $min_value > $point_value) {
							$min_value = (float) $point_value;
						}
					}
				}
			}
			elseif (array_key_exists($index, $this->points)) {
				$min_value = null;
				$max_value = null;

				if ($has_logarithmic_scale) {
					$min_positive = null;
					$max_negative = null;
				}

				foreach ($this->points[$index] as $points) {
					foreach ($points as $point) {
						switch ($metric['options']['approximation']) {
							case APPROXIMATION_MIN:
								$point_min = $point['min'];
								$point_max = $point['min'];
								break;
							case APPROXIMATION_MAX:
								$point_min = $point['max'];
								$point_max = $point['max'];
								break;
							case APPROXIMATION_ALL:
								$point_min = $point['min'];
								$point_max = $point['max'];
								break;
							default:
								$point_min = $point['avg'];
								$point_max = $point['avg'];
								break;
						}

						if ($has_logarithmic_scale) {
							foreach ([$point_min, $point_max] as $point_value) {
								if ($point_value < 0) {
									if ($max_negative === null || $max_negative < $point_value) {
										$max_negative = (float) $point_value;
									}
								}
								elseif ($point_value > 0) {
									if ($min_positive === null || $min_positive > $point_value) {
										$min_positive = (float) $point_value;
									}
								}
							}
						}

						if ($min_value === null || $min_value > $point_min) {
							$min_value = (float) $point_min;
						}
						if ($max_value === null || $max_value < $point_max) {
							$max_value = (float) $point_max;
						}
					}
				}
			}
			else {
				continue;
			}

			if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
				if ($this->min_value_left === null || $this->min_value_left > $min_value) {
					$this->min_value_left = $min_value;
				}
				if ($this->max_value_left === null || $this->max_value_left < $max_value) {
					$this->max_value_left = $max_value;
				}

				if ($this->left_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					if ($this->left_y_min_positive === null || $this->left_y_min_positive > $min_positive) {
						$this->left_y_min_positive = $min_positive;
					}

					if ($this->left_y_max_negative === null || $this->left_y_max_negative < $max_negative) {
						$this->left_y_max_negative = $max_negative;
					}
				}
			}
			else {
				if ($this->min_value_right === null || $this->min_value_right > $min_value) {
					$this->min_value_right = $min_value;
				}
				if ($this->max_value_right === null || $this->max_value_right < $max_value) {
					$this->max_value_right = $max_value;
				}

				if ($this->right_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					if ($this->right_y_min_positive === null || $this->right_y_min_positive > $min_positive) {
						$this->right_y_min_positive = $min_positive;
					}

					if ($this->right_y_max_negative === null || $this->right_y_max_negative < $max_negative) {
						$this->right_y_max_negative = $max_negative;
					}
				}
			}
		}

		foreach ($this->bar_points as $side => $side_bar_data) {
			foreach ($side_bar_data as $bar_group) {
				foreach ($bar_group as $bar_stack) {
					$bar_stack_min = 0;
					$bar_stack_max = 0;

					foreach ($bar_stack as $bar) {
						if ($bar[1] >= 0) {
							$bar_stack_max += $bar[1];
						}
						else {
							$bar_stack_min += $bar[1];
						}
					}

					if ($side == GRAPH_YAXIS_SIDE_LEFT) {
						if ($this->min_value_left === null || $this->min_value_left > $bar_stack_min) {
							$this->min_value_left = $bar_stack_min;
						}
						if ($this->max_value_left === null || $this->max_value_left < $bar_stack_max) {
							$this->max_value_left = $bar_stack_max;
						}

						if ($this->left_y_scale === SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
							foreach ([$bar_stack_min, $bar_stack_max] as $bar_value) {
								if ($bar_value < 0) {
									if ($this->left_y_max_negative === null
											|| $this->left_y_max_negative < $bar_value) {
										$this->left_y_max_negative = $bar_value;
									}
								}
								elseif ($bar_value > 0) {
									if ($this->left_y_min_positive === null
											|| $this->left_y_min_positive > $bar_value) {
										$this->left_y_min_positive = $bar_value;
									}
								}
							}
						}
					}
					else {
						if ($this->min_value_right === null || $this->min_value_right > $bar_stack_min) {
							$this->min_value_right = $bar_stack_min;
						}
						if ($this->max_value_right === null || $this->max_value_right < $bar_stack_max) {
							$this->max_value_right = $bar_stack_max;
						}

						if ($this->right_y_scale === SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
							foreach ([$bar_stack_min, $bar_stack_max] as $bar_value) {
								if ($bar_value < 0) {
									if ($this->right_y_max_negative === null
											|| $this->right_y_max_negative < $bar_value) {
										$this->right_y_max_negative = $bar_value;
									}
								}
								elseif ($bar_value > 0) {
									if ($this->right_y_min_positive === null
											|| $this->right_y_min_positive > $bar_value) {
										$this->right_y_min_positive = $bar_value;
									}
								}
							}
						}
					}
				}
			}
		}

		// Canvas height must be specified before call self::getValuesGridWithPosition.

		$offset_top = 10;
		$offset_bottom = self::SVG_GRAPH_X_AXIS_HEIGHT;
		$this->canvas_height = max(0, $this->height - $offset_top - $offset_bottom);
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

		if ($this->left_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
			$result = calculateLogarithmicGraphScaleExtremes($this->left_y_min, $this->left_y_max,
				$this->left_y_min_positive, $this->left_y_max_negative, $this->left_y_min_calculated,
				$this->left_y_max_calculated, $rows_min, $rows_max
			);

			[
				'min' => $this->left_y_min,
				'max' => $this->left_y_max,
				'max_positive_power' => $this->left_y_max_positive_power,
				'min_positive_power' => $this->left_y_min_positive_power,
				'min_negative_power' => $this->left_y_min_negative_power,
				'max_negative_power' => $this->left_y_max_negative_power,
				'lower_power_shift' => $this->left_y_lower_power_shift,
				'upper_power_shift' => $this->left_y_upper_power_shift,
				'interval' => $this->left_y_interval
			] = $result;

			$this->left_y_has_zero = $this->left_y_min <= 0 && $this->left_y_max >= 0;
		}
		else {
			$calc_power = $this->left_y_units === '' || $this->left_y_units[0] !== '!';

			$result = calculateGraphScaleExtremes($this->left_y_min, $this->left_y_max, $this->left_y_units,
				$calc_power, $this->left_y_min_calculated, $this->left_y_max_calculated, $rows_min, $rows_max
			);

			[
				'min' => $this->left_y_min,
				'max' => $this->left_y_max,
				'interval' => $this->left_y_interval,
				'power' => $this->left_y_power
			] = $result;
		}

		// Calculate vertical scale parameters for right side.

		if ($this->show_left_y_axis && $this->left_y_min_calculated && $this->left_y_max_calculated
				&& $result['rows'] != 0) {
			$rows_min = $result['rows'];
			$rows_max = $result['rows'];
		}

		$this->right_y_min_calculated = $this->right_y_min === null;
		$this->right_y_max_calculated = $this->right_y_max === null;

		if ($this->right_y_min_calculated) {
			$this->right_y_min = $this->min_value_right ?: 0;
		}
		if ($this->right_y_max_calculated) {
			$this->right_y_max = $this->max_value_right ?: 1;
		}

		if ($this->right_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
			$result = calculateLogarithmicGraphScaleExtremes($this->right_y_min, $this->right_y_max,
				$this->right_y_min_positive, $this->right_y_max_negative, $this->right_y_min_calculated,
				$this->right_y_max_calculated, $rows_min, $rows_max
			);

			[
				'min' => $this->right_y_min,
				'max' => $this->right_y_max,
				'max_positive_power' => $this->right_y_max_positive_power,
				'min_positive_power' => $this->right_y_min_positive_power,
				'min_negative_power' => $this->right_y_min_negative_power,
				'max_negative_power' => $this->right_y_max_negative_power,
				'lower_power_shift' => $this->right_y_lower_power_shift,
				'upper_power_shift' => $this->right_y_upper_power_shift,
				'interval' => $this->right_y_interval
			] = $result;

			$this->right_y_has_zero = $this->right_y_min <= 0 && $this->right_y_max >= 0;
		}
		else {
			$calc_power = $this->right_y_units === '' || $this->right_y_units[0] !== '!';

			$result = calculateGraphScaleExtremes($this->right_y_min, $this->right_y_max, $this->right_y_units,
				$calc_power, $this->right_y_min_calculated, $this->right_y_max_calculated, $rows_min, $rows_max
			);

			[
				'min' => $this->right_y_min,
				'max' => $this->right_y_max,
				'interval' => $this->right_y_interval,
				'power' => $this->right_y_power
			] = $result;
		}

		// Define canvas dimensions and offsets, except canvas height and bottom offset.
		if ($this->show_left_y_axis) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, $this->left_y_empty);

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

		if ($this->show_right_y_axis) {
			$values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT, $this->right_y_empty);

			if ($values) {
				if (array_key_exists(0, $values)) {
					unset($values[0]);
				}

				$approx_width = 0;

				foreach ($values as $value) {
					$approx_width = max($approx_width, imageTextSize(11, 0, $value)['width']);
				}

				$this->offset_right = min($this->max_yaxis_width,
					max($this->offset_right, self::SVG_GRAPH_Y_AXIS_LABEL_MARGIN_OUTER + $approx_width)
				);
			}
		}

		$this->canvas_width = max(0, $this->width - $this->offset_left - $this->offset_right);
		$this->canvas_x = $this->offset_left;

		// Calculate vertical zero position.
		if ($this->left_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
			$relative_zero_position = calculateLogarithmicRelativePosition($this->left_y_max_negative_power,
				$this->left_y_min_negative_power, $this->left_y_min_positive_power, $this->left_y_max_positive_power, 0
			);

			$this->left_y_zero = $this->canvas_y + $this->canvas_height * max(0, min(1, $relative_zero_position));
		}
		else {
			if ($this->left_y_max - $this->left_y_min == INF) {
				$this->left_y_zero = $this->canvas_y + $this->canvas_height
					* max(0, min(1, $this->left_y_max / 10 / ($this->left_y_max / 10 - $this->left_y_min / 10)));
			}
			else {
				$this->left_y_zero = $this->canvas_y + $this->canvas_height
					* max(0, min(1, $this->left_y_max / ($this->left_y_max - $this->left_y_min)));
			}
		}

		if ($this->right_y_scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
			$relative_zero_position = calculateLogarithmicRelativePosition($this->right_y_max_negative_power,
				$this->right_y_min_negative_power, $this->right_y_min_positive_power, $this->right_y_max_positive_power,
				0
			);

			$this->right_y_zero = $this->canvas_y + $this->canvas_height * max(0, min(1, $relative_zero_position));
		}
		else {
			if ($this->right_y_max - $this->right_y_min == INF) {
				$this->right_y_zero = $this->canvas_y + $this->canvas_height
					* max(0, min(1, $this->right_y_max / 10 / ($this->right_y_max / 10 - $this->right_y_min / 10)));
			}
			else {
				$this->right_y_zero = $this->canvas_y + $this->canvas_height
					* max(0, min(1, $this->right_y_max / ($this->right_y_max - $this->right_y_min)));
			}
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
			if (!in_array($metric['options']['type'], [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_STAIRCASE,
				SVG_GRAPH_TYPE_POINTS])
					|| $metric['options']['stacked'] == SVG_GRAPH_STACKED_ON
					|| !array_key_exists($index, $this->points)) {
				continue;
			}

			if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
				$min_value = $this->right_y_min;
				$max_value = $this->right_y_max;
				$scale = $this->right_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$max_positive_power = $this->right_y_max_positive_power;
					$max_negative_power = $this->right_y_max_negative_power;
					$min_positive_power = $this->right_y_min_positive_power;
					$min_negative_power = $this->right_y_min_negative_power;
				}
			}
			else {
				$min_value = $this->left_y_min;
				$max_value = $this->left_y_max;
				$scale = $this->left_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$max_positive_power = $this->left_y_max_positive_power;
					$max_negative_power = $this->left_y_max_negative_power;
					$min_positive_power = $this->left_y_min_positive_power;
					$min_negative_power = $this->left_y_min_negative_power;
				}
			}

			$time_range = ($this->time_till - $this->time_from) ?: 1;
			$timeshift = $metric['options']['timeshift'];
			$paths = [];

			foreach ($this->points[$index] as $part_index => $points) {
				foreach ($points as $clock => $point) {
					/**
					 * Avoid invisible data point drawing. Data sets of type != SVG_GRAPH_TYPE_POINTS cannot be skipped to
					 * keep shape unchanged.
					 */
					$path_point = [];
					foreach ($point as $type => $value) {
						$x = $this->canvas_x + $this->canvas_width
							- $this->canvas_width * ($this->time_till - $clock + $timeshift) / $time_range;

						if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
							$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
								calculateLogarithmicRelativePosition($max_negative_power, $min_negative_power,
									$min_positive_power, $max_positive_power, $value
								)
							]);
						}
						else {
							if ($max_value - $min_value == INF) {
								$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
									$max_value / 10 - $value / 10, 1 / ($max_value / 10 - $min_value / 10)
								]);
							}
							else {
								$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
									$max_value - $value, 1 / ($max_value - $min_value)
								]);
							}
						}

						if ($value < $min_value || $value > $max_value) {
							$y = $metric['options']['type'] == SVG_GRAPH_TYPE_POINTS
								? CSvgGraphMetricsPoint::Y_OUT_OF_RANGE
								: ($value > $max_value ? max($y_min, $y) : min($y_max, $y));
						}

						$path_point[$type] = [
							(int) ceil($x),
							(int) ceil($y),
							convertUnits([
								'value' => $value,
								'units' => $metric['units']
							])
						];
					}

					$paths[$part_index][] = $path_point;
				}

				if ($paths) {
					$this->paths[$index] = $paths;
				}
			}
		}
	}

	/**
	 * Calculate paths for stacked metric elements.
	 */
	private function calculateStackedPaths(): void {
		// Metric having very big values of y outside visible area will fail to render.
		$y_max = 2 ** 16;
		$y_min = -$y_max;

		$time_range = ($this->time_till - $this->time_from) ?: 1;

		foreach ($this->metrics as $index => $metric) {
			if (!array_key_exists($index, $this->stacked_points)) {
				continue;
			}

			if ($metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT) {
				$min_value = $this->right_y_min;
				$max_value = $this->right_y_max;
				$scale = $this->right_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$max_positive_power = $this->right_y_max_positive_power;
					$max_negative_power = $this->right_y_max_negative_power;
					$min_positive_power = $this->right_y_min_positive_power;
					$min_negative_power = $this->right_y_min_negative_power;
				}
			}
			else {
				$min_value = $this->left_y_min;
				$max_value = $this->left_y_max;
				$scale = $this->left_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$max_positive_power = $this->left_y_max_positive_power;
					$max_negative_power = $this->left_y_max_negative_power;
					$min_positive_power = $this->left_y_min_positive_power;
					$min_negative_power = $this->left_y_min_negative_power;
				}
			}

			foreach ($this->stacked_points[$index] as $fragment_index => $fragment) {
				$stacked_path = [];

				foreach ($fragment['area'] as $stacked_point) {
					$x = $this->canvas_x + $this->canvas_width
						- $this->canvas_width * ($this->time_till - $stacked_point[0]) / $time_range;

					if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
						$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
							calculateLogarithmicRelativePosition($max_negative_power, $min_negative_power,
								$min_positive_power, $max_positive_power, $stacked_point[1]
							)
						]);
					}
					else {
						if ($max_value - $min_value == INF) {
							$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
								$max_value / 10 - $stacked_point[1] / 10, 1 / ($max_value / 10 - $min_value / 10)
							]);
						}
						else {
							$y = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
								$max_value - $stacked_point[1], 1 / ($max_value - $min_value)
							]);
						}
					}

					$y = min($y_max, max($y_min, $y));

					$stacked_path[] = [
						(int) ceil($x),
						(int) ceil($y),
						$stacked_point[2] !== null
							? convertUnits([
								'value' => $stacked_point[2],
								'units' => $metric['units']
							])
							: ''
					];
				}

				$this->stacked_paths[$index][$fragment_index] = [
					'path' => $stacked_path,
					'line_from' => $fragment['line_from'],
					'line_to' => $fragment['line_to']
				];
			}
		}
	}

	private function calculateBarPaths(): void {
		// Metric having very big values of y outside visible area will fail to render.
		$y_max = 2 ** 16;
		$y_min = -$y_max;

		$time_range = ($this->time_till - $this->time_from) ?: 1;
		$time_per_px = $time_range / $this->canvas_width;

		foreach ($this->bar_points as $side => $side_bar_data) {
			if ($side == GRAPH_YAXIS_SIDE_RIGHT) {
				$min_value = $this->right_y_min;
				$max_value = $this->right_y_max;
				$scale = $this->right_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$max_positive_power = $this->right_y_max_positive_power;
					$max_negative_power = $this->right_y_max_negative_power;
					$min_positive_power = $this->right_y_min_positive_power;
					$min_negative_power = $this->right_y_min_negative_power;
				}
			}
			else {
				$min_value = $this->left_y_min;
				$max_value = $this->left_y_max;
				$scale = $this->left_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$max_positive_power = $this->left_y_max_positive_power;
					$max_negative_power = $this->left_y_max_negative_power;
					$min_positive_power = $this->left_y_min_positive_power;
					$min_negative_power = $this->left_y_min_negative_power;
				}
			}

			$clock_min_diff = max(1, round($time_range * .25));

			$clock_min_last = [];

			foreach ($side_bar_data as $clock => $bar_group) {
				foreach ($bar_group as $bar_stack) {
					foreach ($bar_stack as [$metric_index, $point_value]) {
						if (array_key_exists($metric_index, $clock_min_last)) {
							$clock_min_diff = min($clock_min_diff, $clock - $clock_min_last[$metric_index]);
						}

						$clock_min_last[$metric_index] = $clock;
					}
				}
			}

			$group_width = $clock_min_diff / $time_range * $this->canvas_width * .75;

			$bar_groups = [];

			$last_clock = null;
			$last_clock_index = null;

			foreach ($side_bar_data as $clock => $bar_group) {
				if ($last_clock !== null && $clock - $last_clock < $time_per_px) {
					$bar_groups[$last_clock_index] = array_merge($bar_groups[$last_clock_index], $bar_group);
				}
				else {
					$bar_groups[$clock] = $bar_group;
					$last_clock_index = $clock;
				}

				$last_clock = $clock;
			}

			foreach ($bar_groups as $clock_px => $bar_group) {
				$metric_width = max(1, ceil($group_width / count($bar_group)));

				$group_x1 = ceil(($clock_px - $this->time_from) / $time_range * $this->canvas_width - $group_width / 2);
				$bar_stack_x1 = $group_x1;

				foreach ($bar_group as $bar_group_index => $bar_stack) {
					$sum = 0;

					$bar_stack_x2 = $group_x1 + ($bar_group_index + 1) * $metric_width;

					foreach ($bar_stack as [$metric_index, $point_value]) {
						$value_from = $sum;
						$value_to = $sum + $point_value;
						$sum += $point_value;

						if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
							$bar_y1 = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
								calculateLogarithmicRelativePosition($max_negative_power, $min_negative_power,
									$min_positive_power, $max_positive_power, $value_from
								)
							]);

							$bar_y2 = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
								calculateLogarithmicRelativePosition($max_negative_power, $min_negative_power,
									$min_positive_power, $max_positive_power, $value_to
								)
							]);
						}
						else {
							if ($max_value - $min_value == INF) {
								$bar_y1 = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
									$max_value / 10 - $value_from / 10, 1 / ($max_value / 10 - $min_value / 10)
								]);
								$bar_y2 = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
									$max_value / 10 - $value_to / 10, 1 / ($max_value / 10 - $min_value / 10)
								]);
							}
							else {
								$bar_y1 = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
									$max_value - $value_from, 1 / ($max_value - $min_value)
								]);
								$bar_y2 = $this->canvas_y + CMathHelper::safeMul([$this->canvas_height,
									$max_value - $value_to, 1 / ($max_value - $min_value)
								]);
							}
						}

						$bar_y1 = min($y_max, max($y_min, $bar_y1));
						$bar_y2 = min($y_max, max($y_min, $bar_y2));

						$this->bar_paths[$metric_index][] = [
							(int) ($this->canvas_x + $bar_stack_x1),
							(int) ($this->canvas_x + $bar_stack_x2),
							(int) $bar_y1,
							(int) $bar_y2,
							convertUnits([
								'value' => $point_value,
								'units' => $this->metrics[$metric_index]['units']
							]),
							(int) ($this->canvas_x + $group_x1)
						];
					}

					$bar_stack_x1 = $bar_stack_x2;
				}
			}
		}

		ksort($this->bar_paths, SORT_NUMERIC);
	}

	private function drawWorkingTime(): void {
		if (!$this->show_working_time) {
			return;
		}

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
		$time_points = $this->show_x_axis ? $this->getTimeGridWithPosition() : [];
		$value_points = [];

		if ($this->show_left_y_axis) {
			$value_points = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, $this->left_y_empty);

			unset($time_points[0]);
		}
		elseif ($this->show_right_y_axis) {
			$value_points = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT, $this->right_y_empty);

			unset($time_points[$this->canvas_width]);
		}

		if ($this->show_x_axis) {
			unset($value_points[0]);
		}

		$this->addItem(
			(new CSvgGraphGrid($value_points, $time_points))
				->setPosition($this->canvas_x, $this->canvas_y)
				->setSize($this->canvas_width, $this->canvas_height)
				->setColor('#'.$this->graph_theme['gridcolor'])
		);
	}

	private function drawYAxes(): void {
		if ($this->show_left_y_axis) {
			$grid_values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_LEFT, $this->left_y_empty);
			$this->addItem(
				(new CSvgGraphAxis($grid_values, GRAPH_YAXIS_SIDE_LEFT))
					->setPosition($this->canvas_x - $this->offset_left, $this->canvas_y)
					->setSize($this->offset_left, $this->canvas_height)
					->setLineColor('#'.$this->graph_theme['gridcolor'])
					->setTextColor('#'.$this->graph_theme['textcolor'])
			);
		}

		if ($this->show_right_y_axis) {
			$grid_values = $this->getValuesGridWithPosition(GRAPH_YAXIS_SIDE_RIGHT, $this->right_y_empty);

			// Do not draw label at the bottom of right Y axis to avoid label overlapping with horizontal axis arrow.
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
	}

	/**
	 * @throws Exception
	 */
	private function drawXAxis(): void {
		if (!$this->show_x_axis) {
			return;
		}

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
			if (!in_array($metric['options']['type'], [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_STAIRCASE])
					|| $metric['options']['stacked'] == SVG_GRAPH_STACKED_ON
					|| !array_key_exists($index, $this->paths)) {
				continue;
			}

			switch ($metric['options']['approximation']) {
				case APPROXIMATION_MIN:
					$approximation = 'min';
					break;
				case APPROXIMATION_MAX:
					$approximation = 'max';
					break;
				default:
					$approximation = 'avg';
			}

			$y_zero = $metric['options']['axisy'] == GRAPH_YAXIS_SIDE_RIGHT ? $this->right_y_zero : $this->left_y_zero;
			$metric_paths = [];

			foreach ($this->paths[$index] as $path) {
				$metric_path = [
					'line' => array_column($path, $approximation)
				];

				if ($metric['options']['approximation'] == APPROXIMATION_ALL) {
					$metric_path['min'] = array_column($path, 'min');
					$metric_path['max'] = array_column($path, 'max');
				}

				if (count($path) > 1) {
					if ($metric['options']['approximation'] == APPROXIMATION_ALL) {
						$metric_path['fill'] = array_merge(
							$metric_path['max'],
							array_reverse($metric_path['min'])
						);
					}
					else {
						$first_point = reset($metric_path['line']);
						$last_point = end($metric_path['line']);

						$metric_path['fill'] = array_merge($metric_path['line'], [
							[$last_point[0], $y_zero],
							[$first_point[0], $y_zero]
						]);
					}
				}

				$metric_paths[] = $metric_path;
			}

			$this->addItem(new CSvgGraphMetricsLine($metric_paths, $metric));
		}
	}

	private function drawMetricsStackedArea(): void {
		foreach ($this->metrics as $index => $metric) {
			if (!array_key_exists($index, $this->stacked_points)) {
				continue;
			}

			$metric_paths = [];

			foreach ($this->stacked_paths[$index] as $fragment) {
				$metric_path = ['line' => [], 'fill' => []];

				foreach ($fragment['path'] as $point_index => $point) {
					if ($point_index >= $fragment['line_from'] && $point_index <= $fragment['line_to']) {
						$metric_path['line'][] = $point;
					}

					$metric_path['fill'][] = $point;
				}

				$metric_paths[] = $metric_path;
			}

			$this->addItem(new CSvgGraphMetricsLine($metric_paths, $metric));
		}
	}

	private function drawMetricsPoint(): void {
		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['type'] == SVG_GRAPH_TYPE_POINTS && array_key_exists($index, $this->paths)) {
				switch ($metric['options']['approximation']) {
					case APPROXIMATION_MIN:
						$approximation = 'min';
						break;
					case APPROXIMATION_MAX:
						$approximation = 'max';
						break;
					default:
						$approximation = 'avg';
				}

				$this->addItem(new CSvgGraphMetricsPoint(array_column(reset($this->paths[$index]), $approximation), $metric));
			}
		}
	}

	private function drawMetricsBar(): void {
		foreach ($this->bar_paths as $metric_index => $path) {
			$this->addItem(new CSvgGraphMetricsBar($path, $this->metrics[$metric_index]));
		}
	}

	private function drawPercentiles(): void {
		$values = [];

		if ($this->show_percentile_left && $this->percentile_left_value > 0) {
			$values[GRAPH_YAXIS_SIDE_LEFT] = [];
		}

		if ($this->show_percentile_right && $this->percentile_right_value > 0) {
			$values[GRAPH_YAXIS_SIDE_RIGHT] = [];
		}

		foreach ($this->metrics as $index => $metric) {
			if ($metric['options']['stacked'] == SVG_GRAPH_STACKED_ON) {
				continue;
			}

			if (!array_key_exists($index, $this->points) || !array_key_exists($metric['options']['axisy'], $values)) {
				continue;
			}

			switch ($metric['options']['approximation']) {
				case APPROXIMATION_MIN:
					$approximation = 'min';
					break;
				case APPROXIMATION_MAX:
					$approximation = 'max';
					break;
				default:
					$approximation = 'avg';
			}

			foreach ($this->points[$index] as $points) {
				$values[$metric['options']['axisy']] = array_merge(
					$values[$metric['options']['axisy']],
					array_column($points, $approximation)
				);
			}
		}

		foreach ($values as $side => $points) {
			if ($side == GRAPH_YAXIS_SIDE_LEFT) {
				$percent = $this->percentile_left_value;
				$units = $this->left_y_units;
				$color = $this->graph_theme['leftpercentilecolor'];
				$scale = $this->left_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$scale_boundaries = [
						'max_negative_power' => $this->left_y_max_negative_power,
						'min_negative_power' => $this->left_y_min_negative_power,
						'min_positive_power' => $this->left_y_min_positive_power,
						'max_positive_power' => $this->left_y_max_positive_power
					];
				}
				else {
					$scale_boundaries = ['min' => $this->left_y_min, 'max' => $this->left_y_max];
				}
			}
			else {
				$percent = $this->percentile_right_value;
				$units = $this->right_y_units;
				$color = $this->graph_theme['rightpercentilecolor'];
				$scale = $this->right_y_scale;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$scale_boundaries = [
						'max_negative_power' => $this->right_y_max_negative_power,
						'min_negative_power' => $this->right_y_min_negative_power,
						'min_positive_power' => $this->right_y_min_positive_power,
						'max_positive_power' => $this->right_y_max_positive_power
					];
				}
				else {
					$scale_boundaries = ['min' => $this->right_y_min, 'max' => $this->right_y_max];
				}
			}

			if ($points) {
				sort($points);

				$value = $points[((int) ceil($percent / 100 * count($points))) - 1];
				$label = convertUnits([
					'value' => $value,
					'units' => $units
				]);

				$this->addItem(
					(new CSvgGraphPercentile(_s('%1$sth percentile: %2$s', $percent, $label), $value, $scale,
						$scale_boundaries
					))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setSize($this->canvas_width, $this->canvas_height)
						->setColor('#'.$color)
						->setSide($side)
				);
			}
		}
	}

	private function drawSimpleTriggers(): void {
		foreach ($this->simple_triggers as $index => $simple_triggers) {
			if ($simple_triggers['axisy'] == GRAPH_YAXIS_SIDE_LEFT) {
				$y_min = $this->left_y_min;
				$y_max = $this->left_y_max;
			}
			else {
				$y_min = $this->right_y_min;
				$y_max = $this->right_y_max;
			}

			if ($simple_triggers['value'] >= $y_min && $simple_triggers['value'] <= $y_max) {
				$this->addItem(
					(new CSvgGraphSimpleTrigger($simple_triggers['constant'], $simple_triggers['description'],
						$simple_triggers['value'], $y_min, $y_max))
						->setPosition($this->canvas_x, $this->canvas_y)
						->setIndex($index)
						->setSize($this->canvas_width, $this->canvas_height)
						->setColor('#'.$simple_triggers['color'])
						->setSide($simple_triggers['axisy'])
				);
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

			if ($this->canvas_x > $x1) {
				$x1 = $this->canvas_x;
			}

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
		$power = 0;
		$has_zero = true;
		$min_positive_power = -1;
		$max_positive_power = 0;
		$min_negative_power = null;
		$max_negative_power = null;
		$lower_power_shift = 0;
		$upper_power_shift = 0;
		$scale = SVG_GRAPH_AXIS_SCALE_LINEAR;

		if (!$empty_set) {
			if ($side === GRAPH_YAXIS_SIDE_LEFT) {
				$scale = $this->left_y_scale;
				$min = $this->left_y_min;
				$max = $this->left_y_max;
				$min_calculated = $this->left_y_min_calculated;
				$max_calculated = $this->left_y_max_calculated;
				$interval = $this->left_y_interval;
				$units = $this->left_y_units;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$min_positive_power = $this->left_y_min_positive_power;
					$max_positive_power = $this->left_y_max_positive_power;
					$min_negative_power = $this->left_y_min_negative_power;
					$max_negative_power = $this->left_y_max_negative_power;
					$has_zero = $this->left_y_has_zero;
					$lower_power_shift = $this->left_y_lower_power_shift;
					$upper_power_shift = $this->left_y_upper_power_shift;
				}
				else {
					$power = $this->left_y_power;
				}
			}
			elseif ($side === GRAPH_YAXIS_SIDE_RIGHT) {
				$scale = $this->right_y_scale;
				$min = $this->right_y_min;
				$max = $this->right_y_max;
				$min_calculated = $this->right_y_min_calculated;
				$max_calculated = $this->right_y_max_calculated;
				$interval = $this->right_y_interval;
				$units = $this->right_y_units;

				if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
					$min_positive_power = $this->right_y_min_positive_power;
					$max_positive_power = $this->right_y_max_positive_power;
					$min_negative_power = $this->right_y_min_negative_power;
					$max_negative_power = $this->right_y_max_negative_power;
					$has_zero = $this->right_y_has_zero;
					$lower_power_shift = $this->right_y_lower_power_shift;
					$upper_power_shift = $this->right_y_upper_power_shift;
				}
				else {
					$power = $this->right_y_power;
				}
			}
		}

		if ($scale == SVG_GRAPH_AXIS_SCALE_LOGARITHMIC) {
			$relative_values = calculateLogarithmicGraphScaleValues($min_negative_power, $max_negative_power,
				$min_positive_power, $max_positive_power, $has_zero, $min_calculated, $max_calculated, $interval,
				$units, 14, $lower_power_shift, $upper_power_shift
			);
		}
		else {
			$relative_values = calculateGraphScaleValues($min, $max, $min_calculated, $max_calculated, $interval,
				$units, $power, 14
			);
		}

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

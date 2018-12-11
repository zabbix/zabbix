<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * SVG graphs X axis class.
 */
class CSvgGraphXAxis extends CSvgTag {
	/**
	 * Axis triangle icon size.
	 *
	 * @var int
	 */
	const ZBX_ARROW_SIZE = 5;
	const ZBX_ARROW_OFFSET = 5;

	/**
	 * Optimal grid size in pixels.
	 *
	 * @var int
	 */
	const GRID_PIXELS = 30;

	/**
	 * Label degrees to rotate.
	 *
	 * @var int
	 */
	const LABEL_ROTATE_DEGREES = 270;

	/**
	 * Time period start.
	 *
	 * @var int
	 */
	private $time_from;

	/**
	 * Time period end.
	 *
	 * @var int
	 */
	private $time_till;

	/**
	 * Time period in seconds.
	 *
	 * @var int
	 */
	private $period;

	/**
	 * Time format.
	 *
	 * @var string
	 */
	private $time_format;

	/**
	 * Color for labels.
	 *
	 * @var string
	 */
	private $text_color;

	/**
	 * Color for grid and axis.
	 *
	 * @var string
	 */
	private $line_color;

	/**
	 * Color for main grid lines.
	 *
	 * @var string
	 */
	private $main_grid_color;

	/**
	 * Color for highlighted labels.
	 *
	 * @var string
	 */
	private $highlight_color;

	/**
	 * Label top offset in pixels.
	 *
	 * @var int
	 */
	private $label_top_offset;

	/**
	 * Canvas width.
	 *
	 * @var int
	 */
	private $canvas_width;

	/**
	 * Canvas height.
	 *
	 * @var int
	 */
	private $canvas_height;

	/**
	 * Array of grid objects.
	 *
	 * @var array
	 */
	private $grids;

	/**
	 * Array of labels on X axis.
	 *
	 * @var array
	 */
	private $labels;

	/**
	 * Array of main grid values.
	 *
	 * @var array
	 */
	private $main_grid_positions;

	/**
	 * Array of secondary grid values.
	 *
	 * @var array
	 */
	private $sub_grid_positions;

	public function __construct($time_from, $time_till) {
		$this->time_from = $time_from;
		$this->time_till = $time_till;

		$this->main_grid_positions = [];
		$this->sub_grid_positions = [];

		$this->labels = [];
		$this->grids = [];

		$this->period = $this->time_till - $this->time_from;
		$this->time_format = (date('Y', $this->time_from) === date('Y', $this->time_till))
			? DATE_TIME_FORMAT_SHORT
			: DATE_FORMAT;
	}

	/**
	 * Set text color.
	 *
	 * @param string $color  Color value.
	 *
	 * @return CSvgGraphXAxis
	 */
	public function setTextColor($color) {
		$this->text_color = $color;

		return $this;
	}

	/**
	 * Set label top offset.
	 *
	 * @param int $top_offset  Label top offset in pixels.
	 *
	 * @return CSvgGraphXAxis
	 */
	public function setLabelTopOffset($top_offset) {
		$this->label_top_offset = (int) $top_offset;

		return $this;
	}

	/**
	 * Set line color.
	 *
	 * @param string $color  Color value.
	 *
	 * @return CSvgGraphXAxis
	 */
	public function setLineColor($color) {
		$this->line_color = $color;

		return $this;
	}

	/**
	 * Set main grid color.
	 *
	 * @param string $color  Color value.
	 *
	 * @return CSvgGraphXAxis
	 */
	public function setGridMainColor($color) {
		$this->main_grid_color = $color;

		return $this;
	}

	/**
	 * Set color for highlighted labels.
	 *
	 * @param string $color  Color value.
	 *
	 * @return CSvgGraphXAxis
	 */
	public function setHighlightColor($color) {
		$this->highlight_color = $color;

		return $this;
	}

	/**
	 * Function creates grids for main and sub milestones on X axis.
	 */
	protected function makeGrids() {
		// Add grid for detailed values.
		$this->grids[] = (new CSvgGraphGrid($this->sub_grid_positions))
			->setDimension(CSvgGraphGrid::GRID_DIMENSION_VERTICAL)
			->setPosition($this->x, $this->y - $this->canvas_height)
			->setSize($this->canvas_width, $this->canvas_height)
			->setColor($this->line_color);

		// Add grid for main values.
		$this->grids[] = (new CSvgGraphGrid($this->main_grid_positions))
			->setDimension(CSvgGraphGrid::GRID_DIMENSION_VERTICAL)
			->setPosition($this->x, $this->y - $this->canvas_height)
			->setSize($this->canvas_width, $this->canvas_height)
			->setColor($this->main_grid_color);
	}

	/**
	 * Return CSS style definitions for axis as array.
	 *
	 * @return array
	 */
	public function makeStyles() {
		$styles = [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS.' .'.CSvgTag::ZBX_STYLE_GRAPH_LABEL_MAIN => [
				'text-anchor' => 'end',
				'fill' => $this->highlight_color,
				'font-size' => '11px'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS.' .'.CSvgTag::ZBX_STYLE_GRAPH_LABEL_SUB => [
				'text-anchor' => 'end',
				'font-size' => '10px'
			]
		];

		// Grids must be created here because makeStyles is called before toString.
		$this->getDateTimeIntervals();
		$this->makeGrids();

		foreach ($this->grids as $grid) {
			foreach ($grid->makeStyles() as $grid_class_name => $grid_style) {
				$styles['.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS.' '.$grid_class_name] = $grid_style;
			}
		}

		return $styles;
	}

	public function toString($destroy = true) {
		$time_formatted_from = zbx_date2str(_($this->time_format), $this->time_from);
		$time_formatted_till = zbx_date2str(_($this->time_format), $this->time_till);

		return (new CSvgGroup())
			->addItem([
				$this->grids,
				$this->makeArrow(),
				// Add period start label.
				(new CSvgText($this->x, $this->y + $this->label_top_offset, $time_formatted_from))
					->addClass(CSvgTag::ZBX_STYLE_GRAPH_LABEL_MAIN)
					->rotate(self::LABEL_ROTATE_DEGREES, $this->x, $this->y + $this->label_top_offset),
				// Add time interval labels.
				$this->labels,
				// Add period end label.
				(new CSvgText($this->x + $this->width, $this->y + $this->label_top_offset, $time_formatted_till))
					->addClass(CSvgTag::ZBX_STYLE_GRAPH_LABEL_MAIN)
					->rotate(self::LABEL_ROTATE_DEGREES, $this->x + $this->width, $this->y + $this->label_top_offset)
			])
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_AXIS.' '.CSvgTag::ZBX_STYLE_GRAPH_AXIS_BOTTOM)
			->toString($destroy);
	}

	protected function makeArrow() {
		$offset = ceil(self::ZBX_ARROW_SIZE / 2);
		$x = $this->x + $this->width + self::ZBX_ARROW_OFFSET;
		$y = $this->y;

		return [
			// Draw axis line.
			(new CSvgPath())
				->setAttribute('shape-rendering', 'crispEdges')
				->moveTo($this->x, $y)
				->lineTo($x, $y),
			// Draw arrow.
			(new CSvgPath())
				->moveTo($x + self::ZBX_ARROW_SIZE, $y)
				->lineTo($x, $y - $offset)
				->lineTo($x, $y + $offset)
				->closePath()
		];
	}

	/**
	 * Set canvas size.
	 *
	 * @param int $width        Canvas width.
	 * @param int $height       Canvas height.
	 *
	 * @return CSvgTag
	 */
	public function setCanvasSize($width, $height) {
		$this->canvas_width = (int) $width;
		$this->canvas_height = (int) $height;

		return $this;
	}

	private function calculateTimeInterval() {
		$intervals = [
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 60],			// minute and 1 second
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 12],			// minute and 5 seconds
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 6],			// 1 minute and 10 seconds
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 2],			// 1 minute and 30 seconds
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN],				// 1 hour and 1 minute
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 2],			// 1 hour and 2 minutes
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 5],			// 1 hour and 5 minutes
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 15],		// 1 hour and 15 minutes
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 30],		// 1 hour and 30 minutes
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR],				// 1 day and 1 hours
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR * 3],			// 1 day and 3 hours
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR * 6],			// 1 day and 6 hours
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR * 12],		// 1 day and 12 hours
			['main' => SEC_PER_WEEK, 'sub' => SEC_PER_DAY],				// 1 week and 1 day
			['main' => SEC_PER_MONTH, 'sub' => SEC_PER_WEEK],			// 1 month and 1 week
			['main' => SEC_PER_MONTH, 'sub' => SEC_PER_WEEK * 2],		// 1 month and 2 weeks
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH],			// 1 year and 30 days
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH * 3],		// 1 year and 90 days
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH * 4],		// 1 year and 120 days
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH * 6],		// 1 year and 180 days
			['main' => SEC_PER_YEAR * 5, 'sub' => SEC_PER_YEAR],		// 5 years and 1 year
			['main' => SEC_PER_YEAR * 10, 'sub' => SEC_PER_YEAR * 2],	// 10 years and 2 years
			['main' => SEC_PER_YEAR * 15, 'sub' => SEC_PER_YEAR * 3],	// 15 years and 3 years
			['main' => SEC_PER_YEAR * 20, 'sub' => SEC_PER_YEAR * 5],	// 20 years and 5 years
			['main' => SEC_PER_YEAR * 30, 'sub' => SEC_PER_YEAR * 10],	// 30 years and 10 years
			['main' => SEC_PER_YEAR * 40, 'sub' => SEC_PER_YEAR * 20],	// 40 years and 20 years
			['main' => SEC_PER_YEAR * 60, 'sub' => SEC_PER_YEAR * 30],	// 60 years and 30 years
			['main' => SEC_PER_YEAR * 80, 'sub' => SEC_PER_YEAR * 40]	// 80 years and 40 years
		];

		// Default inteval values.
		$time_interval = (self::GRID_PIXELS * $this->period) / $this->width;
		$distance = SEC_PER_YEAR * 5;
		$time_intervals = [
			'main' => 0,
			'sub' => 0
		];

		foreach ($intervals as $interval) {
			$time = abs($interval['sub'] - $time_interval);

			if ($time < $distance) {
				$distance = $time;
				$time_intervals = $interval;
			}
		}

		return $time_intervals;
	}

	private function getDateTimeIntervals() {
		$interval = $this->calculateTimeInterval();

		$dt = [];
		$modifier = [];
		$format = [];

		foreach (['main', 'sub'] as $type) {
			$dt[$type] = new DateTime();
			$dt[$type]->setTimestamp($this->time_from);

			if ($interval[$type] >= SEC_PER_YEAR) {
				$years = $interval[$type] / SEC_PER_YEAR;
				$year = (int) $dt[$type]->format('Y');
				$dt[$type]->modify('first day of January this year 00:00:00 -'.($year % $years).' year');
				$modifier[$type] = '+ '.$years.' year';
				$format[$type] = _x('Y', DATE_FORMAT_CONTEXT);
			}
			elseif ($interval[$type] >= SEC_PER_MONTH) {
				$months = $interval[$type] / SEC_PER_MONTH;
				$month = (int) $dt[$type]->format('m');
				$dt[$type]->modify('first day of this month 00:00:00 -'.(($month - 1) % $months).' month');
				$modifier[$type] = '+ '.$months.' month';
				$format[$type] = ($type == 'main') ? _('m-d') : _('M');
			}
			elseif ($interval[$type] >= SEC_PER_WEEK) {
				$weeks = $interval[$type] / SEC_PER_WEEK;
				$week = (int) $dt[$type]->format('W');
				$day_of_week = (int) $dt[$type]->format('w');
				$dt[$type]->modify('today -'.(($week - 1) % $weeks).' week -'.$day_of_week.' day');
				$modifier[$type] = '+ '.$weeks.' week';
				$format[$type] = _('m-d');
			}
			elseif ($interval[$type] >= SEC_PER_DAY) {
				$days = $interval[$type] / SEC_PER_DAY;
				$day = (int) $dt[$type]->format('d');
				$dt[$type]->modify('today -'.(($day - 1) % $days).' day');
				$modifier[$type] = '+ '.$days.' day';
				$format[$type] = _('m-d');
			}
			elseif ($interval[$type] >= SEC_PER_HOUR) {
				$hours = $interval[$type] / SEC_PER_HOUR;
				$hour = (int) $dt[$type]->format('H');
				$minute = (int) $dt[$type]->format('i');
				$second = (int) $dt[$type]->format('s');
				$dt[$type]->modify('-'.($hour % $hours).' hour -'.$minute.' minute -'.$second.' second');
				$modifier[$type] = '+ '.$hours.' hour';
				$format[$type] = TIME_FORMAT;
			}
			elseif ($interval[$type] >= SEC_PER_MIN) {
				$minutes = $interval[$type] / SEC_PER_MIN;
				$minute = (int) $dt[$type]->format('i');
				$second = (int) $dt[$type]->format('s');
				$dt[$type]->modify('-'.($minute % $minutes).' minute -'.$second.' second');
				$modifier[$type] = '+ '.$minutes.' min';
				$format[$type] = ($type == 'main') ? _('H:i:s') : TIME_FORMAT;
			}
			else {
				$seconds = $interval[$type];
				$second = (int) $dt[$type]->format('s');
				$dt[$type]->modify('-'.($second % $seconds).' second');
				$modifier[$type] = '+ '.$seconds.' second';
				$format[$type] = _('H:i:s');
			}
		}

		// It is necessary to align the X axis after the jump from winter to summer time.
		$prev_dst = (bool) $dt['sub']->format('I');
		$dst_offset = $dt['sub']->getOffset();
		$do_align = false;

		$prev_time = $this->time_from;
		if ($interval['main'] == SEC_PER_MONTH) {
			$dt_start = new DateTime();
			$dt_start->setTimestamp($this->time_from);
			$prev_month = (int) $dt_start->format('m');
		}

		$position = 0;

		while (true) {
			$dt['sub']->modify($modifier['sub']);

			if (SEC_PER_HOUR < $interval['sub'] && $interval['sub'] < SEC_PER_DAY) {
				if ($do_align) {
					$hours = $interval['sub'] / SEC_PER_HOUR;
					$hour = (int) $dt['sub']->format('H');
					if ($hour % $hours) {
						$dt['sub']->modify($dst_offset.' second');
					}

					$do_align = false;
				}

				$dst = (bool) $dt['sub']->format('I');

				// Check if in particular period time was switched to daylight saving time.
				if ($dst && $prev_dst != $dst) {
					$dst_offset -= $dt['sub']->getOffset();
					$do_align = ($interval['sub'] > abs($dst_offset));
					$prev_dst = $dst;
				}
			}

			if ($dt['main'] < $dt['sub']) {
				$dt['main']->modify($modifier['main']);
			}

			if ($interval['main'] == SEC_PER_MONTH) {
				$month = (int) $dt['sub']->format('m');

				$draw_main = ($month != $prev_month);
				$prev_month = $month;
			}
			else {
				$draw_main = ($dt['main'] == $dt['sub']);
			}

			$time = $dt['sub']->format('U');
			$delta_x = bcsub($time, $prev_time) * $this->width / $this->period;
			$position += $delta_x;

			if ($draw_main) {
				$time_formatted = $dt['main']->format($format['main']);
				$label_style = CSvgTag::ZBX_STYLE_GRAPH_LABEL_MAIN;
				$this->main_grid_positions[] = $position;
			}
			else {
				$time_formatted = $dt['sub']->format($format['sub']);
				$label_style = CSvgTag::ZBX_STYLE_GRAPH_LABEL_SUB;
				$this->sub_grid_positions[] = $position;
			}

			if ($position >= ($this->width - 10)) {
				// Do not continue once position is out of canvas.
				break;
			}
			elseif ($position > 10) {
				$this->labels[] = (new CSvgText($position + $this->x + 1, $this->y + $this->label_top_offset, $time_formatted))
					->rotate(self::LABEL_ROTATE_DEGREES, $position + $this->x + 1, $this->y + $this->label_top_offset)
					->addClass($label_style);
			}

			$prev_time = $time;
		}
	}
}

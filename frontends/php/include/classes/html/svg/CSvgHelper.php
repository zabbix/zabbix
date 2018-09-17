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


class CSvgHelper {

	public static function makeHeader($x, $y, $text, $color) {
		return (new CText($x, $y, $text, $color))->setAttribute('text-anchor', 'middle');
	}

	public static function makeHGrid($x, $y, $width, $height, $rows, $color) {
		$lines = [];

		for ($i = 0; $i <= $rows; $i++) {
			$_y = $y + $i * $height/$rows;
			$lines[] = (new CLine($x, $_y, $x + $width, $_y, $color))
				->setDashed();
		}

		return $lines;
	}

	public static function makeVGrid($x, $y, $width, $height, $columns, $color) {
		$lines = [];

		for ($i = 0; $i <= $columns; $i++) {
			$_x = $x + $i * $width/$columns;
			$lines[] = (new CLine($_x, $y, $_x, $y + $height, $color))
				->setDashed();
		}

		return $lines;
	}

	public static function makeGridVLegend($x, $y, $height, $rows, $grid_min, $grid_max, $color) {
		$text = [];

		for ($i = 0; $i <= $rows; $i++) {
			$_y = $y + $i * $height/$rows;
			$value = $grid_max - ($grid_max - $grid_min) * ($i / $rows);
			$text[] = new CText($x, $_y, $value, $color);
		}

		return $text;
	}

	public static function makeGridHLegend($x, $y, $width, $columns, array $labels, $color) {
		$text = [];

		for ($i = 0; $i < $columns; $i++) {
			$_x = $x + $i * $width/$columns;
			$text[] = (new CText($_x, $y, $labels[$i], $color))
				->setAttribute('text-anchor', 'end')
				->setAngle(-90);
		}

		return $text;
	}

	public static function makeSeries($x, $y, $width, $height, $columns, $yperiod, $data, $color, $type) {
		$text = [];

		if ($type == 'bar') {
			$bar_width = $width/$columns/2;

			for ($i = 0; $i < $columns; $i++ ) {
				$bar_height = $height * $data[$i]['y']/$yperiod;
				$x_curr = $x + $i * $width/$columns - $bar_width/2;
				$text[] = (new CRect($x_curr, $y + $height - $bar_height, $bar_width, $bar_height))
					->setFillColor($color)
					->setStrokeColor($color);
			}
		}
		else if ($type == 'line') {
			// vertical lines
			$bar_width = $width/$columns/2;

			for ($i = 1; $i < $columns; $i++ ) {
				$bar_height = $height * $data[$i]['y']/$yperiod;
				$bar_height_old = $height * $data[$i - 1]['y']/$yperiod;
				$x_curr = $x + $i * $width/$columns;
				$x_old = $x + ($i - 1) * $width/$columns;
				$text[] = (new CLine($x_curr, $y + $height - $bar_height, $x_old, $y + $height - $bar_height_old, $color))
					->setAttribute('stroke-width', '5');
			}
			for ($i = 0; $i < $columns; $i++ ) {
				$bar_height = $height * $data[$i]['y']/$yperiod;
				$x_curr = $x + $i * $width/$columns;
				$text[] = (new CTag('ellipse', true))
						->setAttribute('cx', $x_curr)
						->setAttribute('cy', $y + $height - $bar_height)
						->setAttribute('rx', 6)
						->setAttribute('ry', 6)
						->setAttribute('fill', '#FFFFFF')
						->setAttribute('stroke', $color)
						->setAttribute('stroke-width', '2');
			}
		}

		return $text;
	}

}

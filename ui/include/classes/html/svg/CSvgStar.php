<?php
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


class CSvgStar extends CSvgPolygon {

	public function __construct($x, $y, $size, $points_count = 5) {
		$radius_outer = $size / 2;          // Star fits in size × size.
		$radius_inner = $radius_outer / 2;  // Inner radius.

		$points = [];
		$angle_step = M_PI / $points_count;  // Half-step for alternating outer/inner points.

		for ($i = 0; $i < 2 * $points_count; $i++) {
			$r = $i % 2 === 0 ? $radius_outer : $radius_inner;
			$px = $x + $r * cos($i * $angle_step - M_PI / 2);
			$py = $y + $r * sin($i * $angle_step - M_PI / 2);
			$points[] = [round($px, 1), round($py, 1)];
		}

		parent::__construct($points);
	}
}

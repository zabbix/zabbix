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


/**
 * A helper class to perform operations with colors.
 */
class CColorHelper {

	/**
	 * Interpolate two colors.
	 *
	 * @param string $color1    Hex color.
	 * @param string $color2    Hex color.
	 * @param float  $position  Value between 0 and 1.
	 *
	 * @return string
	 */
	public static function interpolateColor(string $color1, string $color2, float $position): string {
		$color1 = ltrim($color1, '#');
		$color2 = ltrim($color2, '#');

		$r1 = hexdec(substr($color1, 0, 2));
		$g1 = hexdec(substr($color1, 2, 2));
		$b1 = hexdec(substr($color1, 4, 2));

		$r2 = hexdec(substr($color2, 0, 2));
		$g2 = hexdec(substr($color2, 2, 2));
		$b2 = hexdec(substr($color2, 4, 2));

		$r = (int) round($r1 + ($r2 - $r1) * $position);
		$g = (int) round($g1 + ($g2 - $g1) * $position);
		$b = (int) round($b1 + ($b2 - $b1) * $position);

		return sprintf("#%02x%02x%02x", $r, $g, $b);
	}
}

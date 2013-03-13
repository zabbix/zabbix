<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


define('LINE_TYPE_NORMAL',	'0');
define('LINE_TYPE_BOLD',	'1');

/**
 * Change luminosity of RGB color
 *
 * @param resource $image image reference
 * @param array $f background color, array of RGB
 * @param array $t foreground color, array of RGB
 * @param float $p transformation index in range of 0-1
 */
function lip($image, $f, $t, $p) {
	$p = round($p, 1);
	$r = $f[0] + ($t[0] - $f[0]) * $p;
	$g = $f[1] + ($t[1] - $f[1]) * $p;
	$b = $f[2] + ($t[2] - $f[2]) * $p;

	return imagecolorresolvealpha($image, $r, $g, $b, 0);
}

/**
 * Draw antialised line
 *
 * @param resource $image image reference
 * @param int $x1 first x coordinate
 * @param int $y1 first y coordinate
 * @param int $x2 second x coordinate
 * @param int $y2 second y coordinate
 * @param int $color line color
 * @param int $style line style, one of LINE_TYPE_NORMAL (default), LINE_TYPE_BOLD (bold line)
 */
function aline($image, $x1, $y1, $x2, $y2, $color, $style = LINE_TYPE_NORMAL) {
	$x1 = round($x1);
	$y1 = round($y1);
	$x2 = round($x2);
	$y2 = round($y2);

	// Get foreground line color
	$lc = imagecolorsforindex($image, $color);
	$lc = array($lc["red"], $lc["green"], $lc["blue"]);

	$dx = $x2 - $x1;
	$dy = $y2 - $y1;

	if (abs($dx) > abs($dy)) {
		if ($dx < 0) {
			$dx = -$dx; $dy = -$dy;
			$tmp = $x2; $x2 = $x1; $x1 = $tmp;
			$tmp = $y2; $y2 = $y1; $y1 = $tmp;
		}
		for ($x = $x1, $y = $y1; $x <= $x2; $x++, $y = $y1 + ($x - $x1) * $dy / $dx) {
			$yfrac = $y - floor($y);
			$yint = round($y) - round($yfrac);

			if (LINE_TYPE_BOLD == $style) {
				$bc = imagecolorsforindex($image, imagecolorat($image, $x, $yint - 1));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $x, $yint - 1, lip($image, $lc, $bc, $yfrac));

				$bc = imagecolorsforindex($image, imagecolorat($image, $x, $yint + 1));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $x, $yint + 1, lip($image, $lc, $bc, 1 - $yfrac));

				imagesetpixel($image, $x, $yint, $color);
			}
			else {
				$bc = imagecolorsforindex($image, imagecolorat($image, $x, $yint));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $x, $yint, lip($image, $lc, $bc, $yfrac));

				$bc = imagecolorsforindex($image, imagecolorat($image, $x, $yint + 1));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $x, $yint + 1, lip($image, $lc, $bc, 1 - $yfrac));
			}
		}
	}
	else {
		if ($dy < 0) {
			$dx = -$dx; $dy = -$dy;
			$tmp = $x2; $x2 = $x1; $x1 = $tmp;
			$tmp = $y2; $y2 = $y1; $y1 = $tmp;
		}
		for ($y = $y1, $x = $x1; $y <= $y2; $y++, $x = $x1 + ($y - $y1) * $dx / $dy)
		{
			$xfrac = $x - floor($x);
			$xint = round($x) - round($xfrac);

			if (LINE_TYPE_BOLD == $style) {
				$bc = imagecolorsforindex($image, imagecolorat($image, $xint - 1, $y));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $xint - 1, $y, lip($image, $lc, $bc, $xfrac));

				$bc = imagecolorsforindex($image, imagecolorat($image, $xint + 1, $y));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $xint + 1, $y, lip($image, $lc, $bc, 1 - $xfrac));

				imagesetpixel($image, $xint, $y, $color);
			}
			else {
				$bc = imagecolorsforindex($image, imagecolorat($image, $xint, $y));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $xint, $y, lip($image, $lc, $bc, $xfrac));

				$bc = imagecolorsforindex($image, imagecolorat($image, $xint + 1, $y));
				$bc = array($bc["red"], $bc["green"], $bc["blue"]);
				imagesetpixel($image, $xint + 1, $y, lip($image, $lc, $bc, 1 - $xfrac));
			}
		}
	}
}

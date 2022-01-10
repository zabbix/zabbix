<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class to generate SVG arrow.
 */
class CSvgArrow extends CSvg {

	const ZBX_STYLE_ARROW = 'svg-arrow';
	const ZBX_STYLE_ARROW_UP = 'svg-arrow-up';
	const ZBX_STYLE_ARROW_DOWN = 'svg-arrow-down';
	const ZBX_STYLE_ARROW_UP_DOWN = 'svg-arrow-up-down';

	/**
	 * Viewbox attribute fits the pixel-grid used in drawArrow function.
	 *
	 * @var string
	 */
	protected $viewbox = '0 0 6 10';

	/**
	 * Options
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * @param array   $options
	 * @param bool    $options['up']
	 * @param bool    $options['down']
	 * @param string  $options['fill_color']
	 */
	function __construct(array $options = []) {
		parent::__construct();

		$this->options = $options + [
			'up' => false,
			'down' => false,
			'fill_color' => null
		];

		$this->addClass(self::ZBX_STYLE_ARROW);

		if ($this->options['up'] && $this->options['down']) {
			$this->addClass(self::ZBX_STYLE_ARROW_UP_DOWN);
		}
		elseif ($this->options['up']) {
			$this->addClass(self::ZBX_STYLE_ARROW_UP);
		}
		elseif ($this->options['down']) {
			$this->addClass(self::ZBX_STYLE_ARROW_DOWN);
		}

		$fill_color = $this->options['fill_color']
			? 'fill: #'.$this->options['fill_color']
			: null;

		$this
			->setAttribute('viewBox', $this->viewbox)
			->addItem(
				(new CSvgPolygon($this->drawArrow()))->addStyle($fill_color)
			);
	}

	/**
	 * Create polygon path for arrow shape.
	 */
	protected function drawArrow(): array {
		/**
		 * Arrow pixel grid.
		 *
		 *    XXXXXXX
		 *    0123456
		 * Y0 ..cde..
		 * Y1 ..###..
		 * Y2 .#####.
		 * Y3 b#a#g#f
		 * Y4 ..###..
		 * Y5 ..###..
		 * Y6 ..###..
		 * Y7 m#n#h#i
		 * Y8 .#####.
		 * Y9 ..###..
		 * Y10..lkj..
		 */

		[
			$a, $b, $c, $d,
			$e, $f, $g, $h,
			$i, $j, $k, $l,
			$m, $n
		] = [
			[2, 3], [0, 3], [2, 0], [3, 0],
			[4, 0], [6, 3], [4, 3], [4, 7],
			[6, 7], [4, 10], [3, 10], [2, 10],
			[0, 7], [2, 7]
		];

		$head = $this->options['up']
			? [$a, $b, $d, $f, $g]
			: [$a, $c, $e, $g];

		$foot = $this->options['down']
			? [$h, $i, $k, $m, $n]
			: [$h, $j, $l, $n];

		return array_merge($head, $foot);
	}
}

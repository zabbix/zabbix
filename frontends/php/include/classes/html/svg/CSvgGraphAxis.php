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
 * SVG graphs axis class.
 */
class CSvgGraphAxis extends CSvgTag {

	const VERTICAL = 0x0;
	const HORIZONTAL = 0x1;
	const TOP = 0x02;
	const LEFT = 0x02;

	const AXIS_VERTICAL_RIGHT = 0;
	const AXIS_VERTICAL_LEFT = 0x02;
	const AXIS_HORIZONTAL_BOTTOM = 0x01;

	/**
	 * CSS class name for axis container.
	 *
	 * @var array
	 */
	public $css_class = [
		self::AXIS_VERTICAL_RIGHT => 'axis axis-vertical-right',
		self::AXIS_VERTICAL_LEFT => 'axis axis-vertical-left',
		self::AXIS_HORIZONTAL_BOTTOM => 'axis axis-horizontal-bottom'
	];

	/**
	 * Axis type. One of CSvgGraphAxis::AXIS_* constants.
	 *
	 * @var int
	 */
	private $type;

	/**
	 * Array of labels. Key is coordinate, value is text label.
	 *
	 * @var array
	 */
	private $labels;

	/**
	 * Axis triangle icon size.
	 *
	 * @var int
	 */
	private $arrow_size = 5;
	private $arrow_offset = 3;

	/**
	 * Axis container.
	 *
	 * @var CSvgGroup
	 */
	private $container;
	private $width = 0;
	private $height = 0;
	private $x = 0;
	private $y = 0;

	public function __construct(array $labels, $type) {
		$this->labels = $labels;
		$this->type = $type;
		$this->container = new CSvgGroup();
	}

	/**
	 * Set axis container size.
	 *
	 * @param int $width    Axis container width.
	 * @param int $height   Axis container height.
	 */
	public function setSize($width, $height) {
		$this->width = $width;
		$this->height = $height;

		return $this;
	}

	/**
	 * Set axis container position.
	 *
	 * @param int $x        Horizontal position of container element.
	 * @param int $y        Veritical position of container element.
	 */
	public function setPosition($x, $y) {
		$this->x = $x;
		$this->y = $y;

		return $this;
	}

	/**
	 * Get axis line with arrow.
	 *
	 * @return CSvgPath
	 */
	private function getAxis() {
		$path = new CSvgPath();
		$size = $this->arrow_size;
		$offset = ceil($size/2);

		if ($this->type & self::HORIZONTAL) {
			$y = $this->type & self::TOP ? $this->height + $this->y : $this->y;
			$x = $this->width + $this->x;
			$path->moveTo($this->x, $y)
				->lineTo($x , $y);

			if ($size) {
				$path->moveTo($x + $size, $y)
					->lineTo($x, $y - $offset)
					->lineTo($x, $y + $offset)
					->lineTo($x + $size, $y);
			}
		}
		else {
			$x = $this->type & self::LEFT ? $this->x : $this->width + $this->x;
			$y = $this->y - $this->arrow_offset;
			$path->moveTo($x, $y)
				->lineTo($x, $this->height + $y);

			if ($size) {
				$path->moveTo($x, $y - $size)
					->lineTo($x - $offset, $y)
					->lineTo($x + $offset, $y)
					->lineTo($x, $y - $size);
			}
		}

		/* TODO: move to global style */
		$path->setStrokeColor('silver')
			->setFillColor('white');

		return $path;
	}

	/**
	 * Return array of initialized CSvgText objects for axis labels.
	 *
	 * @return array
	 */
	private function getLabels() {
		$x = 0;
		$y = 0;
		$labels = [];
		$is_horizontal = (bool) ($this->type & self::HORIZONTAL);

		if ($is_horizontal) {
			$axis = 'x';
			$y = $this->type & self::TOP ? 0 : $this->height;
			$align = 'middle';
			$valign = 'before-edge';
		}
		else {
			$x = $this->type & self::LEFT ? 0 : $this->width;
			$axis = 'y';
			$align = $this->type & self::LEFT ? 'end' : 'start';
			$valign = 'middle';
		}

		foreach ($this->labels as $pos => $label) {
			$$axis = $pos;

			if (!$is_horizontal) {
				// Flip upside down.
				$y = $this->height - $y;
			}

			$labels[] = (new CSvgText($x + $this->x, $y + $this->y, $label, /* TODO: remove param */'silver'))
				/* TODO: move to global style */->setAttribute('text-anchor', $align)
				/* TODO: move to global style */->setAttribute('dominant-baseline', $valign);
		}

		return $labels;
	}

	public function toString($destroy = true) {
		$this->container->additem([
			$this->getAxis(),
			$this->getLabels()
		])
			->addClass($this->css_class[$this->type]);

		return $this->container->toString($destroy);
	}
}

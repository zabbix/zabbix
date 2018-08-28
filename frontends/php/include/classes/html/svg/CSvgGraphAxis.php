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

	/**
	 * CSS class name for axis container.
	 *
	 * @var array
	 */
	public $css_class;

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
	private $arrow_offset = 5;

	/**
	 * Color for axis and labels.
	 *
	 * @var string
	 */
	private $color = '#787878';

	/**
	 * Axis container.
	 *
	 * @var CSvgGroup
	 */
	private $container;

	/**
	 * Visibility of axis line with arrow.
	 *
	 * @var bool
	 */
	private $axis_visible = true;

	public function __construct(array $labels, $type) {
		$this->css_class = [
			GRAPH_YAXIS_SIDE_RIGHT => CSvgTag::ZBX_STYLE_GRAPH_AXIS.' '.CSvgTag::ZBX_STYLE_GRAPH_AXIS_RIGHT,
			GRAPH_YAXIS_SIDE_LEFT => CSvgTag::ZBX_STYLE_GRAPH_AXIS.' '.CSvgTag::ZBX_STYLE_GRAPH_AXIS_LEFT,
			GRAPH_YAXIS_SIDE_BOTTOM => CSvgTag::ZBX_STYLE_GRAPH_AXIS.' '.CSvgTag::ZBX_STYLE_GRAPH_AXIS_BOTTOM,
		];

		$this->labels = $labels;
		$this->type = $type;
		$this->container = new CSvgGroup();
	}

	/**
	 * Return CSS style definitions for axis as array.
	 *
	 * @return array
	 */
	public function getStyles() {
		return [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS.' path' => [
				'stroke' => $this->color,
				'fill' => 'transparent'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS.' text' => [
				'fill' => $this->color,
				'font-size' => '11px',
				'alignment-baseline' => 'middle',
				'dominant-baseline' => 'middle'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS_RIGHT.' text' => [
				'text-anchor' => 'start'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS_LEFT.' text' => [
				'text-anchor' => 'end'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS_BOTTOM.' text' => [
				'text-anchor' => 'middle'
			]
		];
	}

	/**
	 * Set axis line visibility.
	 *
	 * @param bool $visible   True if should be visible.
	 * @return CSvgGraphAxis
	 */
	public function setAxisVisibility($visible) {
		$this->axis_visible = $visible;

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

		if ($this->type === GRAPH_YAXIS_SIDE_BOTTOM) {
			$y = $this->y;
			$x = $this->width + $this->x + $this->arrow_offset;
			$path->moveTo($this->x, $y)
				->lineTo($x, $y);

			if ($size) {
				$path->moveTo($x + $size, $y)
					->lineTo($x, $y - $offset)
					->lineTo($x, $y + $offset)
					->lineTo($x + $size, $y);
			}
		}
		else {
			$x = $this->type === GRAPH_YAXIS_SIDE_RIGHT ? $this->x : $this->width + $this->x;
			$y = $this->y - $this->arrow_offset;
			$path->moveTo($x, $y)
				->lineTo($x, $this->height + $y + $this->arrow_offset);

			if ($size) {
				$path->moveTo($x, $y - $size)
					->lineTo($x - $offset, $y)
					->lineTo($x + $offset, $y)
					->lineTo($x, $y - $size);
			}
		}

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
		$is_horizontal = $this->type === GRAPH_YAXIS_SIDE_BOTTOM;

		if ($is_horizontal) {
			$axis = 'x';
			// Label margin from axis.
			$margin = 7;
			$y = $this->height - $margin;
		}
		else {
			// Label margin from axis.
			$margin = 5;
			$axis = 'y';
			$x = $this->type === GRAPH_YAXIS_SIDE_RIGHT ?  $margin : $this->width - $margin;
		}

		foreach ($this->labels as $pos => $label) {
			$$axis = $pos;

			if ($this->type === GRAPH_YAXIS_SIDE_RIGHT && $y == 0) {
				continue;
			}

			if (!$is_horizontal) {
				// Flip upside down.
				$y = $this->height - $y;
			}

			$labels[] = (new CSvgText($x + $this->x, $y + $this->y, $label));
		}

		return $labels;
	}

	public function toString($destroy = true) {
		$this->container->additem([
			$this->axis_visible ? $this->getAxis() : null,
			$this->getLabels()
		])
			->addClass($this->css_class[$this->type]);

		return $this->container->toString($destroy);
	}
}

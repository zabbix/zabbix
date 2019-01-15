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
 * SVG graphs Y axis class.
 */
class CSvgGraphYAxis extends CSvgTag {

	/**
	 * CSS class name for axis container.
	 *
	 * @var array
	 */
	private $css_class;

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
	const ZBX_ARROW_SIZE = 5;
	const ZBX_ARROW_OFFSET = 5;

	/**
	 * Color for labels.
	 *
	 * @var string
	 */
	private $text_color;

	/**
	 * Color for axis.
	 *
	 * @var string
	 */
	private $line_color;

	public function __construct(array $labels, $type) {
		$this->css_class = [
			GRAPH_YAXIS_SIDE_RIGHT => CSvgTag::ZBX_STYLE_GRAPH_AXIS.' '.CSvgTag::ZBX_STYLE_GRAPH_AXIS_RIGHT,
			GRAPH_YAXIS_SIDE_LEFT => CSvgTag::ZBX_STYLE_GRAPH_AXIS.' '.CSvgTag::ZBX_STYLE_GRAPH_AXIS_LEFT
		];

		$this->labels = $labels;
		$this->type = $type;
	}

	/**
	 * Return CSS style definitions for axis as array.
	 *
	 * @return array
	 */
	public function makeStyles() {
		return [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS.' path' => [
				'stroke' => $this->line_color,
				'fill' => 'transparent'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS.' text' => [
				'fill' => $this->text_color,
				'font-size' => '11px',
				'alignment-baseline' => 'middle'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS_RIGHT.' text' => [
				'text-anchor' => 'start'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AXIS_LEFT.' text' => [
				'text-anchor' => 'end'
			]
		];
	}

	/**
	 * Set text color.
	 *
	 * @param string $color  Color value.
	 *
	 * @return CSvgGraphYAxis
	 */
	public function setTextColor($color) {
		$this->text_color = $color;

		return $this;
	}

	/**
	 * Set line color.
	 *
	 * @param string $color  Color value.
	 *
	 * @return CSvgGraphYAxis
	 */
	public function setLineColor($color) {
		$this->line_color = $color;

		return $this;
	}

	/**
	 * Get axis line with arrow.
	 *
	 * @return CSvgPath
	 */
	private function getAxis() {
		$offset = ceil(self::ZBX_ARROW_SIZE / 2);

		$x = ($this->type == GRAPH_YAXIS_SIDE_RIGHT) ? $this->x : $this->x + $this->width;
		$y = $this->y - self::ZBX_ARROW_OFFSET;

		return [
			// Draw axis line.
			(new CSvgPath())
				->setAttribute('shape-rendering', 'crispEdges')
				->moveTo($x, $y)
				->lineTo($x, $this->height + $y + self::ZBX_ARROW_OFFSET),
			// Draw arrow.
			(new CSvgPath())
				->moveTo($x, $y - self::ZBX_ARROW_SIZE)
				->lineTo($x - $offset, $y)
				->lineTo($x + $offset, $y)
				->closePath()
		];
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

		// Label margin from axis.
		$margin = 5;
		$x = ($this->type == GRAPH_YAXIS_SIDE_RIGHT) ? $margin : $this->width - $margin;

		foreach ($this->labels as $pos => $label) {
			$y = $pos;

			if ($this->type == GRAPH_YAXIS_SIDE_RIGHT && $y == 0) {
				continue;
			}

			// Flip upside down.
			$y = $this->height - $y;

			$labels[] = new CSvgText($this->x + $x, $this->y + $y + 2.5, $label);
		}

		return $labels;
	}

	public function toString($destroy = true) {
		return (new CSvgGroup())
			->additem([
				$this->getAxis(),
				$this->getLabels()
			])
			->addClass($this->css_class[$this->type])
			->toString($destroy);
	}
}

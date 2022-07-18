<?php
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


class CSvgGraphAnnotation extends CSvgTag {

	const TYPE_SIMPLE = 0x1;
	const TYPE_RANGE = 0x2;

	const DASH_LINE_START = 0x4;
	const DASH_LINE_END = 0x8;
	const DASH_LINE_BOTH = 0x12;

	/**
	 * Annotation type. One of self::TYPE_* constants.
	 *
	 * @var int
	 */
	private $type;

	/**
	 * Problem information as JSON string. Is used by frontend for rendering tooltip with list of problems.
	 *
	 * @var string
	 */
	private $data_info;

	/**
	 * Color value.
	 *
	 * @var string
	 */
	private $color;

	public function __construct($type) {
		$this->color = '';
		$this->type = $type;
	}

	public function makeStyles() {
		return [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_DASHED => [
				'stroke-dasharray' => '2,2'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_PROBLEM_HANDLE => [
				'fill' => $this->color,
				'stroke' => $this->color
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_PROBLEM_BOX => [
				'fill' => $this->color,
				'opacity' => '0.1'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_PROBLEMS.' line' => [
				'stroke' => $this->color
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_PROBLEM_ARROW => [
				'stroke' => $this->color,
				'fill' => $this->color,
				'stroke-width' => 3
			]
		];
	}

	/**
	 * Set array of problem information
	 *
	 * @param string $info    Single problem information.
	 */
	public function setInformation($info) {
		$this->data_info = $info;

		return $this;
	}

	/**
	 * Set color.
	 *
	 * @param string $color   Color value.
	 */
	public function setColor($color) {
		$this->color = $color;

		return $this;
	}

	/**
	 * Return markup for problem of type simple as array.
	 *
	 * @return array
	 */
	private function drawTypeSimple() {
		$y = $this->y + $this->height;
		$arrow_width = 6;
		$offset = (int) $arrow_width / 2;

		return [
			(new CSvgLine($this->x, $this->y, $this->x, $this->y + $this->height))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_DASHED),
			(new CSvgPolygon([
				[$this->x, $y + 1],
				[$this->x - $offset, $y + 5],
				[$this->x + $offset, $y + 5]
			]))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_PROBLEM_ARROW)
				->setAttribute('x', $this->x - $offset)
				->setAttribute('width', $arrow_width)
				->setAttribute('data-info', $this->data_info)
		];
	}

	/**
	 * Return markup for problem of type range as array.
	 *
	 * @return array
	 */
	private function drawTypeRange() {
		$start_line = new CSvgLine($this->x, $this->y, $this->x, $this->y + $this->height);
		$end_line = new CSvgLine($this->x + $this->width, $this->y, $this->x + $this->width, $this->y + $this->height);

		if ($this->type & self::DASH_LINE_START) {
			$start_line->addClass(CSvgTag::ZBX_STYLE_GRAPH_DASHED);
		}

		if ($this->type & self::DASH_LINE_END) {
			$end_line->addClass(CSvgTag::ZBX_STYLE_GRAPH_DASHED);
		}

		return [
			$start_line,
			(new CSvgRect($this->x, $this->y, $this->width, $this->height))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_PROBLEM_BOX),
			$end_line,
			(new CSvgRect($this->x, $this->y + $this->height, $this->width, 4))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_PROBLEM_HANDLE)
				->setAttribute('data-info', $this->data_info)
		];
	}

	public function toString($destroy = true) {
		$problem = $this->type & self::TYPE_SIMPLE ? $this->drawTypeSimple() : $this->drawTypeRange();

		return implode('', $problem);
	}
}

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

	private $width = 0;
	private $height = 0;
	private $x = 0;
	private $y = 0;

	public function __construct($type) {
		$this->data_info = null;
		$this->type = $type;
	}

	/**
	 * Set axis container size.
	 *
	 * @param int $width    Axis container width.
	 * @param int $height   Axis container height.
	 */
	public function setSize($width, $height) {
		$this->width = (int) $width;
		$this->height = (int) $height;

		return $this;
	}

	/**
	 * Set axis container position.
	 *
	 * @param int $x        Horizontal position of container element.
	 * @param int $y        Veritical position of container element.
	 */
	public function setPosition($x, $y) {
		$this->x = (int) $x;
		$this->y = (int) $y;

		return $this;
	}

	public function getStyles() {
		return [
			'.dash' => [
				'stroke-dasharray' => '2, 2'
			]
		];
	}

	/**
	 * Set array of problem information
	 *
	 * @param string $info  Single problem information.
	 */
	public function setInformation($info) {
		$this->data_info = $info;

		return $this;
	}

	private function drawTypeSimple($clock, $info) {
		$x1 = $this->x;
		$x2 = $this->x + $this->width;
		$y1_1 = $this->y;
		$y1_2 = $this->y + $this->height;
		$y2_1 = $this->y;
		$y2_2 = $this->y + $this->height;

		$problem = [
			(new CSvgLine($x1, $y1, $x2, $y2, $this->color_annotation))->setDashed(),
			(new CSvgPolygon([
					[$x2, $y2 + 1],
					[$x2 - 3, $y2 + 5],
					[$x2 + 3, $y2 + 5],
				]))
				->setStrokeWidth(3)
				->setStrokeColor($this->color_annotation)
				->setFillColor($this->color_annotation)
				->setAttribute('data-info', $this->data_info)
		];

		return $problem;
	}

	/**
	 * Return markup for problem of type range as array.
	 *
	 * @return array
	 */
	private function drawTypeRange() {
		$x1 = $this->x;
		$x2 = $this->x + $this->width;
		$y1_1 = $this->y;
		$y1_2 = $this->y + $this->height;
		$y2_1 = $this->y;
		$y2_2 = $this->y + $this->height;
		$color_annotation = 'red';

		// Draw border lines. Make them dashed if beginning or ending of highligted zone is visible in graph.
		$start_line = new CSvgLine($this->x, $this->y, $this->x, $this->y + $this->height, $color_annotation);
		$end_line = new CSvgLine($this->x + $this->width, $this->y, $this->x + $this->width, $this->y + $this->height, $color_annotation);

		if ($this->type & self::DASH_LINE_START) {
			$start_line->addClass('dash');
		}

		if ($this->type & self::DASH_LINE_END) {
			$end_line->addClass('dash');
		}

		$problem = [
			$start_line,
			(new CSvgRect($x1, $y1_1, $x2 - $x1, $y1_2  - $y1_1))
				->setFillColor($color_annotation)// .problems rect
				->setFillOpacity('0.1'),
			$end_line,
			// (new CSvgLine(($this->x, $this->y + $this->height, $this->x + $this->width, $this->y + $this->height))
			// 	->setAttribute('data-info', $info)
			(new CSvgRect($this->x, $this->y + $this->height + 1, $this->width , 4))
				->setFillColor($color_annotation)//	.problems line.handle
				->setStrokeColor($color_annotation)
				->setAttribute('data-info', $this->data_info)
		];

		return $problem;
	}

	public function toString($destroy = true) {
		$problem = $this->type & self::TYPE_SIMPLE ? $this->drawTypeSimple() : $this->drawTypeRange();

		return implode('', $problem);
	}
}

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


class CSvgGraphGrid extends CSvgTag {

	protected $grid_points = [];
	protected $grid_type;
	protected $color;
	protected static $counter = 0;
	protected $instance_counter;

	public function __construct(array $grid_points, $grid_type) {
		parent::__construct('g', true);

		$this->grid_type = $grid_type;
		$this->grid_points = $grid_points;

		$this->instance_counter = ++self::$counter;
	}

	public function makeStyles() {
		$this->instance_counter = ++self::$counter;

		return [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_GRID.'-'.$this->instance_counter.' path' => [
				'stroke-dasharray' => '2,2',
				'stroke' => $this->color
			]
		];
	}

	/**
	 * Set color.
	 *
	 * @param string $color  Color value.
	 *
	 * @return CSvgGraphGrid
	 */
	public function setColor($color) {
		$this->color = $color;

		return $this;
	}

	protected function drawHorizontalGrid() {
		$path = new CSvgPath();

		foreach ($this->grid_points as $pos) {
			if (($this->y + $this->height - $pos) <= ($this->y + $this->height)) {
				$path
					->moveTo($this->x, $this->y + $this->height - $pos)
					->lineTo($this->x + $this->width, $this->y + $this->height - $pos);
			}
		}

		return $path;
	}

	protected function drawVerticalGrid() {
		$path = new CSvgPath();

		foreach ($this->grid_points as $pos) {
			if (($this->x + $pos) <= ($this->x + $this->width)) {
				$path
					->moveTo($this->x + $pos, $this->y)
					->lineTo($this->x + $pos, $this->y + $this->height);
			}
		}

		return $path;
	}

	public function toString($destroy = true) {
		$this
			->setAttribute('shape-rendering', 'crispEdges')
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_GRID.'-'.$this->instance_counter);

		parent::addItem($this->grid_type == GRAPH_HORIZONTAL_GRID
			? $this->drawHorizontalGrid()
			: $this->drawVerticalGrid()
		);

		return parent::toString($destroy);
	}
}

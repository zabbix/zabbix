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


class CSvgGraphArea extends CSvgGraphLine {

	protected $y_zero;

	public function __construct($path, $metric, $y_zero = 0) {
		parent::__construct($path, $metric);

		$this->y_zero = $y_zero;
		$this->add_label = false;
		$this->options = $metric['options'] + [
			'fill' => 5
		];
	}

	public function makeStyles() {
		$this
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_AREA)
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_AREA.'-'.$this->itemid.'-'.$this->options['order']);

		return [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_AREA.'-'.$this->itemid.'-'.$this->options['order'] => [
				'fill-opacity' => $this->options['fill'] * 0.1,
				'fill' => $this->options['color'],
				'stroke-opacity' => 0.1,
				'stroke' => $this->options['color'],
				'stroke-width' => 2
			]
		];
	}

	protected function draw() {
		$path = parent::draw();

		if ($this->path) {
			$first_point = reset($this->path);
			$last_point = end($this->path);
			$this
				->lineTo($last_point[0], $this->y_zero)
				->lineTo($first_point[0], $this->y_zero)
				->closePath();
		}

		return $path;
	}
}

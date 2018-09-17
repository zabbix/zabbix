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


class CSvgGraphLine extends CSvgPath {

	protected $path;

	protected $itemid;
	protected $item_name;
	protected $units;
	protected $host;
	protected $options;
	protected $line_values;
	protected $add_label;

	protected $position_x = 0;
	protected $position_y = 0;

	protected $width = 0;
	protected $height = 0;

	public function __construct($path, $metric) {
		parent::__construct();

		$this->add_label = true;
		$this->path = $path;

		$this->itemid = $metric['itemid'];
		$this->item_name = $metric['name'];
		$this->units = $metric['units'];
		$this->host = $metric['host'];
		$this->line_values = '';

		$this->options = $metric['options'] + [
			'transparency' => 5,
			'type' => SVG_GRAPH_TYPE_LINE,
			'width' => 1,
			'color' => '#b0af07',
			'order' => 1
		];
	}

	public function getStyles() {
		$this
			->addClass(CSvgTag::ZBX_STYLE_SVG_GRAPH_LINE)
			->addClass(CSvgTag::ZBX_STYLE_SVG_GRAPH_LINE.'-'.$this->itemid.'-'.$this->options['order']);

		$line_style = $this->options['type'] == SVG_GRAPH_TYPE_LINE ? ['stroke-linejoin' => 'round'] : [];

		return [
			'.'.CSvgTag::ZBX_STYLE_SVG_GRAPH_LINE => [
				'fill' => 'none'
			],
			'.'.CSvgTag::ZBX_STYLE_SVG_GRAPH_LINE.'-'.$this->itemid.'-'.$this->options['order'] => [
				'opacity' => $this->options['transparency'] * 0.1,
				'stroke' => $this->options['color'],
				'stroke-width' => $this->options['width']
			] + $line_style
		];
	}

	public function setPosition($x, $y) {
		$this->position_x = $x;
		$this->position_y = $y;

		return $this;
	}

	public function addLineValue($value) {
		$this->line_values .= ($this->line_values === '') ? $value : ','.$value;
	}

	public function setSize($width, $height) {
		$this->width = $width;
		$this->height = $height;

		return $this;
	}

	protected function draw() {
		$last_point = [0, 0];
		foreach ($this->path as $i => $point) {
			if ($i == 0) {
				$this->moveTo($point[0], $point[1]);
				if ($this->add_label) {
					$this->addLineValue($point[2]);
				}
			}
			else {
				if ($this->options['type'] == SVG_GRAPH_TYPE_STAIRCASE) {
					$this->lineTo($point[0], $last_point[1]);
				}
				$this->lineTo($point[0], $point[1]);
				if ($this->add_label) {
					$this->addLineValue($point[2]);
				}
			}
			$last_point = $point;
		}
	}

	public function toString($destroy = true) {
		$this->draw();
		$this->setAttribute('data-label', $this->line_values !== '' ? $this->line_values : null);

		return parent::toString($destroy);
	}
}

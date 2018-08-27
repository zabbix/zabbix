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


class CSvgGraphPoints extends CSvgGroup {

	protected $path;

	protected $itemid;
	protected $item_name;
	protected $units;
	protected $options;

	protected $position_x = 0;
	protected $position_y = 0;

	protected $width = 0;
	protected $height = 0;

	public function __construct($path, $metric) {
		parent::__construct();

		$this->path = $path;

		$this->itemid = $metric['itemid'];
		$this->item_name = $metric['name'];
		$this->units = $metric['units'];

		$this->options = $metric['options'] + [
			'color' => '#b0af07',
			'fill' => 5,
			'order' => 1,
			'pointsize' => 1,
			'transparency' => 5
		];
	}

	public function getStyles() {
		$this
			->addClass(CSvgTag::ZBX_STYLE_SVG_GRAPH_POINTS)
			->addClass(CSvgTag::ZBX_STYLE_SVG_GRAPH_POINTS.'-'.$this->itemid.'-'.$this->options['order']);

		return [
			'.'.CSvgTag::ZBX_STYLE_SVG_GRAPH_POINTS.'-'.$this->itemid.'-'.$this->options['order'] => [
				'fill-opacity' => $this->options['transparency'] * 0.1,
				'fill' => $this->options['color']
			]
		];
	}

	protected function draw() {
		foreach ($this->path as $point) {
			$this->addItem((new CSvgCircle($point[0], $point[1], $this->options['pointsize']))
				->setAttribute('label', $point[2])
			);
		}
	}

	public function toString($destroy = true) {
		$this->setAttribute('data-set', 'points')
			->setAttribute('data-metric', $this->item_name)
			->setAttribute('data-color', $this->options['color'])
			->addItem(
				(new CSvgCircle(-10, -10, $this->options['pointsize'] + 2))->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
			)
			->draw();

		return parent::toString($destroy);
	}
}

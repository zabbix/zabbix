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


class CSvgGraphPoints extends CSvgGroup {

	protected $path;
	protected $itemid;
	protected $item_name;
	protected $options;

	public function __construct($path, $metric) {
		parent::__construct();

		$this->path = $path ? : [];
		$this->itemid = $metric['itemid'];
		$this->item_name = $metric['name'];
		$this->options = $metric['options'] + [
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'pointsize' => CSvgGraph::SVG_GRAPH_DEFAULT_POINTSIZE,
			'transparency' => CSvgGraph::SVG_GRAPH_DEFAULT_TRANSPARENCY,
			'order' => 1
		];
	}

	public function makeStyles() {
		$this
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_POINTS)
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_POINTS.'-'.$this->itemid.'-'.$this->options['order']);

		return [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_POINTS.'-'.$this->itemid.'-'.$this->options['order'] => [
				'fill-opacity' => $this->options['transparency'] * 0.1,
				'fill' => $this->options['color']
			]
		];
	}

	protected function draw() {
		foreach ($this->path as $point) {
			$this->addItem(
				(new CSvgCircle($point[0], $point[1], $this->options['pointsize']))->setAttribute('label', $point[2])
			);
		}
	}

	public function toString($destroy = true) {
		$this->setAttribute('data-set', 'points')
			->setAttribute('data-metric', CHtml::encode($this->item_name))
			->setAttribute('data-color', $this->options['color'])
			->addItem(
				(new CSvgCircle(-10, -10, $this->options['pointsize'] + 4))
					->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
			)
			->draw();

		return parent::toString($destroy);
	}
}

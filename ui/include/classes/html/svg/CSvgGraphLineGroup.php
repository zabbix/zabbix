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


/*
 * Class to draw graph line. Single data points will be drawn as points instead of lines.
 */
class CSvgGraphLineGroup extends CSvgGroup {

	protected $paths;
	protected $metric;
	protected $options;

	public function __construct($paths, $metric) {
		parent::__construct();

		$this->paths = $paths;
		$this->metric = $metric;

		$this->options = $metric['options'] + [
			'transparency' => CSvgGraph::SVG_GRAPH_DEFAULT_TRANSPARENCY,
			'width' => CSvgGraph::SVG_GRAPH_DEFAULT_LINE_WIDTH,
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'type' => SVG_GRAPH_TYPE_LINE,
			'order' => 1
		];

		// Minimal point size is 3 to make single data points visible even for thin lines.
		$this->options['pointsize'] = max($this->options['width'], 3);
	}

	public function makeStyles() {
		$this
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_LINE)
			->addClass(CSvgTag::ZBX_STYLE_GRAPH_LINE.'-'.$this->metric['itemid'].'-'.$this->options['order']);

		$line_style = ($this->options['type'] == SVG_GRAPH_TYPE_LINE) ? ['stroke-linejoin' => 'round'] : [];

		return [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_LINE => [
				'fill' => 'none'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_LINE.'-'.$this->metric['itemid'].'-'.$this->options['order'] => [
				'stroke-opacity' => $this->options['transparency'] * 0.1,
				'stroke' => $this->options['color'],
				'stroke-width' => $this->options['width']
			] + $line_style,
			'.'.CSvgTag::ZBX_STYLE_GRAPH_LINE.'-'.$this->metric['itemid'].'-'.$this->options['order'].' circle' => [
				'fill-opacity' => $this->options['transparency'] * 0.1,
				'fill' => $this->options['color'],
				'stroke-width' => 0
			]
		];
	}

	protected function draw() {
		foreach ($this->paths as $path) {
			// Draw single data point paths as circles instead of lines.
			$this->addItem((count($path) > 1)
				? new CSvgGraphLine($path, $this->metric)
				: (new CSvgCircle($path[0][0], $path[0][1], $this->options['pointsize']))
					->setAttribute('label', $path[0][2])
			);
		}
	}

	public function toString($destroy = true) {
		$this->setAttribute('data-set', $this->options['type'] == SVG_GRAPH_TYPE_LINE ? 'line' : 'staircase')
			->setAttribute('data-metric', CHtml::encode($this->metric['name']))
			->setAttribute('data-color', $this->options['color'])
			->addItem((new CSvgCircle(-10, -10, $this->options['width'] + 4))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE))
			->draw();

		return parent::toString($destroy);
	}
}

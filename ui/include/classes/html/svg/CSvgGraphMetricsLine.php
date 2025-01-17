<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/*
 * Class to draw graph line. Single data points will be drawn as points instead of lines.
 */
class CSvgGraphMetricsLine extends CSvgGroup {

	private $metric_paths;
	private $metric;

	private $options;

	public function __construct(array $metric_paths, array $metric) {
		parent::__construct();

		$this->metric_paths = $metric_paths;
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

	public function makeStyles(): array {
		$item_css_class = CSvgGraphLine::ZBX_STYLE_CLASS.'-'.$this->metric['itemid'].'-'.$this->options['order'];

		$this
			->addClass(CSvgGraphLine::ZBX_STYLE_CLASS)
			->addClass($item_css_class);

		return [
			'.'.CSvgGraphLine::ZBX_STYLE_CLASS => [
				'fill' => 'none'
			],
			'.'.$item_css_class => [
				'stroke-opacity' => $this->options['transparency'] * 0.1,
				'stroke' => $this->options['color'],
				'stroke-width' => $this->options['width'] * ($this->options['approximation'] == APPROXIMATION_ALL ? 2 : 1)
			] + ($this->options['type'] == SVG_GRAPH_TYPE_LINE ? ['stroke-linejoin' => 'round'] : []),
			'.'.$item_css_class.' circle' => [
				'fill-opacity' => $this->options['transparency'] * 0.1,
				'fill' => $this->options['color'],
				'stroke-width' => 0
			],
			'.'.$item_css_class.' .'.CSvgGraphArea::ZBX_STYLE_CLASS => [
				'fill-opacity' => $this->options['fill'] * 0.1,
				'fill' => $this->options['color'],
				'stroke-opacity' => 0
			],
			'.'.$item_css_class.' .'.CSvgGraphLine::ZBX_STYLE_LINE_AUXILIARY => [
				'stroke-width' => $this->options['width'],
				'opacity' => $this->options['transparency'] * 0.1
			]
		];
	}

	protected function draw(): void {
		$this->addItem(
			(new CSvgCircle(-10, -10, $this->options['width'] + 4))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
		);

		foreach ($this->metric_paths as $metric_path) {
			// Draw as a line if more than one data point path.
			if (count($metric_path) > 1) {
				$this->addItem(new CSvgGraphLine($metric_path['line'], $this->metric));

				if (array_key_exists('min', $metric_path)) {
					$this->addItem(
						(new CSvgGraphLine($metric_path['min'], $this->metric, false))
							->addClass(CSvgGraphLine::ZBX_STYLE_LINE_AUXILIARY)
					);
					$this->addItem(
						(new CSvgGraphLine($metric_path['max'], $this->metric, false))
							->addClass(CSvgGraphLine::ZBX_STYLE_LINE_AUXILIARY)
					);
				}
			}
			// Draw as a circle if one data point path.
			else {
				$this->addItem(
					(new CSvgCircle($metric_path['line'][0][0], $metric_path['line'][0][1], $this->options['pointsize']))
						->setAttribute('label', $metric_path['line'][0][2])
				);
			}

			if (array_key_exists('fill', $metric_path)) {
				$this->addItem(new CSvgGraphArea($metric_path['fill'], $this->metric));
			}
		}
	}

	public function toString($destroy = true): string {
		$this
			->setAttribute('data-set', $this->options['type'] == SVG_GRAPH_TYPE_LINE ? 'line' : 'staircase')
			->setAttribute('data-metric', $this->metric['name'])
			->setAttribute('data-color', $this->options['color'])
			->draw();

		return parent::toString($destroy);
	}
}

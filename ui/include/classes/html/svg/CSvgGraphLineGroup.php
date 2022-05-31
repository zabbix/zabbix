<?php declare(strict_types = 0);
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

	private $paths;
	private $metric;
	private $y_zero;

	private $options;

	public function __construct(array $paths, array $metric, $y_zero) {
		parent::__construct();

		$this->paths = $paths;
		$this->metric = $metric;
		$this->y_zero = $y_zero;

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
				'stroke-width' => $this->options['width'] * $this->options['approximation'] == APPROXIMATION_ALL ? 2 : 1
			] + ($this->options['type'] == SVG_GRAPH_TYPE_LINE ? ['stroke-linejoin' => 'round'] : []),
			'.'.$item_css_class.' circle' => [
				'fill-opacity' => $this->options['transparency'] * 0.1,
				'fill' => $this->options['color'],
				'stroke-width' => 0
			],
			'.'.$item_css_class.' .'.CSvgGraphArea::ZBX_STYLE_CLASS => [
				'fill-opacity' => $this->options['fill'] * 0.1,
				'fill' => $this->options['color'],
				'stroke-opacity' => 0.05
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

		switch ($this->options['approximation']) {
			case APPROXIMATION_MIN:
				$approximation = 'min';
				break;
			case APPROXIMATION_MAX:
				$approximation = 'max';
				break;
			default:
				$approximation = 'avg';
		}

		foreach ($this->paths as $path) {
			// Draw single data point paths as circles instead of lines.
			if (count($path) > 1) {
				$this->addItem(new CSvgGraphLine(array_column($path, $approximation), $this->metric));

				if ($this->options['approximation'] == APPROXIMATION_ALL) {
					$this->addItem(new CSvgGraphLine(array_column($path, 'min'), $this->metric, true));
					$this->addItem(new CSvgGraphLine(array_column($path, 'max'), $this->metric, true));
				}

				if ($this->options['approximation'] == APPROXIMATION_ALL) {
					$this->addItem(
						new CSvgGraphArea(
							array_merge(
								array_column($path, 'max'),
								array_reverse(array_column($path, 'min'))
							),
							$this->metric,
							null
						)
					);
				}
				else {
					$this->addItem(
						new CSvgGraphArea(array_column($path, $approximation), $this->metric, $this->y_zero)
					);
				}
			}
			else {
				$this->addItem(
					(new CSvgCircle($path[0][$approximation][0], $path[0][$approximation][1],
						$this->options['pointsize'])
					)->setAttribute('label', $path[0][$approximation][2])
				);
			}
		}
	}

	public function toString($destroy = true): string {
		$this
			->setAttribute('data-set', $this->options['type'] == SVG_GRAPH_TYPE_LINE ? 'line' : 'staircase')
			->setAttribute('data-metric', CHtml::encode($this->metric['name']))
			->setAttribute('data-color', $this->options['color'])
			->draw();

		return parent::toString($destroy);
	}
}

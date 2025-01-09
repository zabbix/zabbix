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


class CSvgGraphMetricsBar extends CSvgGroup {

	private const ZBX_STYLE_CLASS = 'svg-graph-bar';

	private $path;
	private $itemid;
	private $item_name;
	private $options;

	public function __construct(array $path, array $metric) {
		parent::__construct();

		$this->path = $path;

		$this->itemid = $metric['itemid'];
		$this->item_name = $metric['name'];

		$this->options = $metric['options'] + [
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'pointsize' => CSvgGraph::SVG_GRAPH_DEFAULT_POINTSIZE,
			'transparency' => CSvgGraph::SVG_GRAPH_DEFAULT_TRANSPARENCY,
			'width' => CSvgGraph::SVG_GRAPH_DEFAULT_LINE_WIDTH,
			'order' => 1
		];
	}

	public function makeStyles(): array {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addClass(self::ZBX_STYLE_CLASS.'-'.$this->itemid.'-'.$this->options['order']);

		return [
			'.'.self::ZBX_STYLE_CLASS.'-'.$this->itemid.'-'.$this->options['order'] => [
				'fill-opacity' => $this->options['transparency'] * 0.1,
				'fill' => $this->options['color']
			]
		];
	}

	private function draw(): void {
		$this->addItem(
			(new CSvgCircle(-10, -10, $this->options['width'] + 4))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
		);

		foreach ($this->path as [$x1, $x2, $y1, $y2, $label, $tooltip_x]) {
			$this->addItem(
				(new CSvgPolygon(
					[[$x1, $y2], [$x2, $y2], [$x2, $y1], [$x1, $y1]]
				))
					->setAttribute('label', $label)
					->setAttribute('data-px', $tooltip_x)
			);
		}
	}

	public function toString($destroy = true): string {
		$this->setAttribute('data-set', 'bar')
			->setAttribute('data-metric', $this->item_name)
			->setAttribute('data-color', $this->options['color'])
			->draw();

		return parent::toString($destroy);
	}
}

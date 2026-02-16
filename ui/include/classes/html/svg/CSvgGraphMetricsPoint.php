<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CSvgGraphMetricsPoint extends CSvgGroup {

	/**
	 * Vertical position of points, which must be hidden, yet still rendered.
	 */
	public const Y_OUT_OF_RANGE = -10;

	private const ZBX_STYLE_CLASS = 'svg-graph-points';

	private array $path;
	private array $metric;

	protected $options;

	public function __construct(array $path, array $metric) {
		parent::__construct();

		$this->path = $path ? : [];
		$this->metric = $metric;

		$this->options = $metric['options'] + [
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'pointsize' => CSvgGraph::SVG_GRAPH_DEFAULT_POINTSIZE,
			'transparency' => CSvgGraph::SVG_GRAPH_DEFAULT_TRANSPARENCY,
			'order' => 1
		];
	}

	public function makeStyles(): array {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addClass(self::ZBX_STYLE_CLASS.'-'.$this->metric['itemid'].'-'.$this->options['order']);

		return [
			'.'.self::ZBX_STYLE_CLASS.'-'.$this->metric['itemid'].'-'.$this->options['order'] => [
				'fill-opacity' => $this->options['transparency'] * 0.1,
				'fill' => $this->options['color']
			]
		];
	}

	protected function draw(): void {
		$this->addItem(
			(new CSvgCircle(-10, self::Y_OUT_OF_RANGE, $this->options['pointsize'] + 4))
				->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
		);

		foreach ($this->path as $point) {
			$this->addItem(
				(new CSvgCircle($point[0], $point[1], $this->options['pointsize']))->setAttribute('label', $point[2])
			);
		}
	}

	public function toString($destroy = true): string {
		$this
			->setAttribute('data-set', 'points')
			->setAttribute('data-metric', $this->metric['name'])
			->setAttribute('data-itemid', $this->metric['itemid'])
			->setAttribute('data-itemids', $this->metric['itemids'])
			->setAttribute('data-ds', $this->metric['data_set'])
			->setAttribute('data-color', $this->options['color'])
			->draw();

		return parent::toString($destroy);
	}
}

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


namespace Widgets\ScatterPlot\Includes;

use CSvgCircle;
use CSvgGraph;
use CSvgGroup;
use CSvgRect;
use CSvgTag;

class CScatterPlotMetricPoint extends CSvgGroup {

	/**
	 * Vertical position of points, which must be hidden, yet still rendered.
	 */
	public const Y_OUT_OF_RANGE = -10;
	public const X_OUT_OF_RANGE = -10;

	public const MARKER_TYPE_ELLIPSIS = 0;
	public const MARKER_TYPE_SQUARE = 1;
	public const MARKER_TYPE_TRIANGLE = 2;
	public const MARKER_TYPE_DIAMOND = 3;
	public const MARKER_TYPE_STAR = 4;
	public const MARKER_TYPE_CROSS = 5;

	private const ZBX_STYLE_CLASS = 'svg-graph-points';

	private ?array $point;
	private string $item_x_name;
	private string $item_y_name;

	protected array $options;

	public function __construct(array $point, array $metric) {
		parent::__construct();

		$this->point = $point ? : null;
		$this->item_x_name = $metric['x_axis_name'];
		$this->item_y_name = $metric['y_axis_name'];

		$this->options = $metric['options'] + [
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'order' => 1
		];
	}

	public function makeStyles(): array {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addClass(self::ZBX_STYLE_CLASS.'-'.$this->options['order']);

		return [
			'.'.self::ZBX_STYLE_CLASS.'-'.$this->options['order'] => [
				'fill-opacity' => 1,
				'fill' => $this->options['color']
			]
		];
	}

	protected function draw(): void {
		if ($this->point === null) {
			return;
		}

		$point = null;
		$size = $this->options['marker_size'];

		switch ($this->options['marker']) {
			case self::MARKER_TYPE_ELLIPSIS:
				$this->addItem(
					(new CSvgCircle(-10, self::Y_OUT_OF_RANGE, $size + 4))
						->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
				);

				$point = new CSvgCircle($this->point[0], $this->point[1], $size);
				break;

			case self::MARKER_TYPE_SQUARE:
				$this->addItem(
					(new CSvgRect(self::X_OUT_OF_RANGE, self::Y_OUT_OF_RANGE, $size + 4, $size + 4))
						->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
				);

				if ($this->point) {
					$x = $this->point[0] - ($size / 2);
					$y = $this->point[1] - ($size / 2);
					$point = new CSvgRect($x, $y, $size, $size);
				}
				break;

			case self::MARKER_TYPE_DIAMOND:
				break;

			case self::MARKER_TYPE_STAR:
				break;

			case self::DATASET_MARKER_TYPE_DIAMOND:
				break;
			case self::DATASET_MARKER_TYPE_CROSS:
				break;
		}

		if ($point !== null) {
			$this->addItem(
				$point
					->addClass('metric-point')
					->setAttribute('value_x', $this->point[2])
					->setAttribute('value_y', $this->point[3])
					->setAttribute('color', $this->point[4])
					->setAttribute('time_from', $this->point[5])
					->setAttribute('time_to', $this->point[6])
			);
		}
	}

	public function toString($destroy = true): string {
		$this
			->setAttribute('data-set', 'points')
			->setAttribute('data-metric-x', $this->item_x_name)
			->setAttribute('data-metric-y', $this->item_y_name)
			->draw();

		return parent::toString($destroy);
	}
}

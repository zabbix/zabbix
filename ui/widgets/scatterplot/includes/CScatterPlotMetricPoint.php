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

use CSvgCircle,
	CSvgCross,
	CSvgDiamond,
	CSvgGraph,
	CSvgGroup,
	CSvgRect,
	CSvgStar,
	CSvgTag,
	CSvgTriangle,
	InvalidArgumentException;

class CScatterPlotMetricPoint extends CSvgGroup {

	/**
	 * Horizontal position of points, which must be hidden, yet still rendered.
	 */
	public const X_OUT_OF_RANGE = -10;

	/**
	 * Vertical position of points, which must be hidden, yet still rendered.
	 */
	public const Y_OUT_OF_RANGE = -10;

	public const MARKER_TYPE_ELLIPSIS = 0;
	public const MARKER_TYPE_SQUARE = 1;
	public const MARKER_TYPE_TRIANGLE = 2;
	public const MARKER_TYPE_DIAMOND = 3;
	public const MARKER_TYPE_STAR = 4;
	public const MARKER_TYPE_CROSS = 5;

	public const MARKER_ICONS = [
		self::MARKER_TYPE_ELLIPSIS => ZBX_ICON_ELLIPSE,
		self::MARKER_TYPE_SQUARE => ZBX_ICON_SQUARE,
		self::MARKER_TYPE_TRIANGLE => ZBX_ICON_TRIANGLE,
		self::MARKER_TYPE_DIAMOND => ZBX_ICON_DIAMOND,
		self::MARKER_TYPE_STAR => ZBX_ICON_STAR_FILLED,
		self::MARKER_TYPE_CROSS => ZBX_ICON_CROSS
	];

	private const ZBX_STYLE_CLASS = 'svg-graph-points';

	private ?array $point;
	private string $item_x_name;
	private string $item_y_name;

	protected array $options;

	public function __construct(array $point, array $metric) {
		parent::__construct();

		$this->point = $point;
		$this->item_x_name = $metric['x_axis_name'];
		$this->item_y_name = $metric['y_axis_name'];

		$this->options = $metric['options'] + [
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'order' => 1,
			'key' => $metric['key']
		];
	}

	public function makeStyles(): array {
		$index_class = self::ZBX_STYLE_CLASS.'-'.$this->options['order'].'-'.$this->options['key'];

		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addClass($index_class);

		$color = $this->point ? $this->point[4] : $this->options['color'];

		return [
			'.'.$index_class => [
				'fill-opacity' => 1,
				'fill' => $color,
				'stroke' => $color
			]
		];
	}

	protected function draw(): void {
		$highlight_point_group = (new CSvgGroup())
			->setAttribute('transform', 'translate('.self::X_OUT_OF_RANGE.', '.self::Y_OUT_OF_RANGE.')')
			->addClass('js-svg-highlight-group');

		$point_group = (new CSvgGroup())->setAttribute('transform',
			'translate('.$this->point[0].', '.$this->point[1].')'
		);

		[$highlight, $point] = self::createMarker($this->options['marker'],  $this->options['marker_size']);

		$this
			->addItem(
				$highlight_point_group->addItem(
					$highlight->addClass(CSvgTag::ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE)
				)
			)
			->addItem(
				$point_group
					->addClass('metric-point')
					->setAttribute('value_x', $this->point[2])
					->setAttribute('value_y', $this->point[3])
					->setAttribute('color', $this->point[4])
					->setAttribute('time_from', $this->point[5])
					->setAttribute('time_to', $this->point[6])
					->setAttribute('marker_class', self::MARKER_ICONS[$this->options['marker']])
					->addItem($point)
			);
	}

	public static function createMarker(int $marker_type, int $size, int $cx = 0, int $cy = 0): array {
		switch ($marker_type) {
			case self::MARKER_TYPE_ELLIPSIS:
				return [
					new CSvgCircle($cx, $cy, $size + 4),
					new CSvgCircle($cx, $cy, $size)
				];

			case self::MARKER_TYPE_SQUARE:
				$empty_coordinates = $cx - ($size + 4) / 2;
				$zero_coordinates = $cy - ($size / 2);

				return [
					new CSvgRect($empty_coordinates, $empty_coordinates, $size + 4, $size + 4),
					new CSvgRect($zero_coordinates, $zero_coordinates, $size, $size)
				];

			case self::MARKER_TYPE_TRIANGLE:
				return [
					new CSvgTriangle($cx, $cy, $size + 4, $size + 4),
					new CSvgTriangle($cx, $cy, $size, $size)
				];

			case self::MARKER_TYPE_DIAMOND:
				return [
					new CSvgDiamond($cx, $cy, $size + 4),
					new CSvgDiamond($cx, $cy, $size)
				];

			case self::MARKER_TYPE_STAR:
				return [
					new CSvgStar($cx, $cy, $size + 4),
					new CSvgStar($cx, $cy, $size)
				];

			case self::MARKER_TYPE_CROSS:
				return [
					new CSvgCross($cx, $cy, $size + 4),
					new CSvgCross($cx, $cy, $size)
				];
		}

		throw new InvalidArgumentException();
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

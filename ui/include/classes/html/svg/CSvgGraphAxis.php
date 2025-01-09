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


/**
 * SVG graphs axis class.
 */
class CSvgGraphAxis extends CSvgGroup {

	private const ZBX_STYLE_CLASS = 'svg-graph-axis';

	private const ZBX_STYLE_GRAPH_AXIS_LEFT = 'svg-graph-axis-left';
	private const ZBX_STYLE_GRAPH_AXIS_RIGHT = 'svg-graph-axis-right';
	private const ZBX_STYLE_GRAPH_AXIS_BOTTOM = 'svg-graph-axis-bottom';

	/**
	 * Axis triangle icon size.
	 *
	 * @var int
	 */
	private const ZBX_ARROW_SIZE = 5;

	private const ZBX_ARROW_OFFSET = 5;

	/**
	 * Array of labels. Key is coordinate, value is text label.
	 *
	 * @var array
	 */
	private $labels;

	/**
	 * Axis type. One of CSvgGraphAxis::AXIS_* constants.
	 *
	 * @var int
	 */
	private $type;

	/**
	 * Color for labels.
	 *
	 * @var string
	 */
	private $text_color;

	/**
	 * Color for axis.
	 *
	 * @var string
	 */
	private $line_color;

	public function __construct(array $labels, int $type) {
		parent::__construct();

		$this->labels = $labels;
		$this->type = $type;
	}

	public function setTextColor(string $color): self {
		$this->text_color = $color;

		return $this;
	}

	public function setLineColor(string $color): self {
		$this->line_color = $color;

		return $this;
	}

	/**
	 * Return CSS style definitions for axis as array.
	 *
	 * @return array
	 */
	public function makeStyles(): array {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addClass([
				GRAPH_YAXIS_SIDE_RIGHT => self::ZBX_STYLE_GRAPH_AXIS_RIGHT,
				GRAPH_YAXIS_SIDE_LEFT => self::ZBX_STYLE_GRAPH_AXIS_LEFT,
				GRAPH_YAXIS_SIDE_BOTTOM => self::ZBX_STYLE_GRAPH_AXIS_BOTTOM
			][$this->type]);

		return [
			'.'.self::ZBX_STYLE_CLASS.' path' => [
				'stroke' => $this->line_color,
				'fill' => 'transparent'
			],
			'.'.self::ZBX_STYLE_CLASS.' text' => [
				'fill' => $this->text_color,
				'font-size' => '11px',
				'alignment-baseline' => 'middle',
				'dominant-baseline' => 'middle'
			],
			'.'.self::ZBX_STYLE_GRAPH_AXIS_RIGHT.' text' => [
				'text-anchor' => 'start'
			],
			'.'.self::ZBX_STYLE_GRAPH_AXIS_LEFT.' text' => [
				'text-anchor' => 'end'
			],
			'.'.self::ZBX_STYLE_GRAPH_AXIS_BOTTOM.' text' => [
				'text-anchor' => 'middle'
			]
		];
	}

	private function getAxis(): array {
		$offset = ceil(self::ZBX_ARROW_SIZE / 2);

		if ($this->type == GRAPH_YAXIS_SIDE_BOTTOM) {
			$x = $this->x + $this->width + self::ZBX_ARROW_OFFSET;
			$y = $this->y;

			return [
				// Draw axis line.
				(new CSvgPath())
					->setAttribute('shape-rendering', 'crispEdges')
					->moveTo($this->x, $y)
					->lineTo($x, $y),
				// Draw arrow.
				(new CSvgPath())
					->moveTo($x + self::ZBX_ARROW_SIZE, $y)
					->lineTo($x, $y - $offset)
					->lineTo($x, $y + $offset)
					->closePath()
			];
		}

		$x = ($this->type == GRAPH_YAXIS_SIDE_RIGHT) ? $this->x : $this->x + $this->width;
		$y = $this->y - self::ZBX_ARROW_OFFSET;

		return [
			// Draw axis line.
			(new CSvgPath())
				->setAttribute('shape-rendering', 'crispEdges')
				->moveTo($x, $y)
				->lineTo($x, $this->height + $y + self::ZBX_ARROW_OFFSET),
			// Draw arrow.
			(new CSvgPath())
				->moveTo($x, $y - self::ZBX_ARROW_SIZE)
				->lineTo($x - $offset, $y)
				->lineTo($x + $offset, $y)
				->closePath()
		];
	}

	/**
	 * Return array of initialized CSvgText objects for axis labels.
	 *
	 * @return array
	 */
	private function getLabels(): array {
		$x = 0;
		$y = 0;
		$labels = [];

		if ($this->type == GRAPH_YAXIS_SIDE_BOTTOM) {
			$axis = 'x';
			$y = $this->height - CSvgGraph::SVG_GRAPH_X_AXIS_LABEL_MARGIN;
		}
		else {
			$axis = 'y';
			$x = ($this->type == GRAPH_YAXIS_SIDE_RIGHT)
				? CSvgGraph::SVG_GRAPH_Y_AXIS_LABEL_MARGIN_INNER
				: $this->width - CSvgGraph::SVG_GRAPH_Y_AXIS_LABEL_MARGIN_INNER;
		}

		foreach ($this->labels as $pos => $label) {
			$$axis = $pos;

			if ($this->type == GRAPH_YAXIS_SIDE_LEFT || $this->type == GRAPH_YAXIS_SIDE_RIGHT) {
				// Flip upside down.
				$y = $this->height - $y;
			}

			if ($this->type == GRAPH_YAXIS_SIDE_BOTTOM) {
				if (count($labels) == 0) {
					$text_tag_x = max($this->x + $x, strlen($label) * 4);
				}
				elseif (end($this->labels) === $label) {
					$text_tag_x = (strlen($label) * 4 > $this->width - $x) ? $this->x + $x - 10 : $this->x + $x;
				}
				else {
					$text_tag_x = $this->x + $x;
				}
			}
			else {
				$text_tag_x = $this->x + $x;
			}

			$labels[] = new CSvgText($label, $text_tag_x, $this->y + $y);
		}

		return $labels;
	}

	public function toString($destroy = true): string {
		$this
			->additem($this->getAxis())
			->additem($this->getLabels());

		return parent::toString($destroy);
	}
}

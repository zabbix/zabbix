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


class CSvgGraphLine extends CSvgPath {

	public const ZBX_STYLE_CLASS = 'svg-graph-line';
	public const ZBX_STYLE_LINE_AUXILIARY = 'svg-graph-line-auxiliary';

	protected $path;

	protected $itemid;
	protected $item_name;
	protected $units;
	protected $host;

	protected $add_labels;

	protected $options;

	public function __construct(array $path, array $metric, bool $add_labels = true) {
		parent::__construct();

		$this->add_labels = $add_labels;
		$this->path = $path;

		$this->itemid = $metric['itemid'];
		$this->item_name = $metric['name'];
		$this->units = $metric['units'];
		$this->host = $metric['host'];

		$this->options = $metric['options'] + [
			'transparency' => CSvgGraph::SVG_GRAPH_DEFAULT_TRANSPARENCY,
			'width' => CSvgGraph::SVG_GRAPH_DEFAULT_LINE_WIDTH,
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'type' => SVG_GRAPH_TYPE_LINE,
			'order' => 1
		];
	}

	protected function draw(): void {
		if (count($this->path) > 1) {
			$last_point = [0, 0];

			foreach ($this->path as $index => $point) {
				if ($index == 0) {
					$this->moveTo($point[0], $point[1]);
				}
				else {
					if ($this->options['type'] == SVG_GRAPH_TYPE_STAIRCASE) {
						$this->lineTo($point[0], $last_point[1]);
					}
					$this->lineTo($point[0], $point[1]);
				}
				$last_point = $point;
			}
		}
	}

	public function toString($destroy = true): string {
		if ($this->path) {
			if ($this->add_labels) {
				// Create a label for each point, including stacked graph blank "points".
				$line_values = implode(',', array_map(static function ($point): string {
					return $point[2];
				}, $this->path));

				$this->setAttribute('label', $line_values);
			}

			$this->draw();
		}

		return parent::toString($destroy);
	}
}

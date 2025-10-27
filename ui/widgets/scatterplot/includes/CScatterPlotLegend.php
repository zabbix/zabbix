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

use CDiv,
	CSvg;

class CScatterPlotLegend extends CDiv {

	// Legend single line height is 18px. Value should be synchronized with $svg-legend-line-height in scss.
	private const LINE_HEIGHT = 18;

	private const ZBX_STYLE_CLASS = 'svg-scatter-plot-legend';
	private const ZBX_STYLE_SCATTER_PLOT_LEGEND_ITEM = 'svg-scatter-plot-legend-item';

	private array $legend_items;

	private int $columns_count = 0;
	private int $lines_count = 0;
	private int $lines_mode = 0;

	/**
	 * @param array  $legend_items
	 * @param string $legend_items[]['name']
	 * @param string $legend_items[]['color']
	 * @param string $legend_items[]['marker']
	 */
	public function __construct(array $legend_items) {
		parent::__construct();

		$this->legend_items = $legend_items;
	}

	public function setColumnsCount(int $columns_count): self {
		$this->columns_count = $columns_count;

		return $this;
	}

	private function getLinesCount(): int {
		if ($this->lines_mode == WidgetForm::LEGEND_LINES_MODE_FIXED) {
			return $this->lines_count;
		}

		return max(WidgetForm::LEGEND_LINES_MIN,
			min($this->lines_count,	ceil(count($this->legend_items) / $this->columns_count))
		);
	}

	public function setLinesCount(int $lines_count): self {
		$this->lines_count = $lines_count;

		return $this;
	}

	public function setLinesMode(int $lines_mode): self {
		$this->lines_mode = $lines_mode;

		return $this;
	}

	public function getHeight(): int {
		return $this->getLinesCount() * self::LINE_HEIGHT;
	}

	private function draw(): void {
		foreach ($this->legend_items as $item) {
			$icon_class = CScatterPlotMetricPoint::createMarker($item['marker'], 10, 7, 7);

			$this->addItem(
				(new CDiv([
					(new CSvg())
						->setSize(16, 16)
						->addItem($icon_class[1])
						->addStyle('fill: '.$item['color'].';'.' stroke: '.$item['color'].'; vertical-align: middle;'),
					$item['name']
				]))->addClass(self::ZBX_STYLE_SCATTER_PLOT_LEGEND_ITEM)
			);
		}
	}

	public function toString($destroy = true): string {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addStyle('--lines: '.$this->getLinesCount().';')
			->addStyle('--columns: '.min($this->columns_count, count($this->legend_items)).';');

		$this->draw();

		return parent::toString($destroy);
	}
}

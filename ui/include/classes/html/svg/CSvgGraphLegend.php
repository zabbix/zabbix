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


class CSvgGraphLegend extends CDiv {

	// Legend single line height is 18px. Value should be synchronized with $svg-legend-line-height in scss.
	public const LINE_HEIGHT = 18;

	private const ZBX_STYLE_CLASS = 'svg-graph-legend';

	private const ZBX_STYLE_GRAPH_LEGEND_STATISTIC = 'svg-graph-legend-statistic';
	private const ZBX_STYLE_GRAPH_LEGEND_HEADER = 'svg-graph-legend-header';
	private const ZBX_STYLE_GRAPH_LEGEND_ITEM = 'svg-graph-legend-item';
	private const ZBX_STYLE_GRAPH_LEGEND_NO_DATA = 'svg-graph-legend-no-data';

	private $legend_items;

	private $columns_count = 0;
	private $lines_count = 0;
	private $show_statistic = false;

	/**
	 * @param array  $legend_items
	 * @param string $legend_items[]['name']
	 * @param string $legend_items[]['color']
	 * @param string $legend_items[]['units']
	 * @param string $legend_items[]['min']
	 * @param string $legend_items[]['avg']
	 * @param string $legend_items[]['max']
	 */
	public function __construct(array $legend_items) {
		parent::__construct();

		$this->legend_items = $legend_items;
	}

	public function setColumnsCount(int $columns_count): self {
		$this->columns_count = $columns_count;

		return $this;
	}

	public function getLinesCount(): int {
		return $this->lines_count + ($this->show_statistic ? 1 : 0);
	}

	public function setLinesCount(int $lines_count): self {
		$this->lines_count = $lines_count;

		return $this;
	}

	public function showStatistic(int $show_statistic): self {
		$this->show_statistic = ($show_statistic == SVG_GRAPH_LEGEND_STATISTIC_ON);

		return $this;
	}

	private function draw(): void {
		if ($this->show_statistic) {
			$this->addItem([
				(new CDiv(_('min')))->addClass(self::ZBX_STYLE_GRAPH_LEGEND_HEADER),
				(new CDiv(_('avg')))->addClass(self::ZBX_STYLE_GRAPH_LEGEND_HEADER),
				(new CDiv(_('max')))->addClass(self::ZBX_STYLE_GRAPH_LEGEND_HEADER)
			]);
		}

		foreach ($this->legend_items as $item) {
			// border-color is for legend element ::before pseudo element.
			$this->addItem(
				(new CDiv(new CSpan($item['name'])))
					->addClass(self::ZBX_STYLE_GRAPH_LEGEND_ITEM)
					->setAttribute('style', '--color: '.$item['color'])
			);

			if ($this->show_statistic) {
				if (array_key_exists('units', $item)) {
					$this->addItem([
						new CDiv(convertUnits([
							'value' => $item['min'],
							'units' => $item['units'],
							'convert' => ITEM_CONVERT_NO_UNITS
						])),
						new CDiv(convertUnits([
							'value' => $item['avg'],
							'units' => $item['units'],
							'convert' => ITEM_CONVERT_NO_UNITS
						])),
						new CDiv(convertUnits([
							'value' => $item['max'],
							'units' => $item['units'],
							'convert' => ITEM_CONVERT_NO_UNITS
						]))
					]);
				}
				else {
					$this->addItem(
						(new CDiv('['._('no data').']'))->addClass(self::ZBX_STYLE_GRAPH_LEGEND_NO_DATA)
					);
				}
			}
		}
	}

	public function toString($destroy = true): string {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addClass($this->show_statistic ? self::ZBX_STYLE_GRAPH_LEGEND_STATISTIC : null)
			->addStyle('--lines: '.$this->getLinesCount().';');

		if (!$this->show_statistic) {
			$this->addStyle('--columns: '. min($this->columns_count, count($this->legend_items)).';');
		}

		$this->draw();

		return parent::toString($destroy);
	}
}

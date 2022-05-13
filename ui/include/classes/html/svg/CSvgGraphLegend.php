<?php declare(strict_types = 1);
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

	private const ZBX_STYLE_CLASS = 'svg-graph-legend';

	private const ZBX_STYLE_GRAPH_LEGEND_SINGLE_ITEM = 'svg-single-item-graph-legend';
	private const ZBX_STYLE_GRAPH_LEGEND_TWO_ITEMS = 'svg-single-two-items-graph-legend';

	private $labels;

	private $height;

	/**
	 * @param array  $labels
	 * @param string $labels[]['name']
	 * @param string $labels[]['color']
	 */
	public function __construct(array $labels, int $height) {
		parent::__construct();

		$this->labels = $labels;
		$this->height = $height;
	}

	private function draw(): void {
		switch (count($this->labels)) {
			case 1:
				$this->addClass(self::ZBX_STYLE_GRAPH_LEGEND_SINGLE_ITEM);
				break;

			case 2:
				$this->addClass(self::ZBX_STYLE_GRAPH_LEGEND_TWO_ITEMS);
				break;

			default:
				$this->addClass(self::ZBX_STYLE_CLASS);
				break;
		}

		foreach ($this->labels as $label) {
			// border-color is for legend element ::before pseudo element.
			$this->addItem(
				(new CDiv($label['name']))->setAttribute('style', 'border-color: '.$label['color'])
			);
		}
	}

	public function toString($destroy = true) {
		$this
			->setAttribute('style', 'height: '.$this->height.'px')
			->draw();

		return parent::toString($destroy);
	}
}

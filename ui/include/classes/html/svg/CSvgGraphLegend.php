<?php
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

	/**
	 * @param array  $labels
	 * @param string $labels[]['name']
	 * @param string $labels[]['color']
	 */
	public function __construct(array $labels = []) {
		parent::__construct();

		foreach ($labels as $label) {
			// border-color is for legend element ::before pseudo element.
			parent::addItem((new CDiv($label['name']))->setAttribute('style', 'border-color: '.$label['color']));
		}

		switch (count($labels)) {
			case 1:
				$this->addClass(CSvgTag::ZBX_STYLE_GRAPH_LEGEND_SINGLE_ITEM);
				break;

			case 2:
				$this->addClass(CSvgTag::ZBX_STYLE_GRAPH_LEGEND_TWO_ITEMS);
				break;

			default:
				$this->addClass(CSvgTag::ZBX_STYLE_GRAPH_LEGEND);
				break;
		}
	}
}

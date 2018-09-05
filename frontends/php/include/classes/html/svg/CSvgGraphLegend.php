<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * Graph labels data array. Every element of array is array with 'name' and 'color' value.
	 *
	 * @var array
	 */
	protected $labels;

	public function __construct(array $labels = []) {
		parent::__construct();

		$this->labels = $labels;
		$this->addClass(CSvgTag::ZBX_STYLE_GRAPH_LEGEND);
	}

	public function toString($destroy = true) {
		foreach ($this->labels as $label) {
			parent::addItem((new CDiv($label['name']))
				// border-color is for legend element ::before pseudo element.
				->setAttribute('style', 'border-color: '.$label['color'])
			);
		}

		return parent::toString($destroy);
	}
}

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


class CSvg extends CTag {

	protected $styles = [];

	public function __construct() {
		parent::__construct('svg', true);

		$this->setAttribute('version', '1.1');
		$this->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
		$this->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
	}

	public function addItem($value) {
		if ($value instanceof CSvgTag) {
			$this->styles = $value->getStyles() + $this->styles;
		}

		return parent::addItem($value);
	}

	protected function startToString() {
		$styles = "\n";

		foreach ($this->styles as $selector => $properties) {
			if ($properties) {
				$styles .= $selector.'{';
				foreach ($properties as $property => $value) {
					$styles .= $property.':'.$value.';';
				}
				$styles .= '}'."\n";
			}
		}

		return parent::startToString().(new CTag('style', true, $styles));
	}
}

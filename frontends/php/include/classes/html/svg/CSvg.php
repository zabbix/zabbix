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
			$this->styles = array_merge($this->styles, $value->getStyles());
		}

		return parent::addItem($value);
	}

	public function toString($destroy = true) {
		$styles = '';
		foreach ($this->styles as $selector => $property) {
			$styles .= $selector.'{'.$property.'}';
		}

		parent::addItem(new CTag('style', true, $styles));

		return parent::toString();
	}
}

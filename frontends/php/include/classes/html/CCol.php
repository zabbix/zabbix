<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CCol extends CTag {

	public function __construct($item = null, $class = null, $colspan = null, $width = null) {
		parent::__construct('td', 'yes');
		$this->addItem($item);
		if ($class !== null) {
			$this->addClass($class);
		}
		if ($colspan !== null) {
			$this->setAttribute('colspan', $colspan);
		}
		if ($width !== null) {
			$this->setAttribute('width', $width);
		}
	}

	public function setAlign($value) {
		$this->setAttribute('align', strval($value));
	}

	public function setRowSpan($value) {
		$this->setAttribute('rowspan', strval($value));
	}

	public function setColSpan($value) {
		$this->setAttribute('colspan', strval($value));
	}

	public function setWidth($value) {
		if (is_string($value)) {
			$this->setAttribute('width', $value);
		}
	}
}

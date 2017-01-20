<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

	public function __construct($item = null) {
		parent::__construct('td', true);
		$this->addItem($item);
	}

	public function setRowSpan($value) {
		$this->setAttribute('rowspan', $value);
		return $this;
	}

	public function setColSpan($value) {
		$this->setAttribute('colspan', $value);
		return $this;
	}

	public function setWidth($value) {
		$this->setAttribute('width', $value);
		return $this;
	}
}

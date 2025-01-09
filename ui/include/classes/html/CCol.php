<?php
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


class CCol extends CTag {

	protected $tag = 'td';

	public function __construct($item = null) {
		parent::__construct($this->tag, true);
		$this->addItem($item);
	}

	public function setRowSpan($value) {
		$this->setAttribute('rowspan', $value);

		return $this;
	}

	public function getColSpan() {
		return $this->getAttribute('colspan') ?? 1;
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

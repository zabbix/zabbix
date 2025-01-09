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


class CTableColumn extends CTag {

	protected $header;

	public function __construct($item = '') {
		parent::__construct('col', true);

		$this->header = ($item instanceof CCol)
			? $item
			: (new CColHeader($item));
	}

	/**
	 * Returns header cell element for column.
	 *
	 * @return CCol
	 */
	public function getHeader(): CCol {
		return $this->header;
	}

	/**
	 * Passes through setting of class names to the internal header object.
	 *
	 * @return CTableColumn
	 */
	public function addClass($class) {
		$this->header->addClass($class);

		return $this;
	}
}

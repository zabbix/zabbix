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


class CHorList extends CList {

	/**
	 * Creates a UL horizontal list with spaces between elements.
	 *
	 * @param array $values			an array of items to add to the list
	 */
	public function __construct(array $values = []) {
		parent::__construct($values);

		$this->addClass(ZBX_STYLE_HOR_LIST);
	}

}

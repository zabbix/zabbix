<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CSeverityCheckBoxList extends CCheckBoxList {

	/**
	 * Number of columns.
	 */
	private const COLUMNS = 3;

	/**
	 * Create check box list with severities.
	 *
	 * @param string $name  Field name in form.
	 */
	public function __construct(string $name) {
		parent::__construct($name);

		$this
			->setOptions(CSeverityHelper::getSeverities())
			->setVertical(true)
			->setColumns(self::COLUMNS);
	}
}

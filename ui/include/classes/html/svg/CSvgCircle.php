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


class CSvgCircle extends CSvgTag {

	public function __construct($x, $y, $diameter) {
		parent::__construct('circle');

		$this->setAttribute('cx', round($x));
		$this->setAttribute('cy', round($y));
		$this->setAttribute('r', round($diameter / 2, 1));
	}
}

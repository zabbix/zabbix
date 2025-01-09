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


class CSvgLine extends CSvgTag {

	public const ZBX_STYLE_DASHED = 'svg-line-dashed';

	public function __construct($x1, $y1, $x2, $y2) {
		parent::__construct('line');

		$this->setAttribute('x1', $x1);
		$this->setAttribute('y1', $y1);
		$this->setAttribute('x2', $x2);
		$this->setAttribute('y2', $y2);
	}
}

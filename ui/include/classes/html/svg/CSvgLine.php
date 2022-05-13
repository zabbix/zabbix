<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

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


class CSvgPath extends CSvgTag {

	protected $directions;
	protected $last_value = 0;

	public function __construct($directions = '') {
		parent::__construct('path');

		$this->setDirections($directions);
	}

	public function setDirections($directions) {
		$this->directions = $directions;
	}

	public function moveTo($x, $y) {
		$this->directions .= ' M'.floor($x).','.ceil($y);

		return $this;
	}

	public function lineTo($x, $y) {
		$this->directions .= ' L'.floor($x).','.ceil($y);

		return $this;
	}

	public function closePath() {
		$this->directions .= ' Z';

		return $this;
	}

	public function toString($destroy = true) {
		$this->setAttribute('d', trim($this->directions));

		return parent::toString($destroy);
	}
}

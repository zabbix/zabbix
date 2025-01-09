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


class CSvgPath extends CSvgTag {

	protected $directions;
	protected $last_value = 0;

	public function __construct($directions = '') {
		parent::__construct('path');

		$this->directions = $directions;
	}

	public function moveTo($x, $y): self {
		$this->directions .= ' M'.round($x).','.ceil($y);

		return $this;
	}

	public function lineTo($x, $y): self {
		$this->directions .= ' L'.round($x).','.ceil($y);

		return $this;
	}

	public function closePath(): self {
		$this->directions .= ' Z';

		return $this;
	}

	public function toString($destroy = true): string {
		if (trim($this->directions) !== '') {
			$this->setAttribute('d', trim($this->directions));
		}

		return parent::toString($destroy);
	}
}

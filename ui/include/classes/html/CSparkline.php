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


class CSparkline extends CTag {

	public function __construct() {
		parent::__construct('z-sparkline', true);
	}

	/**
	 * @param int $height
	 *
	 * @return self
	 */
	public function setHeight(int $height): self {
		$this->setAttribute('height', $height);

		return $this;
	}

	/**
	 * @param string $color
	 *
	 * @return self
	 */
	public function setColor(string $color): self {
		$this->setAttribute('color', $color);

		return $this;
	}

	/**
	 * @param int $line_width
	 *
	 * @return self
	 */
	public function setLineWidth(int $line_width): self {
		$this->setAttribute('line-width', $line_width);

		return $this;
	}

	/**
	 * @param int $fill
	 *
	 * @return self
	 */
	public function setFill(int $fill): self {
		$this->setAttribute('fill', $fill);

		return $this;
	}

	/**
	 * @param array $value
	 *
	 * @return self
	 */
	public function setValue(array $value): self {
		$this->setAttribute('value', $value);

		return $this;
	}

	/**
	 * @param int $from
	 *
	 * @return self
	 */
	public function setTimePeriodFrom(int $from): self {
		$this->setAttribute('time-period-from', $from);

		return $this;
	}

	/**
	 * @param int $to
	 *
	 * @return self
	 */
	public function setTimePeriodTo(int $to): self {
		$this->setAttribute('time-period-to', $to);

		return $this;
	}
}

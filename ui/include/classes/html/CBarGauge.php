<?php declare(strict_types = 0);
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


class CBarGauge extends CTag {
	private $thresholds = [];

	public function __construct() {
		parent::__construct('z-bar-gauge', true);
	}

	/**
	 * @param int|float $value
	 * @param string $fill
	 *
	 * @return CBarGauge
	 */
	public function addThreshold($value, string $fill): self {
		$this->thresholds[] = [
			'value' => $value,
			'fill' => $fill
		];

		return $this;
	}

	/**
	 * Selected option value. If no value is set, first available option will be preselected client at side.
	 *
	 * @param mixed $value
	 *
	 * @return CBarGauge
	 */
	public function setValue($value): self {
		$this->setAttribute('value', $value);

		return $this;
	}

	/**
	 * @param int|string $width
	 *
	 * @return self
	 */
	public function setWidth($width): self {
		$this->setAttribute('width', $width);

		return $this;
	}

	public function toString($destroy = true) {
		foreach ($this->thresholds as $threshold) {
			$this->addItem(
				(new CTag('threshold', true))
					->setAttribute('value', $threshold['value'])
					->setAttribute('fill', $threshold['fill'])
			);
		}

		return parent::toString($destroy);
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CNumericBox extends CInput {

	private $allow_empty;
	private $allow_negative;
	private $min_length = 0;

	public function __construct($name = 'number', $value = '0', $maxlength = 20, $readonly = false,
			$allow_empty = false, $allow_negative = true) {
		parent::__construct('text', $name, $value);

		$this->setReadonly($readonly);
		$this->setAttribute('maxlength', $maxlength);

		$this->allow_empty = $allow_empty;
		$this->allow_negative = $allow_negative;
	}

	public function setWidth($value) {
		return $this->addStyle('width: '.$value.'px;');
	}

	/**
	 * Pad number with zeroes to maintain min length.
	 *
	 * @param int $min_length
	 *
	 * @return CNumericBox
	 */
	public function padWithZeroes(int $min_length): self {
		$this->min_length = $min_length;

		return $this;
	}

	public function toString($destroy = true) {
		$this->onChange('normalizeNumericBox(this, '.json_encode([
			'allow_empty' => $this->allow_empty,
			'allow_negative' => $this->allow_negative,
			'min_length' => $this->min_length
		]).');');

		return parent::toString($destroy);
	}
}

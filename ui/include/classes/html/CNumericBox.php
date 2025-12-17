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


class CNumericBox extends CInput {

	private bool $allow_empty;
	private bool $allow_negative;
	private int $min_length = 0;
	private ?int $default_value = null;

	public function __construct($name = 'number', $value = '0', $maxlength = 20, $readonly = false,
			$allow_empty = false, $allow_negative = true) {
		parent::__construct('text', $name, $value);

		$this->setReadonly($readonly);
		$this->setAttribute('maxlength', $maxlength);
		$this->setAttribute('data-field-type', 'text-box');

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

	public function setDefaultValue(int $value): static {
		$this->default_value = $value;

		return $this;
	}

	public function toString($destroy = true) {
		$this->onChange('normalizeNumericBox(this, '.json_encode([
			'default_value' => $this->default_value,
			'allow_empty' => $this->allow_empty,
			'allow_negative' => $this->allow_negative,
			'min_length' => $this->min_length
		]).');');

		return parent::toString($destroy);
	}
}

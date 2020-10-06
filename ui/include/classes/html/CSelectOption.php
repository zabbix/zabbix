<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * A structure used by CSelect that describes a single option of select element.
 */
class CSelectOption {

	/**
	 * @var string
	 */
	protected $label;

	/**
	 * @var mixed
	 */
	protected $value;

	/**
	 * Arbitrary data associated with this option.
	 *
	 * @var array
	 */
	protected $extra = [];

	/**
	 * @var bool
	 */
	protected $disabled = false;

	/**
	 * @param mixed  $value  Option value.
	 * @param string $label  Option name.
	 */
	public function __construct($value, string $label) {
		$this->value = $value;
		$this->label = $label;
	}

	/**
	 * Add arbitrary data associated with this option.
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return self
	 */
	public function setExtra(string $key, string $value): self {
		$this->extra[$key] = $value;

		return $this;
	}

	/**
	 * @return self
	 */
	public function setDisabled(): self {
		$this->disabled = true;

		return $this;
	}

	/**
	 * Formats this object into associative array.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$option = [
			'value' => $this->value,
			'label' => $this->label
		];

		if ($this->extra) {
			$option['extra'] = $this->extra;
		}

		if ($this->disabled) {
			$option['is_disabled'] = true;
		}

		return $option;
	}
}

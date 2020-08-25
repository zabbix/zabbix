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
	 * @var string $title
	 */
	protected $title;

	/**
	 * @var string $value
	 */
	protected $value;

	/**
	 * @var ?string $description
	 */
	protected $description;

	/**
	 * @var bool $disabled
	 */
	protected $disabled;

	/**
	 * @param string $title   Option name.
	 * @param string $value  Option value.
	 */
	public function __construct(string $title, string $value) {
		$this->title = $title;
		$this->value = $value;
		$this->description = null;
		$this->disabled = false;
	}

	/**
	 * @param string $description
	 */
	public function setDescription(string $description): self {
		$this->description = $description;

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
		$option_fmt = [
			'title' => $this->title,
			'value' => $this->value
		];

		if ($this->description !== null) {
			$option_fmt['desc'] = $this->description;
		}

		if ($this->disabled) {
			$option_fmt['disabled'] = 1;
		}

		return $option_fmt;
	}
}

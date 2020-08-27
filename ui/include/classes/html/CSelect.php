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


class CSelect extends CTag {

	/**
	 * @var string $name
	 */
	protected $name;

	/**
	 * @var CSelectOption[]|CSelectOptionGroup[] $options  List of options and option groups.
	 */
	protected $options = [];

	/**
	 * @param string $name  Input field name.
	 */
	public function __construct(string $name) {
		parent::__construct('z-select', true);

		$this->name = $name;
	}

	/**
	 * @param CSelectOption $option
	 *
	 * @return self
	 */
	public function addOption(CSelectOption $option): self {
		$this->options[] = $option;

		return $this;
	}

	/**
	 * @param CSelectOptionGroup $option_group
	 *
	 * @return self
	 */
	public function addOptionGroup(CSelectOptionGroup $option_group): self {
		$this->options[] = $option_group;

		return $this;
	}

	/**
	 * Selected option value. If no value is set, first available option will be preselected client at side.
	 *
	 * @param string $value
	 *
	 * @return self
	 */
	public function setValue(string $value): self {
		$this->setAttribute('value', $value);

		return $this;
	}

	/**
	 * ID to be used on button in conjunction with label's "for" attribute that binds delegates focus.
	 *
	 * @param string $buttonid
	 *
	 * @return self
	 */
	public function setButtonId(string $buttonid): self {
		$this->setAttribute('data-buttonid', $buttonid);

		return $this;
	}

	/**
	 * @param int $width
	 *
	 * @return self
	 */
	public function setWidth(int $width): self {
		$this->setAttribute('width', $width);

		return $this;
	}

	/**
	 * @param string $onchange
	 *
	 * @return self
	 */
	public function onChange($onchange): self {
		$this->setAttribute('onchange', $onchange);

		return $this;
	}

	/**
	 * @param bool $value
	 *
	 * @return self
	 */
	public function setReadonly(): self {
		$this->setAttribute('readonly', 'true');

		return $this;
	}

	/**
	 * @param bool $value
	 *
	 * @return self
	 */
	public function setDisabled(): self {
		$this->setAttribute('disabled', 'disabled');

		return $this;
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		$options = [];

		foreach ($this->options as $option) {
			$options[] = $option->toArray();
		}

		return $options;
	}

	public function toString($destroy = true) {
		$this->setAttribute('name', $this->name);
		$this->setAttribute('data-options', json_encode($this->toArray()));

		return parent::toString($destroy);
	}
}

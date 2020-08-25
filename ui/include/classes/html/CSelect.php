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
	 * If no value is set, first available option will be preselected.
	 *
	 * @var ?string $value
	 */
	protected $value = null;

	/**
	 * If width is not set, option list contents will determine select element width.
	 *
	 * @var ?int $value
	 */
	protected $width = null;

	/**
	 * ID to be used on button in conjunction with label's "for" attribute that binds delegates focus.
	 *
	 * @var ?string $buttonid
	 */
	protected $buttonid = null;

	/**
	 * @var ?string $onchange
	 */
	protected $onchange = null;

	/**
	 * @var bool $disabled
	 */
	protected $disabled = false;

	/**
	 * @param string $name    Input field name.
	 * @param array $options  (optional) Array of options or option groups.
	 */
	public function __construct(string $name, array $options = []) {
		parent::__construct('z-select', true);

		$this->name = $name;

		foreach ($options as $key => $option) {
			if (is_string($option)) {
				$this->addOption(new CSelectOption($option, (string) $key));
			}
			elseif (is_array($option)) {
				$this->addOptionGroup(new CSelectOptionGroup($key, $option));
			}
			elseif ($option instanceof CSelectOption) {
				$this->addOption($option);
			}
			elseif ($option instanceof CSelectOptionGroup) {
				$this->addOptionGroup($option);
			}
			else {
				throw new RuntimeException('Incorrect structure used for option.');
			}
		}
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
	 * Selected option value.
	 *
	 * @param string $value
	 *
	 * @return self
	 */
	public function setValue(string $value): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Selected option value.
	 *
	 * @param string $value
	 *
	 * @return self
	 */
	public function setButtonId(string $buttonid): self {
		$this->buttonid = $buttonid;

		return $this;
	}

	/**
	 * @param int $width
	 *
	 * @return self
	 */
	public function setWidth(int $width): self {
		$this->width = $width;

		return $this;
	}

	/**
	 * @param string $onchange
	 *
	 * @return self
	 */
	public function onChange($onchange): self {
		$this->onchange = $onchange;

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
		$this->disabled = true;

		return $this;
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		$result = ['options' => [], 'name' => $this->name, 'value' => $this->value, 'disabled' => $this->disabled,
			'buttonid' => $this->buttonid, 'width' => $this->width, 'onchange' => $this->onchange];

		foreach ($this->options as $option) {
			$result['options'][] = $option->toArray();
		}

		return $result;
	}

	public function toString($destroy = true) {
		$this->setAttribute('data-select', json_encode($this->toArray()));

		return parent::toString($destroy);
	}
}

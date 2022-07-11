<?php declare(strict_types = 0);
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


/**
 * A structure used by CSelect that describes an option group of select element.
 */
class CSelectOptionGroup {

	/**
	 * @var string $label
	 */
	protected $label;

	/**
	 * @var CSelectOption[]  List of options.
	 */
	protected $options = [];

	/**
	 * @var string|null  Custom template for group options.
	 */
	protected $option_template;

	/**
	 * @param string $label  Option group label.
	 */
	public function __construct(string $label) {
		$this->label = $label;
	}

	/**
	 * @param CSelectOption[] $options
	 *
	 * @return self
	 */
	public function addOptions(array $options): self {
		foreach ($options as $option) {
			$this->addOption($option);
		}

		return $this;
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
	 * Set custom template for this group options.
	 *
	 * @param string $template
	 *
	 * @return $this
	 */
	public function setOptionTemplate(string $template) {
		$this->option_template = $template;

		return $this;
	}

	/**
	 * Provides this object in associative array format.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$option_group = [
			'label' => $this->label,
			'options' => []
		];

		if ($this->option_template) {
			$option_group['option_template'] = $this->option_template;
		}

		foreach ($this->options as $option) {
			$option_group['options'][] = $option->toArray();
		}

		return $option_group;
	}
}

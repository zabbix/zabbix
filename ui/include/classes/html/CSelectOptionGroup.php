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
 * A structure used by CSelect that describes an option group of select element.
 */
class CSelectOptionGroup {

	/**
	 * @var string $title
	 */
	protected $title;

	/**
	 * @var CSelectOption[] $options  List of options.
	 */
	protected $options = [];

	/**
	 * @param string $title   Option group title.
	 */
	public function __construct(string $title) {
		$this->title = $title;
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
	 * Provides this object in associative array format.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$optgroup = [
			'title' => $this->title,
			'value' => []
		];

		foreach ($this->options as $option) {
			$optgroup['value'][] = $option->toArray();
		}

		return $optgroup;
	}
}

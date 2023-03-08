<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Zabbix\Widgets\Fields;

use Zabbix\Widgets\CWidgetField;

abstract class CWidgetFieldMultiSelect extends CWidgetField {

	// Is selecting multiple objects or a single one?
	private bool $is_multiple = true;

	// Additional filter parameters used for data selection.
	protected array $filter_parameters = [];

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->setDefault([]);
	}

	public function setValue($value): self {
		$this->value = (array) $value;

		return $this;
	}

	public function setFlags(int $flags): self {
		parent::setFlags($flags);

		if (($flags & self::FLAG_NOT_EMPTY) !== 0) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules, API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules();
		}

		return $this;
	}

	/**
	 * Is selecting multiple values or a single value?
	 */
	public function isMultiple(): bool {
		return $this->is_multiple;
	}

	/**
	 * Set field to multiple objects mode.
	 */
	public function setMultiple(bool $is_multiple = true): self {
		$this->is_multiple = $is_multiple;

		return $this;
	}

	/**
	 * Get additional filter parameters.
	 */
	public function getFilterParameters(): array {
		return $this->filter_parameters;
	}

	/**
	 * Set an additional filter parameter for data selection.
	 */
	public function setFilterParameter(string $name, $value): self {
		$this->filter_parameters[$name] = $value;

		return $this;
	}
}

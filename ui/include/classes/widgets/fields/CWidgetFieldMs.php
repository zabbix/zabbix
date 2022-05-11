<?php
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


class CWidgetFieldMs extends CWidgetField {

	/**
	 * Is selecting multiple objects or a single one?
	 *
	 * @var bool
	 */
	protected $multiple = true;

	/**
	 * Additional filter parameters used for data selection.
	 *
	 * @var array
	 */
	protected $filter_parameters = [];

	/**
	 * Multiselect widget field.
	 * Will create text box field with select button, that will allow to select specified resource.
	 *
	 * @param string $name  Field name in form.
	 * @param string $label Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setDefault([]);
	}

	/**
	 * Set additional validation flags.
	 *
	 * @param int $flags
	 *
	 * @return CWidgetFieldMs
	 */
	public function setFlags($flags) {
		parent::setFlags($flags);

		if ($flags & self::FLAG_NOT_EMPTY) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules, API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules(null);
		}

		return $this;
	}

	/**
	 * @return CWidgetFieldMs
	 */
	public function setValue($value) {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Is selecting multiple values or a single value?
	 *
	 * @return bool
	 */
	public function isMultiple() {
		return $this->multiple;
	}

	/**
	 * Set field to multiple objects mode.
	 *
	 * @param bool $multiple
	 *
	 * @return CWidgetFieldMs
	 */
	public function setMultiple($multiple) {
		$this->multiple = $multiple;

		return $this;
	}

	/**
	 * Get additional filter parameters.
	 *
	 * @return array
	 */
	public function getFilterParameters() {
		return $this->filter_parameters;
	}

	/**
	 * Set an additional filter parameter for data selection.
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return CWidgetFieldMs
	 */
	public function setFilterParameter($name, $value) {
		$this->filter_parameters[$name] = $value;

		return $this;
	}
}

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

class CWidgetFieldWidgetSelect extends CWidgetField {

	public const DEFAULT_VALUE = '';

	private string $search_by_value;

	/**
	 * Field that creates a selection of widgets in current dashboard, filtered by given key of widget array.
	 *
	 * @param string $search_by_value  Value that will be searched in widgets.
	 */
	public function __construct(string $name, string $label = null, string $search_by_value = '') {
		parent::__construct($name, $label);

		$this->search_by_value = $search_by_value;

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
	}

	public function getSearchByValue(): string {
		return $this->search_by_value;
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

	public function setValue($value): self {
		if ($value === '' || ctype_alnum((string) $value)) {
			$this->value = $value;
		}

		return $this;
	}
}

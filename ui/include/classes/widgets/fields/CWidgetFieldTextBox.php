<?php declare(strict_types = 0);
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


namespace Zabbix\Widgets\Fields;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldTextBox extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldTextBoxView::class;
	public const DEFAULT_VALUE = '';

	/**
	 * Text box widget field.
	 */
	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->setDefault(self::DEFAULT_VALUE);
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = parent::getValidationRules($strict);

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}

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

abstract class CWidgetFieldMultiSelect extends CWidgetField {

	public const DEFAULT_VALUE = [];

	// Is selecting multiple objects or a single one?
	private bool $is_multiple = true;

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this->setDefault(self::DEFAULT_VALUE);
	}

	public function setValue($value): self {
		$this->value = (array) $value;

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

	public function preventDefault($default_prevented = true): self {
		if ($default_prevented) {
			$this->setMultiple(false);
		}

		return parent::preventDefault($default_prevented);
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if ($strict) {
			$value = $this->getValue();

			if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $value) && $value[self::FOREIGN_REFERENCE_KEY] === '') {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->getErrorLabel(),
					_('referred widget is unavailable')
				);
			}
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();

		if ($value === $this->getDefault()) {
			return;
		}

		if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $value)) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_STR,
				'name' => $this->name.'.'.self::FOREIGN_REFERENCE_KEY,
				'value' => $value[self::FOREIGN_REFERENCE_KEY]
			];
		}
		else {
			parent::toApi($widget_fields);
		}
	}

	protected function getValidationRules(bool $strict = false): array {
		$value = $this->getValue();

		if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $value)) {
			$validation_rules = ['type' => API_OBJECT, 'fields' => [
				self::FOREIGN_REFERENCE_KEY => ['type' => API_STRING_UTF8]
			]];
		}
		else {
			$validation_rules = parent::getValidationRules($strict);

			if ($strict && ($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
				self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);
			}
		}

		return $validation_rules;
	}
}

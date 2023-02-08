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

use CAbsoluteTimeParser,
	CParser,
	CRelativeTimeParser,
	DB;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldDatePicker extends CWidgetField {

	public const DEFAULT_VALUE = '';

	private bool $is_date_only;

	public function __construct(string $name, string $label = null, bool $is_date_only = false) {
		parent::__construct($name, $label);

		$this->is_date_only = $is_date_only;

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR)
			->setValidationRules([
				'type' => API_STRING_UTF8,
				'length' => DB::getFieldLength('widget_field', 'value_str')
			]);
	}

	public function setFlags(int $flags): self {
		parent::setFlags($flags);

		$validation_rules = $this->getValidationRules();
		$validation_rules['flags'] = $validation_rules['flags'] ?? 0x00;

		if (($flags & self::FLAG_NOT_EMPTY) !== 0) {
			$validation_rules['flags'] |= API_NOT_EMPTY;
		}
		else {
			$validation_rules['flags'] &= 0xFF ^ API_NOT_EMPTY;
		}

		$this->setValidationRules($validation_rules);

		return $this;
	}

	public function validate(bool $strict = false): array {
		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		$label = $this->full_name ?? $this->label ?? $this->name;
		$value = $this->value ?? $this->default;

		if ($value === '' && ($this->getFlags() & self::FLAG_NOT_EMPTY) === 0) {
			$this->setValue('');

			return [];
		}

		$absolute_time_parser = new CAbsoluteTimeParser();

		if ($absolute_time_parser->parse($value) == CParser::PARSE_SUCCESS) {
			$has_errors = false;

			if ($this->is_date_only) {
				$has_errors = $absolute_time_parser->getDateTime(true)->format('H:i:s') !== '00:00:00';
			}

			if (!$has_errors) {
				$this->setValue($value);

				return [];
			}
		}

		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse($value) == CParser::PARSE_SUCCESS) {
			$has_errors = false;

			if ($this->is_date_only) {
				foreach ($relative_time_parser->getTokens() as $token) {
					if ($token['suffix'] === 'h' || $token['suffix'] === 'm' || $token['suffix'] === 's') {
						$has_errors = true;
						break;
					}
				}
			}

			if (!$has_errors) {
				$this->setValue($value);

				return [];
			}
		}

		$this->setValue($this->default);

		return [
			_s('Invalid parameter "%1$s": %2$s.', $label,
				$this->is_date_only ? _('a date is expected') : _('a time is expected')
			)
		];
	}
}

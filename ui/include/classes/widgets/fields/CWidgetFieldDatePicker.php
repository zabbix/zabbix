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
	CRelativeTimeParser;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldDatePicker extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldDatePickerView::class;
	public const DEFAULT_VALUE = '';

	private bool $is_date_only = false;

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setMaxLength(255);
	}

	public function validate(bool $strict = false): array {
		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		$value = $this->getValue();

		if ($value === self::DEFAULT_VALUE) {
			return [];
		}

		$absolute_time_parser = new CAbsoluteTimeParser();

		if ($absolute_time_parser->parse($value) === CParser::PARSE_SUCCESS) {
			$has_errors = false;

			if ($this->is_date_only) {
				$has_errors = $absolute_time_parser->getDateTime(true)->format('H:i:s') !== '00:00:00';
			}

			if (!$has_errors) {
				return [];
			}
		}

		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse($value) === CParser::PARSE_SUCCESS) {
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
				return [];
			}
		}

		return [
			_s('Invalid parameter "%1$s": %2$s.', $this->full_name ?? $this->label ?? $this->name,
				$this->is_date_only ? _('a date is expected') : _('a time is expected')
			)
		];
	}

	public function setDateOnly(bool $is_date_only = true): self {
		$this->is_date_only = $is_date_only;

		return $this;
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = parent::getValidationRules($strict);

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}

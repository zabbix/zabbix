<?php declare(strict_types = 1);
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


class CWidgetFieldDatePicker extends CWidgetField {

	/**
	 * @var array
	 */
	private $date_time_formats;

	/**
	 * @var bool
	 */
	private $is_date_only;

	/**
	 * @param string $name
	 * @param string $label
	 * @param array  $date_time_formats
	 * @param bool   $is_date_only
	 */
	public function __construct(string $name, string $label, array $date_time_formats, bool $is_date_only) {
		parent::__construct($name, $label);

		$this->date_time_formats = $date_time_formats;
		$this->is_date_only = $is_date_only;

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules([
			'type' => API_STRING_UTF8,
			'length' => DB::getFieldLength('widget_field', 'value_str')
		]);
		$this->setDefault('');
	}

	/**
	 * @param $flags
	 *
	 * @return CWidgetFieldDatePicker
	 */
	public function setFlags($flags): self {
		parent::setFlags($flags);

		$validation_rules = $this->getValidationRules();
		$validation_rules['flags'] = $validation_rules['flags'] ?? 0x00;

		if (($flags & self::FLAG_NOT_EMPTY) != 0) {
			$validation_rules['flags'] |= API_NOT_EMPTY;
		}
		else {
			$validation_rules['flags'] &= 0xFF ^ API_NOT_EMPTY;
		}

		$this->setValidationRules($validation_rules);

		return $this;
	}

	/**
	 * @return array
	 */
	public function getDateTimeFormats(): array {
		return $this->date_time_formats;
	}

	/**
	 * @return bool
	 */
	public function isDateOnly(): bool {
		return $this->is_date_only;
	}

	/**
	 * @param bool $strict
	 *
	 * @return array
	 */
	public function validate(bool $strict = false): array {
		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		$label = $this->full_name ?? $this->label ?? $this->name;
		$value = $this->value ?? $this->default;

		if ($value === '' && ($this->getFlags() & self::FLAG_NOT_EMPTY) == 0) {
			$this->setValue('');

			return [];
		}

		foreach ($this->getDateTimeFormats() as $datetime_format) {
			$datetime = DateTime::createFromFormat('!'.$datetime_format, $value);
			$last_errors = DateTime::getLastErrors();

			if ($datetime !== false && $last_errors['warning_count'] == 0 && $last_errors['error_count'] == 0) {
				$this->setValue($value);

				return [];
			}
		}

		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse($value) == CParser::PARSE_SUCCESS) {
			$has_errors = false;

			if ($this->isDateOnly()) {
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
				$this->isDateOnly() ? _('a date is expected') : _('a time is expected')
			)
		];
	}
}

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

use CApiInputValidator,
	CRangeTimeParser,
	CTimePeriodHelper,
	CTimePeriodValidator,
	DateTimeZone;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldTimePeriod extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldTimePeriodView::class;
	public const DEFAULT_VALUE = ['from' => '', 'to' => ''];

	public const DATA_SOURCE_DEFAULT = 0;
	public const DATA_SOURCE_WIDGET = 1;
	public const DATA_SOURCE_DASHBOARD = 2;

	private int $data_source = self::DATA_SOURCE_DEFAULT;

	private ?string $from_label = null;
	private ?string $to_label = null;

	private array $default_period = ['from' => '', 'to' => ''];

	private ?int $min_period;
	private ?int $max_period;

	private ?DateTimeZone $timezone = null;
	private bool $is_date_only = false;

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->min_period = CTimePeriodHelper::getMinPeriod();
		$this->max_period = CTimePeriodHelper::getMaxPeriod();

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setMaxLength(255)
			->setValidationRules(['type' => API_OBJECT, 'fields' => [
				'from' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => $this->getMaxLength()],
				'to' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => $this->getMaxLength()]
			]]);
	}

	public function setValue($value): self {
		$value = (array) $value;

		if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $value)) {
			[
				'reference' => $reference
			] = self::parseTypedReference($value[self::FOREIGN_REFERENCE_KEY]);

			$this->data_source = $reference === self::REFERENCE_DASHBOARD
				? self::DATA_SOURCE_DASHBOARD
				: self::DATA_SOURCE_WIDGET;
		}
		elseif (array_key_exists('data_source', $value)) {
			$this->data_source = $value['data_source'];
		}
		else {
			$this->data_source = self::DATA_SOURCE_DEFAULT;
		}

		unset($value['data_source']);

		$this->value = $value;

		return $this;
	}

	public function validate(bool $strict = false): array {
		$validation_rules = $this->getValidationRules($strict);

		$field_label = $this->full_name ?? $this->label ?? '';
		$field_value = $this->getValue();

		$default = $this->getDefault();

		$errors = [];

		if ($this->data_source !== self::DATA_SOURCE_DEFAULT) {
			$data_source_label = $this->data_source === self::DATA_SOURCE_DASHBOARD ? _('Dashboard') : _('Widget');

			$reference_value = array_key_exists(CWidgetField::FOREIGN_REFERENCE_KEY, $field_value)
				? $field_value[CWidgetField::FOREIGN_REFERENCE_KEY]
				: '';

			if (!CApiInputValidator::validate($validation_rules['fields'][CWidgetField::FOREIGN_REFERENCE_KEY],
					$reference_value, ($field_label !== '' ? $field_label.'/' : '').$data_source_label, $error)) {
				$errors[] = $error;
			}
		}
		else {
			$default_period = $this->getDefaultPeriod();

			foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $field => $field_ts) {
				if (!array_key_exists($field, $field_value)) {
					if ($strict) {
						$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->name,
							_s('the parameter "%1$s" is missing', $field)
						);
						continue;
					}

					$field_value[$field] = array_key_exists(self::FOREIGN_REFERENCE_KEY, $default)
						? $default_period[$field]
						: $default[$field];
				}
			}

			$errors = CTimePeriodValidator::validate($field_value, [
				'require_date_only' => $this->is_date_only,
				'require_not_empty' => (bool) ($validation_rules['fields']['from']['flags'] & API_NOT_EMPTY),
				'min_period' => $this->min_period,
				'max_period' => $this->max_period,
				'from_label' => ($field_label !== '' ? $field_label.'/' : '').$this->getFromLabel(),
				'to_label' => ($field_label !== '' ? $field_label.'/' : '').$this->getToLabel()
			]);
		}

		if ($errors) {
			$field_value = $default;

			if (!array_key_exists(self::FOREIGN_REFERENCE_KEY, $field_value)) {
				$range_time_parser = new CRangeTimeParser();

				foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $name => $name_ts) {
					if ($field_value[$name] !== '') {
						$range_time_parser->parse($field_value[$name]);
						$field_value[$name_ts] = $range_time_parser
							->getDateTime($name === 'from', $this->timezone)
							->getTimestamp();
					}
					else {
						$field_value[$name_ts] = 0;
					}
				}
			}
		}

		$this->setValue($field_value);

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();
		$default = $this->getDefault();

		switch ($this->data_source) {
			case self::DATA_SOURCE_DEFAULT:
				foreach (['from', 'to'] as $name) {
					if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $default) || $value[$name] !== $default[$name]) {
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => $this->name.'.'.$name,
							'value' => $value[$name]
						];
					}
				}
				return;

			case self::DATA_SOURCE_WIDGET:
			case self::DATA_SOURCE_DASHBOARD:
				if ($value === $default) {
					return;
				}

				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.self::FOREIGN_REFERENCE_KEY,
					'value' => $value[self::FOREIGN_REFERENCE_KEY]
				];
				return;
		}
	}

	public function getDataSource(): int {
		return $this->data_source;
	}

	public function getFromLabel(): string {
		return $this->from_label ?? _('From');
	}

	public function setFromLabel(string $label): self {
		$this->from_label = $label;

		return $this;
	}

	public function getToLabel(): string {
		return $this->to_label ?? _('To');
	}

	public function setToLabel(string $label): self {
		$this->to_label = $label;

		return $this;
	}

	public function getDefaultPeriod(): array {
		return $this->default_period;
	}

	public function setTimeZone(?DateTimeZone $timezone): self {
		$this->timezone = $timezone;

		return $this;
	}

	public function setDateOnly(bool $is_date_only = true): self {
		$this->is_date_only = $is_date_only;

		return $this;
	}

	/**
	 * @param array  $period
	 *        string $period['from']
	 *        string $period['to']
	 *
	 * @return $this
	 */
	public function setDefaultPeriod(array $period): self {
		$this->default_period = $period;

		return $this;
	}

	public function setMinPeriod(?int $min_period): self {
		$this->min_period = $min_period;

		return $this;
	}

	public function setMaxPeriod(?int $max_period): self {
		$this->max_period = $max_period;

		return $this;
	}

	protected function getValidationRules(bool $strict = false): array {
		if ($this->data_source === self::DATA_SOURCE_DEFAULT) {
			$validation_rules = parent::getValidationRules($strict);

			if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
				self::setValidationRuleFlag($validation_rules['fields']['from'], API_NOT_EMPTY);
				self::setValidationRuleFlag($validation_rules['fields']['to'], API_NOT_EMPTY);
			}
		}
		else {
			$validation_rules = ['type' => API_OBJECT, 'fields' => [
				CWidgetField::FOREIGN_REFERENCE_KEY => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]];

			if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
				self::setValidationRuleFlag($validation_rules['fields'][CWidgetField::FOREIGN_REFERENCE_KEY],
					API_NOT_EMPTY
				);
			}
		}

		return $validation_rules;
	}
}

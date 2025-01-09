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

use CAbsoluteTimeParser,
	CApiInputValidator,
	CParser,
	CRelativeTimeParser,
	CTimePeriodHelper,
	DateTimeZone;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldTimePeriod extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldTimePeriodView::class;
	public const DEFAULT_VALUE = ['from' => '', 'to' => ''];

	public const DATA_SOURCE_DEFAULT = 0;
	public const DATA_SOURCE_WIDGET = 1;
	public const DATA_SOURCE_DASHBOARD = 2;

	private ?string $from_label = null;
	private ?string $to_label = null;

	private array $default_period = ['from' => '', 'to' => ''];

	private ?int $min_period = 0;
	private ?int $max_period = 0;

	private ?DateTimeZone $timezone = null;
	private bool $is_date_only = false;

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setMaxLength(255)
			->setValidationRules(['type' => API_OBJECT, 'fields' => [
				'from' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => $this->getMaxLength()],
				'to' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => $this->getMaxLength()]
			]]);
	}

	public function setValue($value): self {
		parent::setValue((array) $value);

		return $this;
	}

	public function validate(bool $strict = false): array {
		$validation_rules = $this->getValidationRules($strict);

		$value = $this->getValue();
		$default = $this->getDefault();
		$data_source = $this->getDataSource();

		$errors = [];

		if ($data_source !== self::DATA_SOURCE_DEFAULT) {
			$data_source_label = $this->getComponentErrorLabel($data_source === self::DATA_SOURCE_DASHBOARD
				? _('Dashboard')
				: _('Widget')
			);

			$has_reference_key = array_key_exists(CWidgetField::FOREIGN_REFERENCE_KEY, $value);
			$reference_value = $has_reference_key ? $value[CWidgetField::FOREIGN_REFERENCE_KEY] : '';

			if (!CApiInputValidator::validate($validation_rules['fields'][CWidgetField::FOREIGN_REFERENCE_KEY],
					$reference_value, $data_source_label, $error)) {
				$errors[] = $error;
			}
			elseif (!$has_reference_key && ($strict || ($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0)) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', $data_source_label, _('cannot be empty'));
			}
			elseif ($strict && $has_reference_key && $reference_value === '') {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', $data_source_label,
					_('referred widget is unavailable')
				);
			}
		}
		else {
			$absolute_time_parser = new CAbsoluteTimeParser();
			$relative_time_parser = new CRelativeTimeParser();

			$field_labels = [
				'from' => $this->getComponentErrorLabel($this->getFromLabel()),
				'to' => $this->getComponentErrorLabel($this->getToLabel())
			];

			foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $field => $field_ts) {
				if (!array_key_exists($field, $value)) {
					if ($strict || array_key_exists(self::FOREIGN_REFERENCE_KEY, $default)) {
						$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->name,
							_s('the parameter "%1$s" is missing', $field)
						);
						continue;
					}

					$value[$field] = $default[$field];
				}

				$field_value = &$value[$field];
				$field_value_ts = &$value[$field_ts];

				if (!CApiInputValidator::validate($validation_rules['fields'][$field], $field_value,
						$field_labels[$field], $error)) {
					$errors[] = $error;
					continue;
				}

				if ($field_value === '') {
					$field_value_ts = 0;
					continue;
				}

				if ($absolute_time_parser->parse($field_value) === CParser::PARSE_SUCCESS) {
					$datetime = $absolute_time_parser->getDateTime($field === 'from', $this->timezone);
					$time_range = $field === 'from' ? '00:00:00' : '23:59:59';

					if (!$this->is_date_only || $datetime->format('H:i:s') === $time_range) {
						$field_value_ts = $datetime->getTimestamp();
						continue;
					}
				}

				if ($relative_time_parser->parse($field_value) === CParser::PARSE_SUCCESS) {
					$datetime = $relative_time_parser->getDateTime($field === 'from', $this->timezone);
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
						$field_value_ts = $datetime->getTimestamp();
						continue;
					}
				}

				$errors[] = _s('Invalid parameter "%1$s": %2$s.', $field_labels[$field],
					$this->is_date_only ? _('a date is expected') : _('a time is expected')
				);
			}

			if (!$errors) {
				foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $field => $field_ts) {
					if ($value[$field_ts] < 0 || $value[$field_ts] > ZBX_MAX_DATE) {
						$errors[] = _s('Invalid parameter "%1$s": %2$s.', $field_labels[$field],
							$this->is_date_only ? _('a date is expected') : _('a time is expected')
						);
					}
				}
			}

			if (!$errors && $value['from'] !== '' && $value['to'] !== '') {
				$min_period = $this->min_period !== 0
					? $this->min_period
					: ($this->is_date_only ? null : CTimePeriodHelper::getMinPeriod());

				$max_period = $this->max_period !== 0
					? $this->max_period
					: ($this->is_date_only ? null : CTimePeriodHelper::getMaxPeriod($this->timezone));

				$period = $value['to_ts'] - $value['from_ts'] + 1;

				if ($min_period === null && $period <= 1) {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', $field_labels['to'],
						_s('value must be greater than "%1$s"', $field_labels['from'])
					);
				}
				elseif ($min_period !== null && $period < $min_period) {
					$errors[] = _n('Minimum time period to display is %1$s minute.',
						'Minimum time period to display is %1$s minutes.', (int) ($min_period / SEC_PER_MIN)
					);
				}
				elseif ($max_period !== null && $period > $max_period + 1) {
					$errors[] = _n('Maximum time period to display is %1$s day.',
						'Maximum time period to display is %1$s days.', (int) round($max_period / SEC_PER_DAY)
					);
				}
			}
		}

		if (!$errors) {
			$this->setValue($value);
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();
		$default = $this->getDefault();
		$group_name = rtrim(str_replace(['][', '['], '.', $this->name), ']');

		switch ($this->getDataSource()) {
			case self::DATA_SOURCE_DEFAULT:
				foreach (['from', 'to'] as $name) {
					if (!array_key_exists($name, $value)) {
						return;
					}

					if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $default) || $value[$name] !== $default[$name]) {
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => $group_name.'.'.$name,
							'value' => $value[$name]
						];
					}
				}
				return;

			case self::DATA_SOURCE_WIDGET:
			case self::DATA_SOURCE_DASHBOARD:
				if (!array_key_exists(self::FOREIGN_REFERENCE_KEY, $value)) {
					return;
				}

				if (!array_key_exists(self::FOREIGN_REFERENCE_KEY, $default)
						|| $value[self::FOREIGN_REFERENCE_KEY] !== $default[self::FOREIGN_REFERENCE_KEY]) {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $group_name.'.'.self::FOREIGN_REFERENCE_KEY,
						'value' => $value[self::FOREIGN_REFERENCE_KEY]
					];
				}
				return;
		}
	}

	public function getDataSource(): int {
		$value = $this->getValue();

		if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $value)) {
			[
				'reference' => $reference
			] = self::parseTypedReference($value[self::FOREIGN_REFERENCE_KEY]);

			return $reference === self::REFERENCE_DASHBOARD ? self::DATA_SOURCE_DASHBOARD : self::DATA_SOURCE_WIDGET;
		}
		elseif (array_key_exists('data_source', $value)) {
			return (int) $value['data_source'];
		}
		else {
			return self::DATA_SOURCE_DEFAULT;
		}
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

	private function getComponentErrorLabel(string $component_label): string {
		$error_label = $component_label;

		if ($this->label !== null) {
			$error_label = $this->label.'/'.$error_label;
		}

		if ($this->label_prefix !== null) {
			$error_label = $this->label_prefix.': '.$error_label;
		}

		return $error_label;
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
		if ($this->getDataSource() === self::DATA_SOURCE_DEFAULT) {
			$validation_rules = parent::getValidationRules($strict);

			if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
				self::setValidationRuleFlag($validation_rules['fields']['from'], API_NOT_EMPTY);
				self::setValidationRuleFlag($validation_rules['fields']['to'], API_NOT_EMPTY);
			}
		}
		else {
			$validation_rules = ['type' => API_OBJECT, 'fields' => [
				CWidgetField::FOREIGN_REFERENCE_KEY => ['type' => API_STRING_UTF8]
			]];
		}

		return $validation_rules;
	}
}

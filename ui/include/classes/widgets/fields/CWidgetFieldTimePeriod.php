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
	CApiInputValidator,
	CParser,
	CRelativeTimeParser;

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

	private bool $is_date_only;

	public function __construct(string $name, string $label = null, bool $is_date_only = false) {
		parent::__construct($name, $label);

		$this->is_date_only = $is_date_only;

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

		if (array_key_exists('data_source', $value)) {
			unset($value['data_source']);
		}

		if (array_key_exists(self::FOREIGN_REFERENCE_KEY, $value)) {
			[
				'reference' => $reference
			] = self::parseTypedReference($value[self::FOREIGN_REFERENCE_KEY]);

			$this->data_source = $reference === self::REFERENCE_DASHBOARD
				? self::DATA_SOURCE_DASHBOARD
				: self::DATA_SOURCE_WIDGET;
		}
		else {
			$this->data_source = self::DATA_SOURCE_DEFAULT;
		}

		$this->value = $value;

		return $this;
	}

	public function validate(bool $strict = false): array {
		$errors = [];

		$validation_rules = $this->getValidationRules($strict);
		$field_label = $this->full_name ?? $this->label ?? $this->name;
		$field_value = $this->getValue();

		if ($this->data_source === self::DATA_SOURCE_DEFAULT) {
			$absolute_time_parser = new CAbsoluteTimeParser();
			$relative_time_parser = new CRelativeTimeParser();
			$period = ['from' => 0, 'to' => 0];

			foreach ($field_value as $name => &$value) {
				$label = $field_label.'/'.($name === 'from' ? $this->getFromLabel() : $this->getToLabel());

				if (!CApiInputValidator::validate($validation_rules['fields'][$name], $value, $label, $error)) {
					$errors[] = $error;
					continue;
				}

				if ($value === self::DEFAULT_VALUE[$name]) {
					continue;
				}

				if ($absolute_time_parser->parse($value) === CParser::PARSE_SUCCESS) {
					$datetime = $absolute_time_parser->getDateTime($name === 'from');
					$time_range = $name === 'from' ? '00:00:00' : '23:59:59';

					if (!$this->is_date_only || $datetime->format('H:i:s') === $time_range) {
						$period[$name] = $datetime->getTimestamp();
						continue;
					}
				}

				if ($relative_time_parser->parse($value) === CParser::PARSE_SUCCESS) {
					$datetime = $relative_time_parser->getDateTime($name === 'from');
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
						$period[$name] = $datetime->getTimestamp();
						continue;
					}
				}

				$errors[] = [
					_s('Invalid parameter "%1$s": %2$s.', $label,
						$this->is_date_only ? _('a date is expected') : _('a time is expected')
					)
				];
			}
			unset($value);

			if ($period['from'] !== 0 && $period['to'] !== 0 && $period['from'] >= $period['to']) {
				$errors[] = [
					_s('Invalid parameter "%1$s": %2$s.', $field_label,
						$this->is_date_only ? _('a date is expected') : _('a time is expected')
					)
				];
			}

			if ($errors) {
				$this->setValue($this->getDefault());

				return $errors;
			}
		}

		$this->setValue($field_value);

		return [];
	}

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();

		if ($value === $this->getDefault()) {
			return;
		}

		switch ($this->data_source) {
			case self::DATA_SOURCE_DEFAULT:
				array_push($widget_fields,
					[
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'[from]',
						'value' => $value['from']
					],
					[
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'[to]',
						'value' => $value['to']
					]
				);
				break;

			case self::DATA_SOURCE_WIDGET:
			case self::DATA_SOURCE_DASHBOARD:
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'['.self::FOREIGN_REFERENCE_KEY.']',
					'value' => $value[self::FOREIGN_REFERENCE_KEY]
				];
				break;
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

	protected function getValidationRules(bool $strict = false): array {
		if ($this->data_source === self::DATA_SOURCE_DEFAULT) {
			$validation_rules = parent::getValidationRules($strict);

			if ($strict && ($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
				self::setValidationRuleFlag($validation_rules['fields']['from'], API_NOT_EMPTY);
				self::setValidationRuleFlag($validation_rules['fields']['to'], API_NOT_EMPTY);
			}
		}
		else {
			$validation_rules = ['type' => API_OBJECT, 'fields' => [
				'reference' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]];

			if ($strict && ($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
				self::setValidationRuleFlag($validation_rules['fields']['reference'], API_NOT_EMPTY);
			}
		}

		return $validation_rules;
	}
}

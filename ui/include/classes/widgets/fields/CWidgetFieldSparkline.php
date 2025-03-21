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
use CWidgetsData;

class CWidgetFieldSparkline extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldSparklineView::class;
	public const DEFAULT_VALUE = [];

	public const DATA_SOURCE_AUTO = 0;
	public const DATA_SOURCE_HISTORY = 1;
	public const DATA_SOURCE_TRENDS = 2;

	protected array $options;

	protected array $fields = [];

	public function __construct(string $name, ?string $label = null, array $options = []) {
		parent::__construct($name, $label);

		$options = array_replace_recursive([
			'width'			=> ['min' => 0, 'max' => 10, 'step' => 1],
			'fill'			=> ['min' => 0, 'max' => 10, 'step' => 1],
			'color'			=> ['use_default' => false],
			'time_period'	=> [
				'default_period'	=> ['from' => 'now-1h', 'to' => 'now']
			]
		], $options);
		$this->options = $options;

		$this->fields = [
			'width'			=> new CWidgetFieldRangeControl(
									$this->getFormFieldName('width'), _('Width'),
									$options['width']['min'], $options['width']['max'], $options['width']['step']
								),
			'fill'			=> new CWidgetFieldRangeControl(
									$this->getFormFieldName('fill'), _('Fill'),
									$options['fill']['min'], $options['fill']['max'], $options['fill']['step']
								),
			'color'			=> (new CWidgetFieldColor($this->getFormFieldName('color'), _('Color')))
									->allowInherited($options['color']['use_default']),
			'time_period'	=> (new CWidgetFieldTimePeriod(
									$this->getFormFieldName('time_period'), _('Time period'))
								)
									->setDefaultPeriod($options['time_period']['default_period'])
									->setDefault([
										CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
											CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
										)
									])
									->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
									->acceptWidget()
									->acceptDashboard(),
			'history'		=> (new CWidgetFieldRadioButtonList(
									$this->getFormFieldName('history'), _('History data'), [
										self::DATA_SOURCE_AUTO => _('Auto'),
										self::DATA_SOURCE_HISTORY => _('History'),
										self::DATA_SOURCE_TRENDS => _('Trends')
								]))->setDefault(self::DATA_SOURCE_AUTO)
		];

		if ($label !== null) {
			foreach ($this->fields as $field) {
				$field->prefixLabel($label);
			}
		}

		$this->setDefault(static::DEFAULT_VALUE);
	}

	public function getFormFieldName($name): string {
		return sprintf('%1$s[%2$s]', $this->name, $name);
	}

	public function setDefault($value): self {
		parent::setDefault($value);

		foreach (array_intersect_key($value, $this->fields) as $field_name => $field_value) {
			$this->fields[$field_name]->setDefault($field_value);
		}

		return $this;
	}

	public function getValue() {
		return array_merge(parent::getValue(), [
			'time_period' => $this->fields['time_period']->getValue()
		]);
	}

	public function getFields(): array {
		return $this->fields;
	}

	public function setInType(string $in_type): self {
		/** @var CWidgetFieldTimePeriod $time_period */
		$time_period = $this->fields['time_period'];
		$time_period->setInType($in_type);

		return $this;
	}

	public function getInType(): string {
		/** @var CWidgetFieldTimePeriod $time_period */
		$time_period = $this->fields['time_period'];

		return $time_period->getInType();
	}

	public function setValue($value): self {
		parent::setValue($value);

		foreach (array_intersect_key($value, $this->fields) as $field_name => $field_value) {
			$this->fields[$field_name]->setValue($field_value);
		}

		return $this;
	}

	public function validate(bool $strict = false): array {
		$errors = [];

		foreach ($this->fields as $field) {
			if ($field instanceof CWidgetFieldColor && !$field->hasAllowInherited()) {
				$field->setValidationRules(['type' => API_COLOR, 'flags' => API_REQUIRED | API_NOT_EMPTY]);
			}

			$errors = array_merge($errors, $field->validate($strict));
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		$data = [];

		foreach ($this->fields as $field) {
			$field->toApi($data);
		}

		foreach ($data as $api_field_data) {
			$api_field_data['name'] = rtrim(str_replace(['][', '['], '.', $api_field_data['name']), ']');
			$widget_fields[] = $api_field_data;
		}
	}
}

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

class CWidgetFieldTags extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldTagsView::class;
	public const DEFAULT_VALUE = [];
	public const DEFAULT_TAG = ['tag' => '', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''];

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'fields' => [
				'tag'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
				'operator'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])],
				'value'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255]
			]]);
	}

	/**
	 * Get field value. If no value is set, will return default value.
	 */
	public function getValue() {
		$field_value = parent::getValue();

		foreach ($field_value as $index => $value) {
			if ($value['tag'] === '' && $value['value'] === '') {
				unset($field_value[$index]);
			}
		}

		return $field_value;
	}

	public function setValue($value): self {
		$this->value = (array) $value;

		return $this;
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.'.$index.'.'.'tag',
				'value' => $value['tag']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.'.$index.'.'.'operator',
				'value' => $value['operator']
			];
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.'.$index.'.'.'value',
				'value' => $value['value']
			];
		}
	}
}

<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

use Zabbix\Widgets\CWidgetField;

use Widgets\ItemNavigator\Includes\WidgetForm;

class CWidgetFieldItemGrouping extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldItemGroupingView::class;
	public const DEFAULT_VALUE = [];
	public const MAX_ROWS = 10;

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'length' => self::MAX_ROWS, 'fields' => [
				'attribute'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [WidgetForm::GROUP_BY_HOST_GROUP, WidgetForm::GROUP_BY_HOST_NAME, WidgetForm::GROUP_BY_HOST_TAG, WidgetForm::GROUP_BY_ITEM_TAG])],
				'tag_name'	=> ['type' => API_STRING_UTF8, 'length' => $this->getMaxLength()]
			]]);
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		foreach ($this->getValue() as $value) {
			if ($value['attribute'] == WidgetForm::GROUP_BY_HOST_GROUP
					|| $value['attribute'] == WidgetForm::GROUP_BY_HOST_NAME) {
				continue;
			}

			if ($value['tag_name'] === '') {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Group by'), _('tag cannot be empty'));

				break;
			}
		}

		$unique_groupings = [];

		foreach ($this->getValue() as $value) {
			$attribute = $value['attribute'];
			$tag_name = $attribute == WidgetForm::GROUP_BY_HOST_TAG || $attribute == WidgetForm::GROUP_BY_ITEM_TAG
				? $value['tag_name']
				: '';

			if (array_key_exists($attribute, $unique_groupings) && $unique_groupings[$attribute] === $tag_name) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Group by'), _('rows must be unique'));

				break;
			}
			else {
				$unique_groupings[$attribute] = $tag_name;
			}
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.'.$index.'.'.'attribute',
				'value' => $value['attribute']
			];

			if ($value['attribute'] == WidgetForm::GROUP_BY_HOST_TAG
					|| $value['attribute'] == WidgetForm::GROUP_BY_ITEM_TAG) {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.'.'tag_name',
					'value' => $value['tag_name']
				];
			}
		}
	}
}

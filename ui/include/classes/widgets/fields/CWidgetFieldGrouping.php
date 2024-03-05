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

class CWidgetFieldGrouping extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldGroupingView::class;
	public const DEFAULT_VALUE = [];
	public array $attributes;
	public array $tag_fields;

	public function __construct(string $name, string $label, array $attributes, ?array $tag_fields) {
		parent::__construct($name, $label);

		$this->attributes = $attributes;
		$this->tag_fields = $tag_fields;

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'fields' => [
				'attribute'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', array_keys($this->attributes))],
				'tag_name'	=> ['type' => API_STRING_UTF8, 'length' => $this->getMaxLength()],
				'tag_fields' => ['type' => API_INT32, 'in' => implode(',', array_keys($this->attributes))]
			]]);
	}

	public function validate($strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$unique_groupings = [];

		foreach ($this->getValue() as $key => $value) {
			$attribute = $value['attribute'];
			$tag_name = $value['tag_name'] ?? null;

			if (array_key_exists($attribute, $unique_groupings) && $unique_groupings[$attribute] === $tag_name) {
				$errors[] = _s('Invalid parameter "%1$s": row #%2$s %3$s.', _('Group by'), $key + 1,
					_('is not unique'));
			}
			else {
				$unique_groupings[$attribute] = $tag_name;
			}

			if (in_array($attribute, $this->tag_fields) && $tag_name === '') {
				$errors[] = _s('Invalid parameter "%1$s": row #%2$s %3$s.', _('Group by'), $key + 1,
					_('tag cannot be empty'));
			}
		}

		if ($errors) {
			return $errors;
		}

		return [];
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.'.$index.'.'.'attribute',
				'value' => $value['attribute']
			];

			if (array_key_exists('tag_name', $value)) {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.'.'tag_name',
					'value' => $value['tag_name']
				];
			}
		}
	}
}

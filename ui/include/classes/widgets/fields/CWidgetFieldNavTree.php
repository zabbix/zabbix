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

class CWidgetFieldNavTree extends CWidgetField {

	public const DEFAULT_VALUE = [];

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'flags' => API_PRESERVE_KEYS, 'fields' => [
				'name'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
				'order'		=> ['type' => API_INT32, 'in' => '1:'.ZBX_MAX_INT32, 'default' => 1],
				'parent'	=> ['type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT32, 'default' => 0],
				'sysmapid'	=> ['type' => API_ID, 'default' => '0']
			]]);
	}

	public function setValue($value): self {
		$this->value = (array) $value;

		return $this;
	}

	public function validate(bool $strict = false): array {
		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		$field_value = $this->getValue();

		if ($field_value === self::DEFAULT_VALUE) {
			return [];
		}

		// Check and fix the tree of the maps.
		foreach ($field_value as $fieldid => &$navtree_item) {
			if ($navtree_item['parent'] != 0 && !array_key_exists($navtree_item['parent'], $field_value)) {
				$errors[] = _s('Incorrect value for field "%1$s": %2$s.',
					'navtree['.$fieldid.'][parent]', _('reference to a non-existent tree element')
				);
				$navtree_item['parent'] = 0;
			}
		}
		unset($navtree_item);

		// Find and fix circular dependencies.
		foreach ($field_value as $navtree_item) {
			$parentid = $navtree_item['parent'];
			$parentids = [$parentid => true];

			while ($parentid != 0) {
				if (array_key_exists($field_value[$parentid]['parent'], $parentids)) {
					$errors[] = _s('Incorrect value for field "%1$s": %2$s.',
						'navtree['.$parentid.'][parent]', _('circular dependency is not allowed')
					);
					$field_value[$parentid]['parent'] = 0;
				}

				$parentid = $field_value[$parentid]['parent'];
			}
		}

		$this->setValue($field_value);

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.'.$index.'.name',
				'value' => $value['name']
			];

			$value += ['order' => 1, 'parent' => 0, 'sysmapid' => 0];

			if ($value['order'] != 1) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.order',
					'value' => $value['order']
				];
			}

			if ($value['parent'] != 0) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.parent',
					'value' => $value['parent']
				];
			}

			if ($value['sysmapid'] != 0) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
					'name' => $this->name.'.'.$index.'.sysmapid',
					'value' => $value['sysmapid']
				];
			}
		}
	}
}

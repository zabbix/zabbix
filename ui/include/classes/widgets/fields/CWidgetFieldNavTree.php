<?php
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


class CWidgetFieldNavTree extends CWidgetField {

	/**
	 * Create widget field for Tags selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_OBJECTS, 'flags' => API_PRESERVE_KEYS, 'fields' => [
			'name'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
			'order'		=> ['type' => API_INT32, 'in' => '1:'.ZBX_MAX_INT32, 'default' => 1],
			'parent'	=> ['type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT32, 'default' => 0],
			'sysmapid'	=> ['type' => API_ID, 'default' => '0']
		]]);
		$this->setDefault([]);
	}

	public function setValue($value) {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Prepares array entry for widget field, ready to be passed to CDashboard API functions.
	 * Reference is needed here to avoid array merging in CWidgetForm::fieldsToApi method. With large number of widget
	 * fields it causes significant performance decrease.
	 *
	 * @param array $widget_fields   reference to Array of widget fields.
	 */
	public function toApi(array &$widget_fields = []) {
		$value = $this->getValue();

		foreach ($value as $index => $val) {
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.name.'.$index,
				'value' => $val['name']
			];

			// Add default values to avoid check of key existence.
			$val = array_merge([
				'order' => 1,
				'parent' => 0,
				'sysmapid' => 0
			], $val);

			if ($val['order'] != 1) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.order.'.$index,
					'value' => $val['order']
				];
			}
			if ($val['parent'] != 0) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.parent.'.$index,
					'value' => $val['parent']
				];
			}
			if ($val['sysmapid'] != 0) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
					'name' => $this->name.'.sysmapid.'.$index,
					'value' => $val['sysmapid']
				];
			}
		}
	}

	/**
	 * Check and fix the tree of the maps.
	 *
	 * @param array  $navtree_items
	 * @param string $navtree_items[<id>]['parent']
	 *
	 * @return array
	 */
	static private function validateNavTree(array $navtree_items, array &$errors) {
		// Check for incorrect parent IDs.
		foreach ($navtree_items as $fieldid => &$navtree_item) {
			if ($navtree_item['parent'] != 0 && !array_key_exists($navtree_item['parent'], $navtree_items)) {
				$errors[] = _s('Incorrect value for field "%1$s": %2$s.',
					'navtree.parent.'.$fieldid, _('reference to a non-existent tree element')
				);
				$navtree_item['parent'] = 0;
			}
		}
		unset($navtree_item);

		// Find and fix circular dependencies.
		foreach ($navtree_items as $fieldid => $navtree_item) {
			$parentid = $navtree_item['parent'];
			$parentids = [$parentid => true];

			while ($parentid != 0) {
				if (array_key_exists($navtree_items[$parentid]['parent'], $parentids)) {
					$errors[] = _s('Incorrect value for field "%1$s": %2$s.',
						'navtree.parent.'.$parentid, _('circular dependency is not allowed')
					);
					$navtree_items[$parentid]['parent'] = 0;
				}

				$parentid = $navtree_items[$parentid]['parent'];
			}
		}

		return $navtree_items;
	}

	/**
	 * @param bool $strict
	 *
	 * @return array
	 */
	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if (!$errors) {
			$this->setValue(self::validateNavTree($this->getValue(), $errors));
		}

		return $errors;
	}
}

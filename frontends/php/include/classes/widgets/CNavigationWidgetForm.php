<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CNavigationWidgetForm extends CWidgetForm {
	public function __construct($data) {
		parent::__construct($data);

		// widget name field
		$widget_name = (new CWidgetFieldTextBox('widget_name', _('Name')));
		if (array_key_exists('widget_name', $data)) {
			$widget_name->setValue($data['widget_name']);
		}
		$this->fields[] = $widget_name;

		// widget reference field
		$reference_field = (new CWidgetFieldReference());
		$reference = array_key_exists($reference_field->getName(), $data) ? $data[$reference_field->getName()] : '';
		$reference_field->setValue($reference);
		$this->fields[] = $reference_field;

		// Register dynamically created item fields.
		foreach ($data as $field_key => $value) {
			preg_match('/^map\.name\.(\d+)$/', $field_key, $field_details);

			if ($field_details) {
				$item_id = $field_details[1];

				$this->fields[] = (new CWidgetFieldHidden($field_key, $value, ZBX_WIDGET_FIELD_TYPE_STR))
						->setRequired(true);

				if (array_key_exists('map.parent.'.$item_id, $data)) {
					$value = (int)$data['map.parent.'.$item_id];
					$this->fields[] = (new CWidgetFieldHidden('map.parent.'.$item_id, $value, ZBX_WIDGET_FIELD_TYPE_INT32))
							->setRequired(true);
				}

				if (array_key_exists('map.order.'.$item_id, $data)) {
					$value = (int)$data['map.order.'.$item_id];
					$this->fields[] = (new CWidgetFieldHidden('map.order.'.$item_id, $value, ZBX_WIDGET_FIELD_TYPE_INT32))
							->setRequired(true);
				}

				if (array_key_exists('mapid.'.$item_id, $data) && $data['mapid.'.$item_id]) {
					$value = $data['mapid.'.$item_id];
					$this->fields[] = (new CWidgetFieldHidden('mapid.'.$item_id, $value, ZBX_WIDGET_FIELD_TYPE_MAP));
				}
			}
		}
	}

	public function validate() {
		$errors = [];

		foreach ($this->fields as $field) {
			// Validate each field seperately
			$errors = array_merge($errors, $field->validate());
		}

		return $errors;
	}
}

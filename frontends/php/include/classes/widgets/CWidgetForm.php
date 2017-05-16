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

class CWidgetForm
{
	protected $fields;

	public function __construct($data) {
		$known_widget_types = (new CWidgetConfig())->getKnownWidgetTypes();

		$this->fields = [];

		// Widget Type field
		$field_type = (new CWidgetFieldComboBox('type', _('Type'), $known_widget_types, WIDGET_CLOCK, 'updateWidgetConfigDialogue()'));
		$field_type->setRequired(true);
		if (array_key_exists('type', $data)) {
			$field_type->setValue($data['type']);
		}
		$this->fields[] = $field_type;
	}

	/**
	 * Return fields for this form
	 *
	 * @return CWidgetField[]
	 */
	public function getFields() {
		return $this->fields;
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

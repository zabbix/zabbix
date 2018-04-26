<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CWidgetFieldTags extends CWidgetField {

	/**
	 * Create widget field for Tags selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_OBJECTS, 'fields' => [
			'tag'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'operator'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL])],
			'value'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255]
		]]);
		$this->setDefault([]);
	}

	public function setValue($value) {
		$this->value = (array) $value;

		foreach ($this->value as $index => $val) {
			if (!array_key_exists('tag', $val) || ($val['tag'] === '' && $val['value'] === '')) {
				unset($this->value[$index]);
			}
		}

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
				'name' => $this->name.'.tag.'.$index,
				'value' => $val['tag']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.operator.'.$index,
				'value' => $val['operator']
			];
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.value.'.$index,
				'value' => $val['value']
			];
		}
	}
}

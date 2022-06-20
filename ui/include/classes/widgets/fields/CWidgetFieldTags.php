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
			'operator'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])],
			'value'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255]
		]]);
		$this->setDefault([]);
	}

	public function setValue($value) {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Get field value. If no value is set, will return default value.
	 *
	 * @return mixed
	 */
	public function getValue() {
		$value = parent::getValue();

		foreach ($value as $index => $val) {
			if ($val['tag'] === '' && $val['value'] === '') {
				unset($value[$index]);
			}
		}

		return $value;
	}

	/**
	 * Add dynamic row script and fix the distance between AND/OR buttons and tag inputs below them.
	 *
	 * @return string
	 */
	public function getJavascript() {
		return 'var tags_table = jQuery("#tags_table_'.$this->getName().'");'.

			'tags_table'.
				'.dynamicRows({template: "#tag-row-tmpl"})'.
				'.on("afteradd.dynamicRows", function() {'.
					'var rows = this.querySelectorAll(".form_row");'.
					'new CTagFilterItem(rows[rows.length - 1]);'.
				'});'.

			// Init existing fields once loaded.
			'document.querySelectorAll("#tags_table_'.$this->getName().' .form_row").forEach(row => {'.
				'new CTagFilterItem(row);'.
			'});';
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

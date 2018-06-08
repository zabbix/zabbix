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


class CWidgetForm {

	protected $fields;

	/**
	 * Widget fields array that came from AJAX request.
	 *
	 * @var array
	 */
	protected $data;

	public function __construct($data, $type) {
		$this->data = CJs::decodeJson($data);

		$this->fields = [];

		// Refresh interval field.
		$default_rf_rate = '';

		foreach (CWidgetConfig::getRfRates() as $rf_rate => $label) {
			if ($rf_rate == CWidgetConfig::getDefaultRfRate($type)) {
				$default_rf_rate = $label;
				break;
			}
		}

		$rf_rates = [
			-1 => _('Default').' ('.$default_rf_rate.')'
		];
		$rf_rates += CWidgetConfig::getRfRates();

		$rf_rate_field = (new CWidgetFieldComboBox('rf_rate', _('Refresh interval'), $rf_rates))
			->setDefault(-1);

		if (array_key_exists('rf_rate', $this->data)) {
			$rf_rate_field->setValue($this->data['rf_rate']);
		}

		$this->fields[] = $rf_rate_field;
	}

	/**
	 * Convert all dot-delimited keys to arrays of objets.
	 * Example:
	 *   source: [
	 *               'tags.tag.1' => 'tag1',
	 *               'tags.value.1' => 'value1',
	 *               'tags.tag.2' => 'tag2',
	 *               'tags.value.2' => 'value2'
	 *           ]
	 *   result: [
	 *               'tags' => [
	 *                   ['tag' => 'tag1', 'value' => 'value1'],
	 *                   ['tag' => 'tag2', 'value' => 'value2']
	 *               ]
	 *           ]
	 *
	 * @static
	 *
	 * @param array $data  An array of key => value pairs.
	 *
	 * @return array
	 */
	protected static function convertDottedKeys(array $data) {
		foreach ($data as $key => $value) {
			if (preg_match('/^([a-z]+)\.([a-z]+)\.(\d+)$/', $key, $matches) === 1) {
				$data[$matches[1]][$matches[3]][$matches[2]] = $value;
				unset($data[$key]);
			}
		}

		return $data;
	}

	/**
	 * Return fields for this form.
	 *
	 * @return array  An array of CWidgetField.
	 */
	public function getFields() {
		return $this->fields;
	}

	/**
	 * Returns widget fields data as array.
	 *
	 * @return array  Key/value pairs where key is field name and value is it's data.
	 */
	public function getFieldsData() {
		$data = [];

		foreach ($this->fields as $field) {
			/* @var $field CWidgetField */
			$data[$field->getName()] = $field->getValue();
		}

		return $data;
	}

	/**
	 * Validate form fields.
	 *
	 * @param bool $strict  Enables more strict validation of the form fields.
	 *                      Must be enabled for validation of input parameters in the widget configuration form.
	 *
	 * @return bool
	 */
	public function validate($strict = false) {
		$errors = [];

		foreach ($this->fields as $field) {
			$errors = array_merge($errors, $field->validate($strict));
		}

		return $errors;
	}

	/**
	 * Prepares array, ready to be passed to CDashboard API functions.
	 *
	 * @return array  Array of widget fields ready for saving in API.
	 */
	public function fieldsToApi() {
		$api_fields = [];

		foreach ($this->fields as $field) {
			$field->toApi($api_fields);
		}

		return $api_fields;
	}
}

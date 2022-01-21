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


class CWidgetForm {

	protected $fields;

	/**
	 * Widget fields array that came from AJAX request.
	 *
	 * @var array
	 */
	protected $data;

	protected $templateid;

	public function __construct($data, $templateid, $type) {
		$this->data = json_decode($data, true);

		$this->templateid = $templateid;

		$this->fields = [];

		if ($templateid === null) {
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

			$rf_rate_field = (new CWidgetFieldSelect('rf_rate', _('Refresh interval'), $rf_rates))
				->setDefault(-1);

			if (array_key_exists('rf_rate', $this->data)) {
				$rf_rate_field->setValue($this->data['rf_rate']);
			}

			$this->fields[$rf_rate_field->getName()] = $rf_rate_field;
		}

		// Add Columns and Rows fields for Iterator widgets.

		if (CWidgetConfig::isIterator($type)) {
			$field_columns = (new CWidgetFieldIntegerBox('columns', _('Columns'), 1, DASHBOARD_MAX_COLUMNS))
				->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
				->setDefault(2);

			if (array_key_exists('columns', $this->data)) {
				$field_columns->setValue($this->data['columns']);
			}

			$this->fields[$field_columns->getName()] = $field_columns;

			$field_rows = (new CWidgetFieldIntegerBox('rows', _('Rows'), 1,
					floor(DASHBOARD_WIDGET_MAX_ROWS / DASHBOARD_WIDGET_MIN_ROWS)))
				->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
				->setDefault(1);

			if (array_key_exists('rows', $this->data)) {
				$field_rows->setValue($this->data['rows']);
			}

			$this->fields[$field_rows->getName()] = $field_rows;
		}
	}

	/**
	 * Convert all dot-delimited keys to arrays of objects.
	 * Example:
	 *   source:                             result:
	 *   [                                   [
	 *       'tags.tag.0' => 'tag1',             'tags' => [
	 *       'tags.value.0' => 'value1',             ['tag' => 'tag1', 'value' => 'value1'],
	 *       'tags.tag.1' => 'tag2',                 ['tag' => 'tag2', 'value' => 'value2']
	 *       'tags.value.1' => 'value2',         ],
	 *       'ds.hosts.0.0' => 'host1',          'ds' => [
	 *       'ds.hosts.1.0' => 'host2',              [
	 *       'ds.hosts.1.1' => 'host3',                  'hosts' => ['host1'],
	 *       'ds.color.0' => 'AB43C5',                   'color' => 'AB43C5'
	 *       'ds.color.1' => 'CCCCCC',               ],
	 *       'ds.hosts.1.1' => 'host3',              [
	 *       'problemhosts.0' => 'ph1',                  'hosts => ['host2', 'host3'],
	 *       'problemhosts.1' => 'ph2'                   'color' => 'CCCCCC'
	 *   ]                                           ],
	 *                                           ],
	 *                                           'problemhosts' => ['ph1', 'ph2']
	 *                                       ]
	 *
	 * @static
	 *
	 * @param array $data  An array of key => value pairs.
	 *
	 * @return array
	 */
	protected static function convertDottedKeys(array $data) {
		// API doesn't guarantee fields to be retrieved in same order as stored. Sorting by key...
		uksort($data, function ($key1, $key2) {
			foreach (['key1', 'key2'] as $var) {
				if (preg_match('/^([a-z]+)\.([a-z_]+)\.(\d+)\.(\d+)$/', (string) $$var, $matches) === 1) {
					$$var = $matches[1].'.'.$matches[3].'.'.$matches[2].'.'.$matches[4];
				}
				elseif (preg_match('/^([a-z]+)\.([a-z_]+)\.(\d+)$/', (string) $$var, $matches) === 1) {
					$$var = $matches[1].'.'.$matches[3].'.'.$matches[2];
				}
			}

			return strnatcmp((string) $key1, (string) $key2);
		});

		// Converting dot-delimited keys to the arrays.
		foreach ($data as $key => $value) {
			if (preg_match('/^([a-z]+)\.([a-z_]+)\.(\d+)\.(\d+)$/', (string) $key, $matches) === 1) {
				$data[$matches[1]][$matches[3]][$matches[2]][$matches[4]] = $value;
				unset($data[$key]);
			}
			elseif (preg_match('/^([a-z]+)\.([a-z_]+)\.(\d+)$/', (string) $key, $matches) === 1) {
				$data[$matches[1]][$matches[3]][$matches[2]] = $value;
				unset($data[$key]);
			}
			elseif (preg_match('/^([a-z]+)\.(\d+)$/', (string) $key, $matches) === 1) {
				$data[$matches[1]][$matches[2]] = $value;
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
	 * @return array
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

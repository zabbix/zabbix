<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Zabbix\Widgets;

class CWidgetForm {

	protected array $fields = [];

	protected array $values;
	protected ?string $templateid;

	public function __construct(array $values, ?string $templateid) {
		$this->values = $this->normalizeValues($values);
		$this->templateid = $templateid;
	}

	public function addFields(): self {
		return $this;
	}

	public function addField(?CWidgetField $field): self {
		if ($field !== null) {
			$this->fields[$field->getName()] = $field;
		}

		return $this;
	}

	public function getFields(): array {
		return $this->fields;
	}

	public function getFieldValue(string $field_name) {
		return $this->fields[$field_name]->getValue();
	}

	public function getFieldsValues(): array {
		$values = [];

		foreach ($this->fields as $field) {
			$values[$field->getName()] = $field->getValue();
		}

		return $values;
	}

	public function setFieldsValues(): self {
		foreach ($this->fields as $field) {
			if (array_key_exists($field->getName(), $this->values)) {
				$field->setValue($this->values[$field->getName()]);
			}
		}

		return $this;
	}

	/**
	 * Validate form fields.
	 *
	 * @param bool $strict  Enables more strict validation of the form fields.
	 *                      Must be enabled for validation of input parameters in the widget configuration form.
	 *
	 * @return array
	 */
	public function validate(bool $strict = false): array {
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
	public function fieldsToApi(): array {
		$api_fields = [];

		foreach ($this->fields as $field) {
			$field->toApi($api_fields);
		}

		return $api_fields;
	}

	protected function normalizeValues(array $values): array {
		return self::convertDottedKeys($values);
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
	protected static function convertDottedKeys(array $data): array {
		// API doesn't guarantee fields to be retrieved in same order as stored. Sorting by key...
		uksort($data, static function ($key1, $key2) {
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
}

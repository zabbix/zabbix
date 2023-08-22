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
	private $templateid;

	public function __construct(array $values, $templateid = null) {
		$this->values = $this->normalizeValues($values);
		$this->templateid = $templateid;
	}

	public function addFields(): self {
		return $this;
	}

	public function addField(?CWidgetField $field): self {
		if ($field !== null) {
			$field->setTemplateId($this->templateid);

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

	public function isTemplateDashboard(): bool {
		return $this->templateid !== null;
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
		return $values;
	}
}

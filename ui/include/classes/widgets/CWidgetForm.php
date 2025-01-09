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

	public function getField(string $field_name): CWidgetField {
		return $this->fields[$field_name];
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
	 * Validate widget fields.
	 *
	 * @param bool $strict  If true, the submitted form data is strictly validated and all fields with not-empty flag
	 *                      set must be filled-in. If false, the saved data is loosely validated and fields with
	 *                      not-empty flag set, relating to database objects (like hosts or items) are allowed to be
	 *                      missing (deleted or not available due to insufficient permissions).
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

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

class CWidgetField {

	const FLAG_ACKNOWLEDGES = 0x01;
	const FLAG_NOT_EMPTY = 0x02;
	const FLAG_LABEL_ASTERISK = 0x04;

	protected	$name;
	protected	$label;
	protected	$value;
	protected	$default;
	protected	$save_type;
	protected	$action;
	private		$validation_rules = [];
	private		$ex_validation_rules = [];
	private		$flags;

	/**
	 * Create widget field (general)
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label = null) {
		$this->name = $name;
		$this->label = $label;
		$this->value = null;
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->flags = 0x00;
	}

	public function setValue($value) {
		$this->value = $value;

		return $this;
	}

	public function setDefault($value) {
		$this->default = $value;

		return $this;
	}

	/**
	 * Set JS code that will be called on field change.
	 *
	 * @param string $action  JS function to call on field change.
	 *
	 * @return $this
	 */
	public function setAction($action) {
		$this->action = $action;

		return $this;
	}

	protected function setSaveType($save_type) {
		switch ($save_type) {
			case ZBX_WIDGET_FIELD_TYPE_INT32:
				$this->validation_rules = ['type' => API_INT32];
				break;

			case ZBX_WIDGET_FIELD_TYPE_STR:
				$this->validation_rules = ['type' => API_STRING_UTF8, 'length' => 255];
				break;

			case ZBX_WIDGET_FIELD_TYPE_ITEM:
			case ZBX_WIDGET_FIELD_TYPE_GROUP:
			case ZBX_WIDGET_FIELD_TYPE_HOST:
				$this->validation_rules = ['type' => API_IDS];
				break;

			case ZBX_WIDGET_FIELD_TYPE_MAP:
			case ZBX_WIDGET_FIELD_TYPE_GRAPH:
				$this->validation_rules = ['type' => API_ID];
				break;

			default:
				exit(_('Internal error.'));
		}

		$this->save_type = $save_type;

		return $this;
	}

	protected function setValidationRules(array $validation_rules) {
		$this->validation_rules = $validation_rules;
	}

	protected function setExValidationRules(array $ex_validation_rules) {
		$this->ex_validation_rules = $ex_validation_rules;
	}

	/**
	 * Get field value. If no value is set, will return default value.
	 *
	 * @return mixed
	 */
	public function getValue() {
		return ($this->value === null) ? $this->default : $this->value;
	}

	public function getLabel() {
		return $this->label;
	}

	public function getName() {
		return $this->name;
	}

	public function getAction() {
		return $this->action;
	}

	public function getSaveType() {
		return $this->save_type;
	}

	/**
	 * Set additional flags, which can be used in configuration form.
	 *
	 * @param int $flags
	 *
	 * @return $this
	 */
	public function setFlags($flags) {
		$this->flags = $flags;

		return $this;
	}

	/**
	 * Get additional flags, which can be used in configuration form.
	 *
	 * @return int
	 */
	public function getFlags() {
		return $this->flags;
	}

	/**
	 * Validate field.
	 *
	 * @param bool $strict  Enables more strict validation of the field.
	 *
	 * @return bool
	 */
	public function validate($strict = false) {
		$errors = [];

		$validation_rules = $this->validation_rules + $this->ex_validation_rules;
		$value = $this->getValue();
		$label = ($this->label === null) ? $this->name : $this->label;

		if (!CApiInputValidator::validate($validation_rules, $value, $label, $error)) {
			$this->setValue($this->default);
			$errors[] = $error;
		}

		return $errors;
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

		if ($value !== null && $value !== $this->default) {
			$widget_field = [
				'type' => $this->save_type,
				'name' => $this->name
			];

			if (is_array($value)) {
				foreach ($value as $val) {
					$widget_field['value'] = $val;
					$widget_fields[] = $widget_field;
				}
			}
			else {
				$widget_field['value'] = $value;
				$widget_fields[] = $widget_field;
			}
		}
	}
}

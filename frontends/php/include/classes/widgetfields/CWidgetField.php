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

class CWidgetField {

	protected $name;
	protected $label;
	protected $value;
	protected $default;
	protected $save_type;
	protected $action;
	protected $setup_type;

	/**
	 * Create widget field (general)
	 *
	 * @param string $name   field name in form
	 * @param string $label  label for the field in form
	 */
	public function __construct($name, $label = null) {
		$this->name = $name;
		$this->label = $label;
		$this->value = null;
		$this->save_type = ZBX_WIDGET_FIELD_TYPE_STR;
		$this->setup_type = WIDGET_FIELDS_SETUP_TYPE_CONFIG;
	}

	public function setValue($value) {
		if ($value === '' || $value === null) {
			$value = null;
		}
		if ($this->save_type === ZBX_WIDGET_FIELD_TYPE_INT32) {
			$value = (int)$value;
		}
		$this->value = $value;
		return $this;
	}

	public function setDefault($value) {
		$this->default = $value;
		return $this;
	}

	/**
	 * Set JS code that will be called on field change
	 *
	 * @param string $action  JS function to call on field change
	 *
	 * @return $this
	 */
	public function setAction($action) {
		$this->action = $action;
		return $this;
	}

	/**
	 * Get field value
	 *
	 * @param bool $with_default  replaces missing value with default one
	 *
	 * @return mixed
	 */
	public function getValue($with_default = false) {
		$value = $this->value;

		if ($with_default === true) {
			// display default value, if no other given
			$value = ($this->value === null) ? $this->default : $this->value;
		}

		return $value;
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

	public function validate() {
		$errors = [];

		// Check based on save type
		switch ($this->save_type) {
			case ZBX_WIDGET_FIELD_TYPE_INT32:
				if ($this->value < ZBX_MIN_INT32 && $this->value > ZBX_MAX_INT32) {
					$errors[] = _s('Incorrect value "%1$s" for "%2$s" field.', $this->getLabel());
				}
				break;
			case ZBX_WIDGET_FIELD_TYPE_STR:
				// TODO VM: (?) should we have define for this?
				if (mb_strlen($this->value) > 255) {
					$errors[] = _s('Parameter "%1$s" should be shorter than %2$s characters.', $this->getLabel(), 255);
				}
				break;
			case ZBX_WIDGET_FIELD_TYPE_GROUP:
			case ZBX_WIDGET_FIELD_TYPE_ITEM:
			case ZBX_WIDGET_FIELD_TYPE_MAP:
				// TODO VM: write validation for ID type
				break;

			default:
				break;
		}

		return $errors;
	}

	/**
	 * Prepares array entry for widget field, ready to be passed to CDashboard API functions
	 *
	 * @return array  Array for widget fields ready for saving in API.
	 */
	public function toApi() {
		$value = $this->getValue(true);
		$widget_fields = [];

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

		return $widget_fields;
	}

	protected function setSaveType($save_type) {
		$known_save_types = [
			ZBX_WIDGET_FIELD_TYPE_INT32,
			ZBX_WIDGET_FIELD_TYPE_STR,
			ZBX_WIDGET_FIELD_TYPE_GROUP,
			ZBX_WIDGET_FIELD_TYPE_ITEM,
			ZBX_WIDGET_FIELD_TYPE_MAP
		];

		if (in_array($save_type, $known_save_types)) {
			$this->save_type = $save_type;
		}
	}
}

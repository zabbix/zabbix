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
	protected $required;
	protected $setup_type;

	/**
	 * Create widget field (general)
	 *
	 * @param string $name field name in form
	 * @param string $label label for the field in form
	 * @param mixed $default default value
	 * @param string $action JS function to call on field change
	 */
	public function __construct($name, $label = null, $default = null, $action = null) {
		$this->name = $name;
		$this->label = $label;
		$this->value = null;
		$this->default = $default;
		$this->save_type = ZBX_WIDGET_FIELD_TYPE_STR;
		$this->action = $action;
		$this->required = false;
		$this->setup_type = WIDGET_FIELDS_SETUP_TYPE_CONFIG;
	}

	public function setRequired($value) {
		$this->required = ($value === true) ? true : false;
		return $this;
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

	/**
	 * Get field value
	 *
	 * @param bool $with_default replaces missing value with default one
	 *
	 * @return mixed
	 */
	public function getValue($with_default = false) {
		$value = $this->value;

		if ($with_default === true) {
			$value = ($this->value === null) ? $this->default : $this->value; // display default value, if no other given
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

		if ($this->required === true && $this->value === null) {
			$errors[] = _s('The parameter "%1$s" is missing.', $this->getLabel());
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

		if ($value !== null) {
			$api_field_key = CWidgetConfig::getApiFieldKey($this->save_type);
			$widget_field = [
				'type' => $this->save_type,
				'name' => $this->name
			];

			if (is_array($value)) {
				foreach ($value as $val) {
					$widget_field[$api_field_key] = $val;
					$widget_fields[] = $widget_field;
				}
			}
			else {
				$widget_field[$api_field_key] = $value;
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

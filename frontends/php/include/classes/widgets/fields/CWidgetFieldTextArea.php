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


class CWidgetFieldTextArea extends CWidgetField {

	protected $attributes;

	/**
	 * Textarea widget field.
	 *
	 * @param string $name  field name in form
	 * @param string $label  label for the field in form
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setDefault('');
		$this->attributes = [];

		/**
		 * Set validation rules bypassing a parent::setSaveType to skip validation of length.
		 * Save type is set in self::toApi method for each string field separately.
		 */
		$this->setValidationRules(['type' => API_STRING_UTF8]);
	}

	public function validate($strict = false) {
		$errors = parent::validate($strict);

		if (!$errors && $strict && ($this->getFlags() & CWidgetField::FLAG_NOT_EMPTY) && $this->getValue() === '') {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->getLabel(), _('cannot be empty'));
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
			foreach (CWidgetHelper::splitPatternIntoParts($value) as $num => $val) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$num,
					'value' => $val
				];
			}
		}
	}

	public function setAttribute($name, $value) {
		$this->attributes[$name] = $value;
		return $this;
	}

	public function getAttribute($name) {
		return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : null;
	}
}

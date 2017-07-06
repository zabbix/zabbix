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

class CWidgetFieldRadioButtonList extends CWidgetField {

	private $values;
	private $modern = false;

	/**
	 * Radio button widget field. Can use both, string and integer type keys.
	 *
	 * @param string $name       field name in form
	 * @param string $label      label for the field in form
	 * @param array  $values     key/value pairs of radio button values. Key - saved in DB. Value - visible to user.
	 * @param int    $save_type  ZBX_WIDGET_FIELD_TYPE_ constant. Defines how field will be saved in database.
	 */
	public function __construct($name, $label, $values, $save_type = ZBX_WIDGET_FIELD_TYPE_INT32) {
		parent::__construct($name, $label);
		$this->setSaveType($save_type);
		$this->values = $values;
	}

	public function setModern($modern) {
		$this->modern = $modern;

		return $this;
	}

	public function getModern() {
		return $this->modern;
	}

	public function setValue($value) {
		if ($value === null) {
			parent::setValue($value);
		} elseif (array_key_exists($value, $this->values)) {
			parent::setValue($value);
		}
		return $this;
	}

	public function getValues() {
		return $this->values;
	}

	public function validate()
	{
		$errors = parent::validate();

		if (!in_array($this->getValue(true), array_keys($this->values))) {
			$errors[] = _s('Invalid parameter "%1$s".', $this->getLabel());
		}

		return $errors;
	}
}

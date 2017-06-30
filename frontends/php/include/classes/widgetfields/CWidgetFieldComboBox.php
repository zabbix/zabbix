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

class CWidgetFieldComboBox extends CWidgetField {

	protected $values;

	public function __construct($name, $label, $values, $default = null, $action = null, $save_type = ZBX_WIDGET_FIELD_TYPE_STR) {
		parent::__construct($name, $label, $default, $action);
		$this->setSaveType($save_type);
		$this->values = $values;
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
}

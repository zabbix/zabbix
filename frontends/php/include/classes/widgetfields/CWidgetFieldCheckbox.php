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

class CWidgetFieldCheckbox extends CWidgetField {

	public function __construct($name, $label, $default = 0) {
		parent::__construct($name, $label, $default, null);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32);
	}

	public function setValue($value) {
		// Only values in this array will be considered as true, any other value will be considered as false.
		$true_values = [true,1,'1'];
		$value = (in_array($value, $true_values)) ? 1 : 0;
		return parent::setValue($value);
	}
}

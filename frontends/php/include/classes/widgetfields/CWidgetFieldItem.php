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

class CWidgetFieldItem extends CWidgetField
{
	/**
	 * Create widget field for Item selection
	 *
	 * @param string $name  field name in form
	 * @param string $label  label for the field in form
	 * @param string $default  default Item Id value
	 */
	public function __construct($name, $label, $default = '0') {
		parent::__construct($name, $label, $default, null);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_ITEM);
	}

	public function setValue($value) {
		if (in_array($value, [null, 0, '0'])) {
			$this->value = '0';
		}
		else {
			$this->value = $value;
		}
		return $this;
	}

	public function validate() {
		$errors = parent::validate();
		if ($this->required === true && bccomp($this->value, '0') === 0) {
			$errors[] = _s('the parameter "%1$s" is missing', $this->label);
		}

		return $errors;
	}
}

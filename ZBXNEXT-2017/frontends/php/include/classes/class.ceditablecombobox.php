<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CEditableComboBox extends CComboBox {

	public function __construct($name = 'editablecombobox', $value = null, $size = 0, $action = null) {
		insert_javascript_for_editable_combobox();
		parent::__construct($name, $value, $action);
		parent::addAction('onfocus', 'CEditableComboBoxInit(this);');
		parent::addAction('onchange', 'CEditableComboBoxOnChange(this, '.$size.');');
	}

	public function addItem($value, $caption = '', $selected = null, $enabled = 'yes', $class = null) {
		if (is_null($selected)) {
			if (is_array($this->value)) {
				if (str_in_array($value, $this->value)) {
					$this->value_exist = 1;
				}
			}
			elseif (strcmp($value, $this->value) == 0) {
				$this->value_exist = 1;
			}
		}
		parent::addItem($value, $caption, $selected, $enabled, $class);
	}

	public function toString($destroy = true) {
		if (!isset($this->value_exist) && !empty($this->value)) {
			$this->addItem($this->value, $this->value, 'yes');
		}
		return parent::toString($destroy);
	}
}

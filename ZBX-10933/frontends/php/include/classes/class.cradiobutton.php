<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
?>
<?php

class CRadioButton extends CTag {

	public function __construct($name, $value, $class = null, $id = null, $checked = false, $action = null) {
		parent::__construct('input', 'no'); // no means not paired
		$this->setAttribute('class', $class);
		$this->setAttribute('name', $name);
		$this->setAttribute('value', $value);
		$this->setAttribute('id', $id);
		$this->setAttribute('type', 'radio');
		if ($checked) {
			$this->setAttribute('checked', 'checked');
		}
		if (!empty($action)) {
			$this->setAttribute('onchange', $action);
		}
	}
}
?>

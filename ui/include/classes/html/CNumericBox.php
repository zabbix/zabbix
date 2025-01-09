<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CNumericBox extends CInput {

	public function __construct($name = 'number', $value = '0', $maxlength = 15, $readonly = false, $allowempty = false,
			$allownegative = true) {
		parent::__construct('text', $name, $value);
		$this->setReadonly($readonly);
		$this->setAttribute('maxlength', $maxlength);
		$this->onChange(
			'validateNumericBox(this, '.($allowempty ? 'true' : 'false').', '.($allownegative ? 'true' : 'false').');'
		);
	}

	public function setWidth($value) {
		$this->addStyle('width: '.$value.'px;');
		return $this;
	}
}

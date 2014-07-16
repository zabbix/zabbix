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


class CInput extends CTag {

	public function __construct($type = 'text', $name = 'textbox', $value = '', $class = null, $id = null) {
		parent::__construct('input', 'no');
		$this->setType($type);

		// if id is not passed, it will be the same as element name
		if (is_null($id)) {
			$this->attr('id', zbx_formatDomId($name));
		}
		else {
			$this->attr('id', zbx_formatDomId($id));
		}
		$this->attr('name', $name);
		$this->attr('value', $value);
		$class = !is_null($class) ? $class : $type;
		if ($class == 'button' || $class == 'submit') {
			$class .= ' shadow ui-corner-all';
		}
		$this->addClass('input '.$class);
		return $this;
	}

	public function setType($type) {
		$this->attr('type', $type);
		return $this;
	}

	public function setReadonly($value = 'yes') {
		if ((is_string($value) && ($value == 'yes' || $value == 'checked' || $value == 'on') || $value == '1') || (is_int($value) && $value <> 0) || $value === true) {
			$this->attr('readonly', 'readonly');
			return $this;
		}
		$this->removeAttr('readonly');
		return $this;
	}

	public function setEnabled($value = 'yes') {
		if ((is_string($value) && ($value == 'yes' || $value == 'checked' || $value == 'on') || $value == '1') || (is_int($value) && $value <> 0) || $value === true) {
			$this->removeAttr('disabled');
			return $this;
		}
		$this->attr('disabled', 'disabled');
		return $this;
	}

	public function useJQueryStyle($class = '') {
		$this->attr('class', 'jqueryinput '.$this->getAttribute('class').' '.$class);
	}
}

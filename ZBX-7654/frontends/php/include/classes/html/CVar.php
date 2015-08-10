<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CVar {

	public $var_container;
	public $var_name;
	public $element_id;

	public function __construct($name, $value = null, $id = null) {
		$this->var_container = [];
		$this->var_name = $name;
		$this->element_id = $id;
		$this->setValue($value);
	}

	public function setValue($value) {
		$this->var_container = [];
		if ($value !== null) {
			$this->parseValue($this->var_name, $value);
		}
		return $this;
	}

	private function parseValue($name, $value) {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				if (is_null($item)) {
					continue;
				}
				$this->parseValue($name.'['.$key.']', $item);
			}
			return null;
		}
		if (strpos($value, "\n") === false) {
			$hiddenVar = (new CInput('hidden', $name, $value))->removeAttribute('class');

			if ($this->element_id !== null) {
				$hiddenVar->setId($this->element_id);
			}
		}
		else {
			$hiddenVar = (new CTextArea($name, $value))->setAttribute('class', 'hidden');
		}
		$this->var_container[] = $hiddenVar;
	}

	public function toString() {
		$res = '';
		foreach ($this->var_container as $item) {
			$res .= $item->toString();
		}
		return $res;
	}
}

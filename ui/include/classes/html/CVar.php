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
			$this->parseValue($this->var_name, $value, $this->element_id);
		}

		return $this;
	}

	private function parseValue($name, $value, ?string $id) {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				if (is_null($item)) {
					continue;
				}
				$this->parseValue($name.'['.$key.']', $item, $id !== null ? $id.'_'.$key : null);
			}
			return null;
		}

		if (strpos($value, "\n") === false) {
			$hiddenVar = new CInput('hidden', $name, $value);
		}
		else {
			$hiddenVar = (new CTextArea($name, $value))->addStyle('display: none;');
		}

		if ($id !== null) {
			$hiddenVar->setId($id);
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

	/**
	 * Remove ID attribute from tag.
	 *
	 * @return CVar
	 */
	public function removeId() {
		foreach ($this->var_container as $item) {
			$item->removeAttribute('id');
		}

		return $this;
	}

	/**
	 * Enable or disable the element.
	 *
	 * @param bool $value
	 *
	 * @return CVar
	 */
	public function setEnabled($value) {
		foreach ($this->var_container as $item) {
			$item->setEnabled($value);
		}

		return $this;
	}
}

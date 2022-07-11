<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	/**
	 * Enabled or disabled state of input field.
	 *
	 * @var bool
	 */
	protected $enabled = true;

	public function __construct($type = 'text', $name = 'textbox', $value = '') {
		parent::__construct('input');
		$this->setType($type);

		if ($name !== null) {
			$this->setId(zbx_formatDomId($name));
			$this->setAttribute('name', $name);
		}

		$this->setAttribute('value', $value);
	}

	public function setType($type) {
		$this->setAttribute('type', $type);
		return $this;
	}

	public function setReadonly($value) {
		if ($value) {
			$this->setAttribute('readonly', 'readonly');
			$this->setAttribute('tabindex', '-1');
		}
		else {
			$this->removeAttribute('readonly');
			$this->removeAttribute('tabindex');
		}
		return $this;
	}

	/**
	 * Prevent browser to autocomplete input element.
	 */
	public function disableAutocomplete() {
		$this->setAttribute('autocomplete', 'off');

		return $this;
	}

	/**
	 * Enable or disable the element.
	 *
	 * @param bool $value
	 */
	public function setEnabled($value) {
		if ($value) {
			$this->removeAttribute('disabled');
		}
		else {
			$this->setAttribute('disabled', 'disabled');
		}

		return $this;
	}

	public function removeAttribute($name) {
		if ($name === 'disabled') {
			$this->enabled = false;
		}

		return parent::removeAttribute($name);
	}

	public function setAttribute($name, $value) {
		if ($name === 'disabled') {
			$this->enabled = ($value !== 'disabled');
		}

		return parent::setAttribute($name, $value);
	}
}

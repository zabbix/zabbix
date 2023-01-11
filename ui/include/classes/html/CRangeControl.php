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


/**
 * Class for range control creation.
 */
class CRangeControl extends CTextBox {
	/**
	 * Default CSS class name for HTML root element.
	 */
	const ZBX_STYLE_CLASS = 'range-control';

	/**
	 * Options array for javascript initialization class.crangecontrol.js plugin.
	 */
	private $options = [
		'min' => 0,
		'max' => ZBX_MAX_INT32,
		'step' => 1,
		'width' => ZBX_TEXTAREA_SMALL_WIDTH
	];

	public function __construct($name, $value = '') {
		parent::__construct($name);

		$this->setValue($value);
		$this->addClass(static::ZBX_STYLE_CLASS);
	}

	public function setValue($value) {
		$this->setAttribute('value', $value);
		return $this;
	}

	public function setMin($value) {
		$this->options['min'] = $value;
		return $this;
	}

	public function setMax($value) {
		$this->options['max'] = $value;
		return $this;
	}

	public function setStep($value) {
		$this->options['step'] = $value;
		return $this;
	}

	public function setWidth($value) {
		$this->options['width'] = $value;
		return $this;
	}

	public function getPostJS() {
		return 'jQuery("[name=\''.$this->getName().'\']").rangeControl();';
	}

	public function toString($destroy = true) {
		// Set options for jQuery rangeControl class.
		$this->setAttribute('data-options', json_encode($this->options));
		$this->setAttribute('maxlength', max(strlen($this->options['min']), strlen($this->options['max'])));

		return parent::toString($destroy);
	}
}

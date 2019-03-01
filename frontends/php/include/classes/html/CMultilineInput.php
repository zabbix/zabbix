<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CMultilineInput extends CDiv {
	/**
	 * Default CSS class name for HTML root element.
	 */
	const ZBX_STYLE_CLASS = 'multilineinput-control';

	private $name;

	private $value;

	private $options;

	public function __construct($name = 'multilineinput', $value = '', array $options = []) {
		parent::__construct();

		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->setId(zbx_formatDomId($name))
			->setAttribute('data-name', $name);

		$this->name = $name;
		$this->value = $value;
		$this->options = $options + [
			'modal_title' => '',
			'title' => '',
			'placeholder' => '',
			'maxlength' => 65535,
			'readonly' => false,
			'disabled' => false,
			'line_numbers' => true,
			'monospace_font' => true
		];
	}

	public function setValue($value) {
		$this->value = $value;

		return $this;
	}

	public function setOption($key, $value) {
		$this->options[$key] = $value;
	}

	public function setMaxlength($maxlength) {
		$this->options['maxlength'] = $maxlength;

		return $this;
	}

	public function setReadonly($readonly) {
		$this->options['readonly'] = $readonly;

		return $this;
	}

	public function setEnabled($enabled) {
		$this->options['disabled'] = !$enabled;

		return $this;
	}

	public function setDisabled($disabled) {
		$this->options['disabled'] = $disabled;

		return $this;
	}

	public function getPostJS() {
		return 'jQuery("#'.$this->getId().'").multilineInput('.CJs::encodeJson(
			['name' => $this->name, 'value' => $this->value] + $this->options).');';
	}

	public function toString($destroy = true) {
		if (!array_key_exists('add_post_js', $this->options) || $this->options['add_post_js']) {
			zbx_add_post_js($this->getPostJS());
		}

		return parent::toString($destroy);
	}
}

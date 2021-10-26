<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CColor extends CDiv {

	private $name;
	private $value;
	private $is_enabled = true;
	private $is_required = false;
	private $append_color_picker_js = true;
	private $default_color;
	private $input_id;

	/**
	 * Creates a color picker form element.
	 *
	 * @param string $name        Color picker field name.
	 * @param string $value       Color value in HEX RGB format.
	 * @param string $input_id    (optional) Color input field id.
	 */
	public function __construct($name, $value, $input_id = null) {
		parent::__construct();

		$this->name = $name;
		$this->value = $value;
		$this->input_id = $input_id;
	}

	/**
	 * Enable or disable the element.
	 *
	 * @param bool $is_enabled
	 *
	 * @return CColor
	 */
	public function setEnabled($is_enabled = true) {
		$this->is_enabled = $is_enabled;

		return $this;
	}

	/**
	 * Set default color.
	 *
	 * @param string $default_color  Default color.
	 *
	 * @return CColor
	 */
	public function setDefaultColor(string $default_color) {
		$this->default_color = $default_color;

		return $this;
	}

	/**
	 * Set or reset element 'aria-required' attribute.
	 *
	 * @param bool $is_required
	 *
	 * @return CColor
	 */
	public function setAriaRequired($is_required = true) {
		$this->is_required = $is_required;

		return $this;
	}

	/**
	 * Append color picker javascript.
	 *
	 * @param bool $append
	 *
	 * @return CColor
	 */
	public function appendColorPickerJs($append = true)
	{
		$this->append_color_picker_js = $append;

		return $this;
	}

	/**
	 * Make coloricker initialization javascript.
	 *
	 * @return string
	 */
	protected function getInitJavascript(): string {
		$options = [
			'default_color' => $this->default_color
		];

		return 'jQuery("#'.$this->name.'").colorpicker('.json_encode(array_filter($options)).');';
	}

	/**
	 * Gets string representation of widget HTML content.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$this->cleanItems();

		$input = (new CInput('hidden', $this->name, $this->value))->setEnabled($this->is_enabled);

		if ($this->input_id !== null) {
			$input->setId($this->input_id);
		}

		$this->addItem($input);

		$this->addClass(ZBX_STYLE_INPUT_COLOR_PICKER);

		$init_script = $this->append_color_picker_js ? get_js($this->getInitJavascript()) : '';

		return parent::toString($destroy).$init_script;
	}
}

<?php declare(strict_types = 1);
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


class CColor extends CDiv {

	private $name;
	private $value;
	private $enabled = true;
	private $append_color_picker_js = true;
	private $input_id;

	/**
	 * Either "Use default" is enabled.
	 *
	 * @var bool
	 */
	private $use_default = false;

	/**
	 * Creates a color picker form element.
	 *
	 * @param string $name      Color picker field name.
	 * @param string $value     Color value in HEX RGB format.
	 * @param string $input_id  (optional) Color input field id.
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
	 * @param bool $enabled
	 *
	 * @return CColor
	 */
	public function setEnabled(bool $enabled = true): self {
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * Enable default color button.

	 * @return CColor
	 */
	public function enableUseDefault(): self {
		$this->use_default = true;

		return $this;
	}

	/**
	 * Append color picker javascript.
	 *
	 * @param bool $append
	 *
	 * @return CColor
	 */
	public function appendColorPickerJs(bool $append = true): self {
		$this->append_color_picker_js = $append;

		return $this;
	}

	/**
	 * Make colorpicker initialization javascript.
	 *
	 * @return string
	 */
	protected function getInitJavascript(): string {
		return 'jQuery("#'.$this->name.'").colorpicker('.json_encode([
			'use_default' => $this->use_default
		]).');';
	}

	/**
	 * Gets string representation of widget HTML content.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true): string {
		$input = (new CInput('hidden', $this->name, $this->value))->setEnabled($this->enabled);

		if ($this->input_id !== null) {
			$input->setId($this->input_id);
		}

		$this
			->addClass(ZBX_STYLE_COLOR_PICKER)
			->cleanItems()
			->addItem($input);

		return parent::toString($destroy).($this->append_color_picker_js ? get_js($this->getInitJavascript()) : '');
	}
}

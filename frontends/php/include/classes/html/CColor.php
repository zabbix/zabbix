<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

	const MAX_LENGTH = 6;

	private $name;
	private $value;
	private $is_enabled = true;
	private $is_required = false;
	private $append_color_picker_js = true;

	/**
	 * Creates a color picker form element.
	 *
	 * @param string $name	Color picker field name.
	 * @param string $value Color value in HEX RGB format.
	 */
	public function __construct($name, $value) {
		parent::__construct();

		$this->name = $name;
		$this->value = $value;
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
	 * Gets string representation of widget HTML content.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$this->cleanItems();

		$this->addItem([
			(new CColorCell('lbl_'.$this->name, $this->value))
				->setTitle('#'.$this->value)
				->onClick('javascript: show_color_picker("'.zbx_formatDomId($this->name).'", this)'),
			(new CTextBox($this->name, $this->value))
				->setWidth(ZBX_TEXTAREA_COLOR_WIDTH)
				->setAttribute('maxlength', self::MAX_LENGTH)
				->setEnabled($this->is_enabled)
				->setAriaRequired($this->is_required)
				->onChange('set_color_by_name("'.zbx_formatDomId($this->name).'", this.value)')
		]);

		$this->addClass(ZBX_STYLE_INPUT_COLOR_PICKER);

		if ($this->append_color_picker_js) {
			insert_show_color_picker_javascript();
		}

		return parent::toString($destroy);
	}
}

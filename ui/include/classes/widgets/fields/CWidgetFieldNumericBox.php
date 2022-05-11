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
 * Widget Field for numeric data.
 */
class CWidgetFieldNumericBox extends CWidgetField {

	private $placeholder;
	private $width;

	/**
	 * A numeric box widget field.
	 * Supported signed decimal values with suffix (KMGTsmhdw).
	 *
	 * @param string $name   field name in form
	 * @param string $label  label for the field in form
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_NUMERIC, 'length' => 255]);
		$this->setDefault('');
	}

	public function getMaxLength() {
		return strlen((string) $this->max);
	}

	public function setPlaceholder($placeholder) {
		$this->placeholder = $placeholder;

		return $this;
	}

	public function getPlaceholder() {
		return $this->placeholder;
	}

	public function setWidth($width) {
		$this->width = $width;

		return $this;
	}

	public function getWidth() {
		return $this->width;
	}
}

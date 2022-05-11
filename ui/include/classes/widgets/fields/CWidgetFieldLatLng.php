<?php declare(strict_types = 0);
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


class CWidgetFieldLatLng extends CWidgetField {

	/**
	 * @var string
	 */
	private $placeholder;

	/**
	 * @var int
	 */
	private $width;

	/**
	 * Latitude, longitude and zoom level input text box widget field.
	 *
	 * @param string $name  field name in form
	 * @param string $label  label for the field in form
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_LAT_LNG_ZOOM, 'length' => 255]);
		$this->placeholder = '40.6892494,-74.0466891';
		$this->width = ZBX_TEXTAREA_MEDIUM_WIDTH;
		$this->setDefault('');
	}

	public function getPlaceholder() {
		return $this->placeholder;
	}

	public function getWidth() {
		return $this->width;
	}
}

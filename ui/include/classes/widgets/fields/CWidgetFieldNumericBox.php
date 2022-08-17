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


class CWidgetFieldNumericBox extends CWidgetField {

	private int $width = ZBX_TEXTAREA_NUMERIC_BIG_WIDTH;

	private string $placeholder = '';

	/**
	 * A numeric box widget field.
	 * Supported signed decimal values with suffix (KMGTsmhdw).
	 */
	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_NUMERIC, 'length' => 255]);
		$this->setDefault('');
	}

	public function getPlaceholder(): string {
		return $this->placeholder;
	}

	public function setPlaceholder(string $placeholder): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	public function getWidth(): int {
		return $this->width;
	}

	public function setWidth(int $width): self {
		$this->width = $width;

		return $this;
	}
}

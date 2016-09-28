<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CCheckBox extends CInput {

	const LABEL_POSITION_LEFT = 0;
	const LABEL_POSITION_RIGHT = 1;

	private $label = '';
	private $label_position = self::LABEL_POSITION_RIGHT;

	public function __construct($name = 'checkbox', $value = '1') {
		parent::__construct('checkbox', $name, $value);
		$this->setChecked(false);
		$this->addClass(ZBX_STYLE_CHECKBOX_RADIO);
	}

	public function setChecked($checked) {
		if ($checked) {
			$this->attributes['checked'] = 'checked';
		}
		else {
			$this->removeAttribute('checked');
		}

		return $this;
	}

	public function setLabel($label) {
		$this->label = $label;

		return $this;
	}

	public function setLabelPosition($label_position) {
		$this->label_position = $label_position;

		return $this;
	}

	public function toString($destroy = true) {
		$elements = ($this->label_position == self::LABEL_POSITION_LEFT)
			? [$this->label, new CSpan()]
			: [new CSpan(), $this->label];

		return parent::toString($destroy).((new CLabel($elements, $this->getId()))->toString(true));
	}
}

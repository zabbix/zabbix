<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CRadioButtonList extends CDiv {

	const ORIENTATION_HORIZONTAL = 'horizontal';
	const ORIENTATION_VERTICAL = 'vertical';

	protected $count;
	protected $name;
	protected $value;
	protected $orientation;

	public function __construct($name = 'radio', $value = 'yes') {
		$this->count = 0;
		$this->name = $name;
		$this->value = $value;
		$this->orientation = self::ORIENTATION_HORIZONTAL;
		parent::__construct(null, null, $name);
	}

	public function addValue($name, $value, $checked = null) {
		$this->count++;

		$id = str_replace(array('[', ']'), array('_'), $this->name).'_'.$this->count;

		$radio = new CInput('radio', $this->name, $value);
		$radio->attr('id', zbx_formatDomId($id));

		if (strcmp($value, $this->value) == 0 || !is_null($checked) || $checked) {
			$radio->attr('checked', 'checked');
		}

		$label = new CLabel($name, $id);

		$outerDiv = new CDiv(array($radio, $label));
		if ($this->orientation == self::ORIENTATION_HORIZONTAL) {
			$outerDiv->addClass('inlineblock');
		}

		parent::addItem($outerDiv);
	}

	public function makeHorizaontal() {
		$this->orientation = self::ORIENTATION_HORIZONTAL;
	}

	public function makeVertical() {
		$this->orientation = self::ORIENTATION_VERTICAL;
	}
}

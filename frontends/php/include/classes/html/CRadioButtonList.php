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


class CRadioButtonList extends CList {

	const ORIENTATION_HORIZONTAL = 'horizontal';
	const ORIENTATION_VERTICAL = 'vertical';

	private $name;
	private $value;
	private $orientation;
	private $enabled;
	private $values;
	private $modern;
	private $autofocused;

	public function __construct($name, $value) {
		parent::__construct();

		$this->name = $name;
		$this->value = $value;
		$this->orientation = self::ORIENTATION_HORIZONTAL;
		$this->enabled = true;
		$this->values = [];
		$this->modern = false;
		$this->setId(zbx_formatDomId($name));
	}

	public function addValue($name, $value, $id = null, $on_change = null) {
		$this->values[] = [
			'name' => $name,
			'value' => $value,
			'id' => ($id === null ? null : zbx_formatDomId($id)),
			'on_change' => $on_change
		];

		return $this;
	}

	public function makeVertical() {
		$this->orientation = self::ORIENTATION_VERTICAL;

		return $this;
	}

	public function setEnabled($enabled) {
		$this->enabled = $enabled;

		return $this;
	}

	public function setModern($modern) {
		$this->modern = $modern;

		return $this;
	}

	public function toString($destroy = true) {
		if ($this->modern) {
			$this->addClass(ZBX_STYLE_RADIO_SEGMENTED);
		}
		else {
			$this->addClass($this->orientation == self::ORIENTATION_HORIZONTAL
				? ZBX_STYLE_LIST_HOR_CHECK_RADIO
				: ZBX_STYLE_LIST_CHECK_RADIO
			);
		}

		foreach ($this->values as $key => $value) {
			if ($value['id'] === null) {
				$value['id'] = zbx_formatDomId($this->name).'_'.$key;
			}

			$radio = (new CInput('radio', $this->name, $value['value']))
				->setEnabled($this->enabled)
				->onChange($value['on_change'])
				->setId($value['id']);

			if ($value['value'] === $this->value) {
				$radio->setAttribute('checked', 'checked');

				if ($this->autofocused) {
					$radio->setAttribute('autofocus', 'autofocus');
				}
			}

			if ($this->modern) {
				parent::addItem([$radio, new CLabel($value['name'], $value['id'])]);
			}
			else {
				$radio->addClass(ZBX_STYLE_CHECKBOX_RADIO);
				parent::addItem([$radio, new CLabel([new CSpan(), $value['name']], $value['id'])]);
			}
		}

		if ($this->getAttribute('aria-required') === 'true') {
			$this->setAttribute('role', 'radiogroup');
		}

		return parent::toString($destroy);
	}

	/**
	 * Overrides base method to correctly handle autofocus attribute for radio buttons.
	 *
	 * @param $name
	 * @param $value
	 *
	 * @return CRadioButtonList
	 */
	public function setAttribute($name, $value) {
		if ($name === 'autofocus') {
			$this->autofocused = true;

			return $this;
		}

		return parent::setAttribute($name, $value);
	}
}

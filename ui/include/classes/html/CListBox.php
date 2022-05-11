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


class CListBox extends CTag {

	public $value;

	public function __construct($name = 'listbox', $value = null, $size = 5, array $items = []) {
		parent::__construct('select', true);
		$this->setId(zbx_formatDomId($name));
		$this->setAttribute('name', $name);
		$this->value = $value;
		$this->addItems($items);

		// Prevent Firefox remembering selected option on page refresh.
		$this->setAttribute('autocomplete', 'off');

		$this->setAttribute('multiple', 'multiple');
		$this->setAttribute('size', $size);
	}

	public function setValue($value = null) {
		$this->value = $value;
		return $this;
	}

	public function addItems(array $items) {
		foreach ($items as $value => $caption) {
			$selected = (strcmp($value, $this->value) == 0);
			parent::addItem(new CListBoxItem($value, $caption, $selected));
		}
		return $this;
	}

	public function addItem($value, $caption = '', $selected = null, $enabled = true, $class = null) {
		if ($value instanceof CListBoxItem) {
			parent::addItem($value);
		}
		else {
			if (is_null($selected)) {
				$selected = false;
				if (is_array($this->value)) {
					if (str_in_array($value, $this->value)) {
						$selected = true;
					}
				}
				elseif (strcmp($value, $this->value) == 0) {
					$selected = true;
				}
			}
			else {
				$selected = true;
			}

			$citem = new CListBoxItem($value, $caption, $selected, $enabled);

			if ($class !== null) {
				$citem->addClass($class);
			}

			parent::addItem($citem);
		}
		return $this;
	}

	/**
	 * Enable or disable readonly mode for the element.
	 *
	 * @param bool $value
	 *
	 * @return self
	 */
	public function setReadonly($value) {
		if ($value) {
			$this->setAttribute('readonly', 'readonly');
			$this->setAttribute('tabindex', '-1');
		}
		else {
			$this->removeAttribute('readonly');
			$this->removeAttribute('tabindex');
		}

		return $this;
	}

	/**
	 * Enable or disable the element.
	 *
	 * @param $value
	 */
	public function setEnabled($value) {
		if ($value) {
			$this->removeAttribute('disabled');
		}
		else {
			$this->setAttribute('disabled', 'disabled');
		}
		return $this;
	}

	/**
	 * Set with of the element.
	 *
	 * @param int $value  Width in pixels of the element.
	 *
	 * @return self
	 */
	public function setWidth($value) {
		$this->addStyle('width: '.$value.'px;');

		return $this;
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CComboBox extends CTag {

	public $value;

	public function __construct($name = 'combobox', $value = null, $action = null, array $items = []) {
		parent::__construct('select', true);
		$this->setId(zbx_formatDomId($name));
		$this->setAttribute('name', $name);
		$this->value = $value;
		if ($action !== null) {
			$this->onChange($action);
		}
		$this->addItems($items);

		// Prevent Firefox remembering selected option on page refresh.
		$this->setAttribute('autocomplete', 'off');
	}

	public function setValue($value = null) {
		$this->value = $value;
		return $this;
	}

	public function addItems(array $items) {
		foreach ($items as $value => $caption) {
			$selected = (int) (strcmp($value, $this->value) == 0);
			parent::addItem(new CComboItem($value, $caption, $selected));
		}
		return $this;
	}

	public function addItemsInGroup($label, $items) {
		$group = new COptGroup($label);
		foreach ($items as $value => $caption) {
			$selected = (int) (strcmp($value, $this->value) == 0);
			$group->addItem(new CComboItem($value, $caption, $selected));
		}
		parent::addItem($group);
		return $this;
	}

	public function addItem($value, $caption = '', $selected = null, $enabled = true, $class = null) {
		if ($value instanceof CComboItem || $value instanceof COptGroup) {
			parent::addItem($value);
		}
		else {
			if (is_null($selected)) {
				$selected = 'no';
				if (is_array($this->value)) {
					if (str_in_array($value, $this->value)) {
						$selected = 'yes';
					}
				}
				elseif (strcmp($value, $this->value) == 0) {
					$selected = 'yes';
				}
			}
			else {
				$selected = 'yes';
			}

			$citem = new CComboItem($value, $caption, $selected, $enabled);

			if ($class !== null) {
				$citem->addClass($class);
			}

			parent::addItem($citem);
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
}

class COptGroup extends CTag {

	public function __construct($label) {
		parent::__construct('optgroup', true);
		$this->setAttribute('label', $label);
	}
}

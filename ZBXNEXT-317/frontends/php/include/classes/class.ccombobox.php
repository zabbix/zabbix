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


class CComboBox extends CTag {

	public $value;

	public function __construct($name = 'combobox', $value = null, $action = null, $items = null) {
		parent::__construct('select', 'yes');
		$this->tag_end = '';
		$this->attr('id', zbx_formatDomId($name));
		$this->attr('name', $name);
		$this->attr('class', 'input select');
		$this->attr('size', 1);
		$this->value = $value;
		$this->attr('onchange', $action);
		if (is_array($items)) {
			$this->addItems($items);
		}
	}

	public function setValue($value = null) {
		$this->value = $value;
	}

	public function addItems($items) {
		foreach ($items as $value => $caption) {
			$selected = (int) ($value == $this->value);
			parent::addItem(new CComboItem($value, $caption, $selected));
		}
	}

	public function addItemsInGroup($label, $items) {
		$group = new COptGroup($label);
		foreach ($items as $value => $caption) {
			$selected = (int) ($value == $this->value);
			$group->addItem(new CComboItem($value, $caption, $selected));

			if (strcmp($value, $this->value) == 0) {
				$this->value_exist = 1;
			}
		}
		parent::addItem($group);
	}

	public function addItem($value, $caption = '', $selected = null, $enabled = 'yes', $class = null) {
		if ($value instanceof CComboItem || $value instanceof COptGroup) {
			parent::addItem($value);
		}
		else {
			$title = false;
			// if caption is too long ( > 44 symbols), we add new class - 'selectShorten',
			// so that the select box would not stretch
			if (zbx_strlen($caption) > 44 && !$this->hasClass('selectShorten')) {
				$this->setAttribute('class', $this->getAttribute('class').' selectShorten');
				$title = true;
			}
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
			if ($title) {
				$citem->setTitle($caption);
			}
			if ($class !== null) {
				$citem->addClass($class);
			}
			parent::addItem($citem);
		}
	}
}

class COptGroup extends CTag {

	public function __construct($label) {
		parent::__construct('optgroup', 'yes');
		$this->setAttribute('label', $label);
	}
}

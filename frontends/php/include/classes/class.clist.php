<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CList extends CTag {

	public $emptyList;

	/**
	 * Creates a UL list.
	 *
	 * @param mixed $value			a single or an array of values to add to the list
	 * @param string $class			HTML class
	 * @param string $emptyString	text to display if the list is empty
	 */
	public function __construct($value = null, $class = null, $emptyString = null) {
		parent::__construct('ul', 'yes');
		$this->tag_end = '';
		$this->addItem($value);
		$this->addClass($class);

		if (is_null($value)) {
			$emptyString = (!zbx_empty($emptyString)) ? $emptyString : _('List is empty');
			$this->addItem($emptyString, 'empty');
			$this->emptyList = true;
		}
	}

	public function prepareItem($value = null, $class = null, $id = null) {
		if (!is_null($value)) {
			$value = new CListItem($value, $class, $id);
		}

		return $value;
	}

	public function addItem($value, $class = null, $id = null) {
		if (!is_null($value) && $this->emptyList) {
			$this->emptyList = false;
			$this->items = array();
		}

		if ($value instanceof CListItem) {
			parent::addItem($value);
		}
		else {
			parent::addItem($this->prepareItem($value, $class, $id));
		}
	}
}

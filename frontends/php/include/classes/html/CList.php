<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	private $emptyList;

	/**
	 * Creates a UL list.
	 *
	 * @param array $values			an array of items to add to the list
	 */
	public function __construct(array $values = []) {
		parent::__construct('ul', true);

		foreach ($values as $value) {
			$this->addItem($value);
		}

		if (!$values) {
			$this->addItem(_('List is empty'), 'empty');
			$this->emptyList = true;
		}
	}

	public function prepareItem($value = null, $class = null, $id = null) {
		if ($value !== null) {
			$value = new CListItem($value);
			if ($class !== null) {
				$value->addClass($class);
			}
			if ($id !== null) {
				$value->setId($id);
			}
		}

		return $value;
	}

	public function addItem($value, $class = null, $id = null) {
		if (!is_null($value) && $this->emptyList) {
			$this->emptyList = false;
			$this->items = [];
		}

		if ($value instanceof CListItem) {
			parent::addItem($value);
		}
		else {
			parent::addItem($this->prepareItem($value, $class, $id));
		}

		return $this;
	}

}

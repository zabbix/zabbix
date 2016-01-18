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


class CRow extends CTag {

	public function __construct($item = null) {
		parent::__construct('tr', true);
		$this->addItem($item);
	}

	public function addItem($item) {
		if (is_object($item) && strtolower(get_class($item)) === 'ccol') {
			parent::addItem($item);
		}
		elseif (is_array($item)) {
			foreach ($item as $el) {
				if (is_object($el) && strtolower(get_class($el)) === 'ccol') {
					parent::addItem($el);
				}
				elseif (!is_null($el)) {
					parent::addItem(new CCol($el));
				}
			}
		}
		elseif (!is_null($item)) {
			parent::addItem(new CCol($item));
		}
		return $this;
	}
}

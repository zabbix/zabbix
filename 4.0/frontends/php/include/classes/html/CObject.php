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


class CObject {

	public $items;

	public function __construct($items = null) {
		$this->items = [];
		if (isset($items)) {
			$this->addItem($items);
		}
	}

	public function toString($destroy = true) {
		$res = implode('', $this->items);
		if ($destroy) {
			$this->destroy();
		}
		return $res;
	}

	public function __toString() {
		return $this->toString();
	}

	public function show($destroy = true) {
		echo $this->toString($destroy);
		return $this;
	}

	public function destroy() {
		$this->cleanItems();
		return $this;
	}

	public function cleanItems() {
		$this->items = [];
		return $this;
	}

	public function itemsCount() {
		return count($this->items);
	}

	public function addItem($value) {
		if (is_object($value)) {
			array_push($this->items, unpack_object($value));
		}
		elseif (is_string($value)) {
			array_push($this->items, $value);
		}
		elseif (is_array($value)) {
			foreach ($value as $item) {
				$this->addItem($item); // attention, recursion !!!
			}
		}
		elseif (!is_null($value)) {
			array_push($this->items, unpack_object($value));
		}
		return $this;
	}
}

function unpack_object(&$item) {
	$res = '';
	if (is_object($item)) {
		$res = $item->toString(false);
	}
	elseif (is_array($item)) {
		foreach ($item as $id => $dat) {
			$res .= unpack_object($item[$id]); // attention, recursion !!!
		}
	}
	elseif (!is_null($item)) {
		$res = strval($item);
		unset($item);
	}
	return $res;
}

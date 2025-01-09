<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	if ($item instanceof CPartial) {
		$res = $item->getOutput();
	}
	elseif (is_object($item)) {
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

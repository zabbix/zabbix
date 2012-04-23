<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
class CObject {
	public $items;

	public function __construct($items = null) {
		$this->items = array();
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

	public function show($destroy = true) {
		echo $this->toString($destroy);
	}

	public function destroy() {
		$this->cleanItems();
	}

	public function cleanItems() {
		$this->items = array();
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
				$this->addItem($item); // Attention, recursion !!!
			}
		}
		elseif (!is_null($value)) {
			array_push($this->items, unpack_object($value));
		}
	}
}

function unpack_object(&$item) {
	$res = '';

	if (is_object($item)) {
		$res = $item->toString(false);
	}
	else if (is_array($item)) {
		foreach ($item as $id => $dat) {
			$res .= unpack_object($item[$id]); // Attention, recursion !!!
		}
	}
	else if (!is_null($item)) {
		$res = strval($item);
		unset($item);
	}
	return $res;
}
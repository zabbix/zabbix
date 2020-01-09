<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CMenu {

	protected $label;
	protected $items = [];
	protected $alias = [];
	protected $visible = null;
	protected $action;
	protected $selected = false;

	public function __construct($label, array $item) {
		$this->label = $label;
		$this->action = array_key_exists('action', $item) ? $item['action'] : '';
		$this->alias = array_key_exists('alias', $item) ? $item['alias'] : [];
		$this->items = [];
		$this->uniqueid = array_key_exists('uniqueid', $item)
			? $item['uniqueid']
			: 'uid'.base_convert(mt_rand(), 10, 32);

		if (array_key_exists('items', $item)) {
			foreach ($item['items'] as $child_label => $child_item) {
				$this->add($child_label, $child_item);
			}
		}

		if (array_key_exists('visible', $item) && is_callable($item['visible'])) {
			$this->visible = $item['visible'];
		}
	}

	public function getAlias() {
		return $this->alias;
	}

	public function getAction() {
		return $this->action;
	}

	public function getItems() {
		return $this->items;
	}

	public function getLabel() {
		return $this->label;
	}

	public function getSelected() {
		return array_reduce($this->items, function($carry, $child) {
			return $carry || $child->getSelected();
		}, $this->selected);
	}

	public function getUniqueid() {
		return $this->uniqueid;
	}

	public function setSelected($action) {
		if ($this->action === $action || in_array($action, $this->alias)) {
			$this->selected = true;
		}
		else {
			foreach ($this->items as $item) {
				$item->setSelected($action);
			}
		}

		return $this;
	}

	public function add($label, $item) {
		$this->items[$label] = new CMenu($label, $item);

		return $this;
	}

	public function remove($label) {
		unset($this->items[$label]);

		return $this;
	}

	public function find($label) {
		return $this->has($label) ? $this->items[$label] : null;
	}

	public function has($label) {
		return array_key_exists($label, $this->items);
	}

	public function insertBefore($before_label, $label, $data) {
		$this->insert($before_label, $label, $data, false);

		return $this;
	}

	public function insertAfter($after_label, $label, $data) {
		$this->insert($after_label, $label, $data);

		return $this;
	}

	private function insert($target_label, $label, $data, $after = true) {
		if ($this->has($target_label)) {
			$index = array_search($target_label, array_keys($this->items));
			$before = ($index > 0 || ($after && $index == 0))
				? array_slice($this->items, 0, $index + ($after ? 1 : 0))
				: [];
			$after = $index < count($this->items) ? array_slice($this->items, $index + ($after ? 1 : 0)) : [];
			$this->items = $before + [$label => new CMenu($label, $data)] + $after;
		}
	}
}

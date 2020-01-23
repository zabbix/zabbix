<?php declare(strict_types = 1);
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
	protected $action;

	protected $items;
	protected $alias;

	protected $selected = false;

	private $uniqueid;

	/**
	 * Create menu class instance.
	 *
	 * @param string $label             Visible label.
	 * @param string $item['action']    MVC action name or php file name for non-mvc actions.
	 * @param array  $item['alias']     Array of aliases for permission checks.
	 * @param string $item['uniqueid']  String qith unique identifier.
	 * @param array  $item['items']     Array of child menu entries.
	 */
	public function __construct(string $label, array $item) {
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
	}

	/**
	 * Getter for alias property.
	 *
	 * @return array
	 */
	public function getAlias(): array {
		return $this->alias;
	}

	/**
	 * Getter for action property.
	 *
	 * @return string
	 */
	public function getAction(): string {
		return $this->action;
	}

	/**
	 * Getter for items property.
	 *
	 * @return array
	 */
	public function getItems(): array {
		return $this->items;
	}

	/**
	 * Getter for visual label property.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Getter for uniqueid property.
	 *
	 * @return string
	 */
	public function getUniqueId(): string {
		return $this->uniqueid;
	}

	/**
	 * Check is menu element or it nested items marked as selected.
	 *
	 * @return bool
	 */
	public function isSelected(): bool {
		return array_reduce($this->items, function(bool $carry, CMenu $child) {
			return $carry || $child->isSelected();
		}, $this->selected);
	}

	/**
	 * Set selected property on current menu entry or it nested items according passed $action.
	 *
	 * @param string $action  Action name to be selected.
	 *
	 * @return CMenu
	 */
	public function setSelected(string $action): CMenu {
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

	/**
	 * Add nested menu items.
	 *
	 * @param string $label  Visual label.
	 * @param array  $item   Item data.
	 *
	 * @return CMenu
	 */
	public function add(string $label, array $item): CMenu {
		$this->items[$label] = new CMenu($label, $item);

		return $this;
	}

	/**
	 * Remove nested item by visual label.
	 *
	 * @param string $label  Visual label.
	 *
	 * @return CMenu
	 */
	public function remove(string $label): CMenu {
		unset($this->items[$label]);

		return $this;
	}

	/**
	 * Find nested item by visual label.
	 *
	 * @param string $label    Visaul label.
	 *
	 * @return CMenu|null
	 */
	public function find(string $label): ?CMenu {
		return $this->has($label) ? $this->items[$label] : null;
	}

	/**
	 * Check element contains nested item with visual label.
	 *
	 * @param string $label  Visaul label.
	 *
	 * @return bool
	 */
	public function has(string $label): bool {
		return array_key_exists($label, $this->items);
	}

	/**
	 * Add new nested element before nested element with $before_label visual label.
	 *
	 * @param string $before_label  Visual label to insert item before.
	 * @param string $label         New item visual label.
	 * @param array  $item          New item data.
	 *
	 * @return CMenu
	 */
	public function insertBefore(string $before_label, string $label, array $item): CMenu {
		$this->insert($before_label, $label, $item, false);

		return $this;
	}

	/**
	 * Add new nested element after nested element with $after_label visual label.
	 *
	 * @param string $after_label  Visual label to insert item before.
	 * @param string $label        New item visual label.
	 * @param array  $item         New item data.
	 *
	 * @return CMenu
	 */
	public function insertAfter(string $after_label, string $label, array $item): CMenu {
		$this->insert($after_label, $label, $item);

		return $this;
	}

	/**
	 * Generic method to insert new item before or after specific item.
	 *
	 * @param string $target_label  Visual label to insert item before or after.
	 * @param string $label         New item visual label.
	 * @param array  $item          New item data.
	 * @param bool   $after         Insert new item before or after $target_label
	 */
	private function insert(string $target_label, string $label, array $item, bool $after = true): void {
		if ($this->has($target_label)) {
			$index = array_search($target_label, array_keys($this->items));
			$before = ($index > 0 || ($after && $index == 0))
				? array_slice($this->items, 0, $index + ($after ? 1 : 0))
				: [];
			$after = $index < count($this->items) ? array_slice($this->items, $index + ($after ? 1 : 0)) : [];
			$this->items = $before + [$label => new CMenu($label, $item)] + $after;
		}
	}
}
